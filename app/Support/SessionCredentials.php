<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/** Bind a session to the password used at login without storing its hash. */
final class SessionCredentials
{
    public static function fingerprint(int|string $userId, ?string $passwordHash): string
    {
        return hash_hmac('sha256', $userId.'|'.$passwordHash, (string) config('app.key'));
    }

    public static function matches(Request $request, User $user): bool
    {
        $saved = $request->session()->get('auth_user.credential_fingerprint');

        // Older sessions must sign in again; accepting a missing marker would bypass revocation.
        return is_string($saved) && hash_equals(self::fingerprint($user->id, $user->password), $saved);
    }
}
