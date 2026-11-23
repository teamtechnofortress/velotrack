<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VeloTrackBackup;
use App\Services\AuthCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VeloTrackController extends Controller
{
    public function __construct(private AuthCodeService $codes) {}

    private function userPayload(User $user): array
    {
        return $user->only('id', 'name', 'email') + ['email_verified' => $user->hasVerifiedEmail()];
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'string', 'min:8'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
        $this->codes->send($user, AuthCodeService::VERIFY);
        return response()->json(['user' => $this->userPayload($user), 'token' => $user->createToken($data['device_name'])->plainTextToken], 201);
    }
    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required','email'], 'password' => ['required','string'], 'device_name' => ['required','string','max:100']]);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) throw ValidationException::withMessages(['email' => ['Invalid email or password.']]);
        return response()->json(['user' => $this->userPayload($user), 'token' => $user->createToken($data['device_name'])->plainTextToken]);
    }
    public function me(Request $request) { return $this->userPayload($request->user()); }
    public function verifyEmail(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();
        if ($user->hasVerifiedEmail()) return response()->json(['user' => $this->userPayload($user)]);
        if (!$this->codes->check($user, AuthCodeService::VERIFY, $data['code'])) {
            throw ValidationException::withMessages(['code' => ['Invalid or expired code.']]);
        }
        $user->markEmailAsVerified();
        return response()->json(['user' => $this->userPayload($user->fresh())]);
    }
    public function resendVerification(Request $request)
    {
        $user = $request->user();
        if (!$user->hasVerifiedEmail()) $this->codes->send($user, AuthCodeService::VERIFY);
        return response()->json(['message' => 'If your email is not verified, a new code has been sent.']);
    }
    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $data['email'])->first();
        if ($user) $this->codes->send($user, AuthCodeService::RESET);
        // Same response whether or not the account exists (no account enumeration).
        return response()->json(['message' => 'If that email has an account, a reset code has been sent.']);
    }
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', 'string', 'min:8'],
        ]);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !$this->codes->check($user, AuthCodeService::RESET, $data['code'])) {
            throw ValidationException::withMessages(['code' => ['Invalid or expired code.']]);
        }
        $user->forceFill(['password' => $data['password']])->save();
        if (!$user->hasVerifiedEmail()) $user->markEmailAsVerified(); // they proved inbox access
        $user->tokens()->delete(); // sign out every device
        return response()->json(['message' => 'Password updated. Please sign in.']);
    }
    public function deleteAccount(Request $request)
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        if (!Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['Incorrect password.']]);
        }
        // Every per-user table cascades on delete; tokens are polymorphic so remove them explicitly.
        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });
        return response()->json(['message' => 'Account deleted']);
    }
    public function logout(Request $request) { $request->user()->currentAccessToken()->delete(); return response()->json(['message' => 'Signed out']); }
    public function upload(Request $request)
    {
        $data = $request->validate([
            'snapshot' => ['required', 'array'],
            'snapshot.schema' => ['required', 'integer', 'in:1'],
            'snapshot.vehicles' => ['required','array','max:2000'],
            'snapshot.entries' => ['required','array','max:20000'],
            'snapshot.reminders' => ['required','array','max:5000'],
            'snapshot.inspections' => ['required','array','max:5000'],
        ]);
        // One full JSON snapshot per account; last successful upload wins.
        $backup = VeloTrackBackup::updateOrCreate(['user_id' => $request->user()->id], ['snapshot' => $data['snapshot']]);
        return response()->json(['updated_at' => $backup->updated_at->toIso8601String()]);
    }
    public function download(Request $request)
    {
        $backup = VeloTrackBackup::where('user_id', $request->user()->id)->first();
        if (!$backup) return response()->json(['message' => 'No cloud backup exists for this account.'], 404);
        return response()->json(['snapshot' => $backup->snapshot, 'updated_at' => $backup->updated_at->toIso8601String()]);
    }
}
