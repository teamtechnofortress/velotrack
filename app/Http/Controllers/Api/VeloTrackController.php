<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VeloTrackBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VeloTrackController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'string', 'min:8'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
        return response()->json(['user' => $user->only('id', 'name', 'email'), 'token' => $user->createToken($data['device_name'])->plainTextToken], 201);
    }
    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required','email'], 'password' => ['required','string'], 'device_name' => ['required','string','max:100']]);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) throw ValidationException::withMessages(['email' => ['Invalid email or password.']]);
        return response()->json(['user' => $user->only('id', 'name', 'email'), 'token' => $user->createToken($data['device_name'])->plainTextToken]);
    }
    public function me(Request $request) { return $request->user()->only('id', 'name', 'email'); }
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
