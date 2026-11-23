<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserDataTest extends TestCase {
    use RefreshDatabase;

    private function payload(): array {
        return [
            'vehicles' => [['id'=>100,'name'=>'Hilux','make'=>'Toyota','model'=>'Hilux','year'=>'2020','plate'=>'ABC1','fuel'=>'Diesel','type'=>'Truck','vin'=>'']],
            'entries' => [['id'=>200,'vehicleId'=>100,'kind'=>'Fuel','title'=>'Diesel','amount'=>45.5,'date'=>'2026-11-21','quantity'=>30,'unit'=>'L','unitPrice'=>1.5,'notes'=>'']],
            'reminders' => [['id'=>300,'vehicleId'=>100,'title'=>'Oil','due'=>'2026-12-01','done'=>false]],
            'inspections' => [['id'=>400,'vehicleId'=>100,'date'=>'2026-11-21','tyre'=>true,'brakes'=>false,'lights'=>true,'battery'=>true,'notes'=>'x']],
        ];
    }

    public function test_requires_authentication_and_verified_email(): void {
        $this->getJson('/api/data')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->unverified()->create());
        $this->getJson('/api/data')->assertForbidden();
    }

    public function test_data_round_trips_in_app_format(): void {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/data/sync', $this->payload())->assertOk();
        $r = $this->getJson('/api/data')->assertOk();
        $r->assertJsonPath('vehicles.0.id', 100)->assertJsonPath('vehicles.0.name', 'Hilux')
          ->assertJsonPath('entries.0.vehicleId', 100)->assertJsonPath('entries.0.amount', 45.5)
          ->assertJsonPath('entries.0.unitPrice', 1.5)->assertJsonPath('reminders.0.done', false)
          ->assertJsonPath('inspections.0.brakes', false);
    }

    public function test_each_user_only_sees_their_own_data(): void {
        $a = User::factory()->create(); $b = User::factory()->create();
        Sanctum::actingAs($a);
        $this->postJson('/api/data/sync', $this->payload())->assertOk();
        Sanctum::actingAs($b);
        $this->getJson('/api/data')->assertOk()->assertJsonCount(0, 'vehicles')->assertJsonCount(0, 'entries');
        // Same client ids from another account must not touch user A's rows.
        $other = $this->payload(); $other['vehicles'][0]['name'] = 'Hacked';
        $this->postJson('/api/data/sync', $other)->assertOk();
        $this->postJson('/api/data/sync', ['deleted'=>['vehicles'=>[100]]])->assertOk();
        Sanctum::actingAs($a);
        $this->getJson('/api/data')->assertJsonPath('vehicles.0.name', 'Hilux')->assertJsonCount(1, 'entries');
    }

    public function test_sync_updates_and_is_idempotent(): void {
        Sanctum::actingAs(User::factory()->create());
        $p = $this->payload();
        $this->postJson('/api/data/sync', $p)->assertOk();
        $p['entries'][0]['amount'] = 99;
        $this->postJson('/api/data/sync', $p)->assertOk();
        $this->postJson('/api/data/sync', $p)->assertOk();
        $this->getJson('/api/data')->assertJsonCount(1, 'entries')->assertJsonPath('entries.0.amount', 99);
    }

    public function test_deleting_a_vehicle_removes_its_records(): void {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/data/sync', $this->payload())->assertOk();
        $this->postJson('/api/data/sync', ['deleted'=>['vehicles'=>[100]]])->assertOk();
        $this->getJson('/api/data')->assertJsonCount(0, 'vehicles')->assertJsonCount(0, 'entries')
            ->assertJsonCount(0, 'reminders')->assertJsonCount(0, 'inspections');
    }

    public function test_rejects_invalid_records(): void {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/data/sync', ['entries'=>[['id'=>1,'vehicleId'=>1,'kind'=>'Fuel','title'=>'x','amount'=>'abc','date'=>'2026-01-01']]])
            ->assertUnprocessable();
    }
}
