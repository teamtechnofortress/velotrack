<?php

namespace App\Services;

use App\Models\AuthCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/** Short-lived 6-digit codes sent by email; stored hashed, limited attempts. */
class AuthCodeService
{
    public const VERIFY = 'verify_email';
    public const RESET = 'reset_password';
    private const TTL_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;

    public function send(User $user, string $purpose): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        AuthCode::updateOrCreate(
            ['user_id' => $user->id, 'purpose' => $purpose],
            ['code_hash' => Hash::make($code), 'attempts' => 0, 'expires_at' => now()->addMinutes(self::TTL_MINUTES)]
        );
        $subject = $purpose === self::VERIFY ? 'Verify your VeloTrack email' : 'Reset your VeloTrack password';
        $intro = $purpose === self::VERIFY ? 'Your email verification code is' : 'Your password reset code is';
        Mail::raw("$intro: $code\n\nIt expires in ".self::TTL_MINUTES." minutes. If you did not request this, ignore this email.",
            fn ($m) => $m->to($user->email)->subject($subject));
    }

    /** Returns true and consumes the code when valid. */
    public function check(User $user, string $purpose, string $code): bool
    {
        $record = AuthCode::where('user_id', $user->id)->where('purpose', $purpose)->first();
        if (!$record || $record->expires_at->isPast() || $record->attempts >= self::MAX_ATTEMPTS) return false;
        if (!Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');
            return false;
        }
        $record->delete();
        return true;
    }
}
