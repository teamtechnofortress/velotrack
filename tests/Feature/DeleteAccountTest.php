<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteAccountTest extends TestCase {
    use RefreshDatabase;

    public function test_requires_authentication(): void {
        $this->deleteJson('/api/auth/account', ['password' => 'password'])->assertUnauthorized();
    }

    public function test_wrong_password_keeps_account(): void {
        $u = User::factory()->create();
        Sanctum::actingAs($u);
        $this->deleteJson('/api/auth/account', ['password' => 'nope'])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $u->id]);
    }

    public function test_deletes_account_data_and_tokens(): void {
        $u = User::factory()->create();
        $u->createToken('t');
        $other = User::factory()->create();
        Sanctum::actingAs($u);
        $this->postJson('/api/data/sync', ['vehicles' => [['id'=>1,'name'=>'Car']]])->assertOk();
        $this->deleteJson('/api/auth/account', ['password' => 'password'])->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $u->id]);
        $this->assertDatabaseMissing('vehicles', ['user_id' => $u->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_unverified_user_can_delete(): void {
        $u = User::factory()->unverified()->create();
        Sanctum::actingAs($u);
        $this->deleteJson('/api/auth/account', ['password' => 'password'])->assertOk();
    }

    public function test_public_page_is_reachable(): void {
        $this->get('/delete-account')->assertOk()->assertSee('Delete your Drivio account');
    }
}
