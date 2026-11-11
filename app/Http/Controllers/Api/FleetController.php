<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FleetDriver;
use App\Models\FleetInvitation;
use App\Models\FleetMembership;
use App\Models\FleetOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** All company access is scoped to the authenticated account on the server. */
class FleetController extends Controller
{
    private function workspace(Request $request, bool $write = false): array {
        $userId = $request->user()->id;
        $owned = FleetOrganization::where('user_id', $userId)->first();
        if ($owned) return [$owned, 'owner'];
        $membership = FleetMembership::where('user_id', $userId)->first();
        if (!$membership) abort(404, 'No company workspace linked to this account.');
        if ($write && $membership->role !== 'manager') abort(403, 'Manager access required.');
        return [FleetOrganization::findOrFail($membership->organization_id), $membership->role];
    }
    private function owner(Request $request): FleetOrganization {
        return FleetOrganization::where('user_id', $request->user()->id)->firstOrFail();
    }
    public function catalog() {
        return response()->json(['plans' => [
            ['code'=>'free','name'=>'Free','purchasable'=>false],
            ['code'=>'individual','name'=>'Individual','purchasable'=>false],
            ['code'=>'pro','name'=>'PRO','purchasable'=>false],
            ['code'=>'fleet','name'=>'Fleet','purchasable'=>false],
        ], 'note'=>'Plan preview only. Google Play Billing is not connected.']);
    }
    public function entitlement(Request $request) {
        return response()->json(['plan'=>'free','paid_verified'=>false,'fleet_subscription_active'=>false,
            'note'=>'No paid entitlements are issued without store verification. Staging fleet collaboration is available only for integration testing.']);
    }
    public function show(Request $request) {
        try { [$org,$role] = $this->workspace($request); }
        catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return response()->json(['organization'=>null,'role'=>null,'drivers'=>[], 'members'=>[]]);
        }
        return response()->json([
            'organization'=>$org->only('id','name'), 'role'=>$role,
            'drivers'=>$org->drivers()->orderBy('id')->get(),
            'members'=>$role === 'owner' ? FleetMembership::with('user:id,name,email')
                ->where('organization_id',$org->id)->get()->map(fn($m)=>[
                    'id'=>$m->id,'role'=>$m->role,'name'=>$m->user?->name,'email'=>$m->user?->email]) : [],
            'staging_only'=>true,
        ]);
    }
    public function save(Request $request) {
        $data = $request->validate(['name'=>['required','string','max:150']]);
        // An account already belonging to somebody else's company may not create one.
        if (FleetMembership::where('user_id', $request->user()->id)->exists()) abort(403, 'Leave your current company before creating another.');
        $org = FleetOrganization::updateOrCreate(['user_id'=>$request->user()->id], $data);
        return response()->json(['organization'=>$org->only('id','name'), 'role'=>'owner']);
    }
    private function validatedDriver(Request $request): array {
        return $request->validate([
            'name'=>['required','string','max:120'], 'email'=>['nullable','email','max:255'],
            'license_label'=>['nullable','string','max:100'],
            'vehicle_reference'=>['nullable','string','max:120'],
        ]);
    }
    public function addDriver(Request $request) {
        [$org] = $this->workspace($request, true);
        $driver = $org->drivers()->create($this->validatedDriver($request));
        return response()->json(['driver'=>$driver], 201);
    }
    public function editDriver(Request $request, int $id) {
        [$org] = $this->workspace($request, true);
        $data = $this->validatedDriver($request);
        $driver = $org->drivers()->findOrFail($id);
        $driver->update($data);
        return response()->json(['driver'=>$driver]);
    }
    public function removeDriver(Request $request, int $id) {
        [$org] = $this->workspace($request, true);
        $org->drivers()->findOrFail($id)->delete();
        return response()->json(['message'=>'Driver removed']);
    }
    public function invitation(Request $request) {
        $org = $this->owner($request);
        $data = $request->validate(['email'=>['required','email','max:255'], 'role'=>['required','in:manager,viewer']]);
        if (strcasecmp($data['email'], $request->user()->email) === 0) abort(422, 'Cannot invite yourself.');
        $token = Str::random(48);
        FleetInvitation::create(['organization_id'=>$org->id,'email'=>strtolower($data['email']),
            'role'=>$data['role'], 'token_hash'=>hash('sha256',$token), 'expires_at'=>now()->addDays(3)]);
        // Shown once to owner; send out of band. Do not place token into logs.
        return response()->json(['invite_code'=>$token,'expires_at'=>now()->addDays(3)->toIso8601String()],201);
    }
    public function accept(Request $request) {
        $data = $request->validate(['code'=>['required','string','min:20','max:150']]);
        $user = $request->user();
        return DB::transaction(function () use ($data,$user) {
            $invite = FleetInvitation::where('token_hash',hash('sha256',$data['code']))->lockForUpdate()->first();
            if (!$invite || $invite->accepted_at || $invite->expires_at->isPast() || strcasecmp($invite->email,$user->email)!==0)
                throw ValidationException::withMessages(['code'=>['Invitation invalid, expired, or assigned to another email.']]);
            if (FleetOrganization::where('user_id',$user->id)->exists() || FleetMembership::where('user_id',$user->id)->exists())
                throw ValidationException::withMessages(['code'=>['Account already belongs to a workspace.']]);
            FleetMembership::create(['organization_id'=>$invite->organization_id,'user_id'=>$user->id,'role'=>$invite->role]);
            $invite->update(['accepted_at'=>now()]);
            return response()->json(['message'=>'Joined workspace','role'=>$invite->role]);
        });
    }
    public function removeMember(Request $request, int $id) {
        $org = $this->owner($request);
        FleetMembership::where('organization_id',$org->id)->findOrFail($id)->delete();
        return response()->json(['message'=>'Member removed']);
    }
    public function leave(Request $request) {
        FleetMembership::where('user_id',$request->user()->id)->firstOrFail()->delete();
        return response()->json(['message'=>'Left workspace']);
    }
    public function dashboard(Request $request) {
        [$org,$role] = $this->workspace($request);
        return response()->json(['organization'=>$org->only('id','name'),'role'=>$role,
            'driver_count'=>$org->drivers()->count(),
            'member_count'=>FleetMembership::where('organization_id',$org->id)->count()+1,
            'notice'=>'Financial records remain device-local; this API does not aggregate vehicle expenses or income.']);
    }
}
