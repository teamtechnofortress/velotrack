<?php
namespace Tests\Feature;

use App\Models\FleetMembership;
use App\Models\FleetOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetAccessTest extends TestCase {
    use RefreshDatabase;

    public function test_anonymous_user_cannot_access_fleet(): void {
        $this->getJson('/api/fleet')->assertUnauthorized();
    }
    public function test_viewer_cannot_modify_driver_records(): void {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $org = FleetOrganization::create(['user_id'=>$owner->id,'name'=>'Demo Fleet']);
        FleetMembership::create(['organization_id'=>$org->id,'user_id'=>$viewer->id,'role'=>'viewer']);
        Sanctum::actingAs($viewer);
        $this->getJson('/api/fleet')->assertOk()->assertJsonPath('role','viewer');
        $this->postJson('/api/fleet/drivers',['name'=>'Driver A'])->assertForbidden();
    }
    public function test_fleet_driver_ids_are_scoped_to_organization(): void {
        $a = User::factory()->create(); $b = User::factory()->create();
        $companyA = FleetOrganization::create(['user_id'=>$a->id,'name'=>'A']);
        $companyB = FleetOrganization::create(['user_id'=>$b->id,'name'=>'B']);
        $driver = $companyB->drivers()->create(['name'=>'Private Driver']);
        Sanctum::actingAs($a);
        $this->deleteJson('/api/fleet/drivers/'.$driver->id)->assertNotFound();
        $this->assertDatabaseHas('fleet_drivers',['id'=>$driver->id]);
    }
    public function test_paid_entitlements_are_not_issued_by_preview_endpoint(): void {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/subscription')->assertOk()->assertJsonPath('paid_verified',false);
    }
}
