<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthFlowTest extends TestCase {
    use RefreshDatabase;

    private function captureCodes(array &$codes): void {
        Mail::shouldReceive('raw')->andReturnUsing(function ($body) use (&$codes) {
            preg_match('/: (\d{6})/', $body, $m); $codes[] = $m[1];
        });
    }

    private function register(): array {
        return $this->postJson('/api/auth/register', ['name'=>'Ali','email'=>'ali@example.com','password'=>'secret123',
            'password_confirmation'=>'secret123','device_name'=>'test'])->assertCreated()->json();
    }

    public function test_register_returns_unverified_user_and_sends_code(): void {
        $codes = []; $this->captureCodes($codes);
        $r = $this->register();
        $this->assertFalse($r['user']['email_verified']);
        $this->assertCount(1, $codes);
    }

    public function test_unverified_user_is_blocked_from_data_endpoints(): void {
        $codes = []; $this->captureCodes($codes);
        $r = $this->register();
        $h = ['Authorization' => 'Bearer '.$r['token']];
        $this->getJson('/api/fleet', $h)->assertForbidden();
        $this->getJson('/api/backup', $h)->assertForbidden();
        $this->getJson('/api/auth/me', $h)->assertOk()->assertJsonPath('email_verified', false);
    }

    public function test_correct_code_verifies_email(): void {
        $codes = []; $this->captureCodes($codes);
        $r = $this->register();
        $h = ['Authorization' => 'Bearer '.$r['token']];
        $this->postJson('/api/auth/email/verify', ['code'=>'000000'], $h)->assertUnprocessable();
        $this->postJson('/api/auth/email/verify', ['code'=>$codes[0]], $h)->assertOk()->assertJsonPath('user.email_verified', true);
        $this->getJson('/api/fleet', $h)->assertOk();
    }

    public function test_code_locks_after_five_wrong_attempts(): void {
        $codes = []; $this->captureCodes($codes);
        $r = $this->register();
        $h = ['Authorization' => 'Bearer '.$r['token']];
        $wrong = $codes[0] === '111111' ? '222222' : '111111';
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/auth/email/verify', ['code'=>$wrong], $h)->assertUnprocessable();
        $this->postJson('/api/auth/email/verify', ['code'=>$codes[0]], $h)->assertUnprocessable();
    }

    public function test_expired_code_is_rejected(): void {
        $codes = []; $this->captureCodes($codes);
        $r = $this->register();
        $this->travel(16)->minutes();
        $this->postJson('/api/auth/email/verify', ['code'=>$codes[0]], ['Authorization'=>'Bearer '.$r['token']])->assertUnprocessable();
    }

    public function test_forgot_password_does_not_reveal_whether_account_exists(): void {
        Mail::shouldReceive('raw')->never();
        $this->postJson('/api/auth/password/forgot', ['email'=>'nobody@example.com'])->assertOk();
    }

    public function test_password_reset_changes_password_and_revokes_tokens(): void {
        $user = User::factory()->create(['email'=>'ali@example.com','password'=>'oldpassword']);
        $user->createToken('d');
        $codes = []; $this->captureCodes($codes);
        $this->postJson('/api/auth/password/forgot', ['email'=>'ali@example.com'])->assertOk();
        $this->postJson('/api/auth/password/reset', ['email'=>'ali@example.com','code'=>'000000','password'=>'newpassword1','password_confirmation'=>'newpassword1'])->assertUnprocessable();
        $this->postJson('/api/auth/password/reset', ['email'=>'ali@example.com','code'=>$codes[0],'password'=>'newpassword1','password_confirmation'=>'newpassword1'])->assertOk();
        $this->assertTrue(Hash::check('newpassword1', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/auth/login', ['email'=>'ali@example.com','password'=>'newpassword1','device_name'=>'t'])->assertOk();
    }
}
