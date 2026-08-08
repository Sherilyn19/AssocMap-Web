<?php

// app/Services/SessionUserResolver.php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

final class SessionUserResolver
{
    /**
     * Resolve the real database user behind AssocMap's custom auth_user session.
     *
     * AssocMap currently stores the authenticated identity in session('auth_user')
     * instead of Laravel's default guard. Centralizing the lookup prevents each
     * controller from guessing a different session key.
     */
    public function resolve(Request $request): User
    {
        $sessionUser = $request->session()->get('auth_user');
        $actorId = is_array($sessionUser) ? ($sessionUser['id'] ?? null) : null;

        $actorId ??= auth()->id();
        $actorId ??= $request->session()->get('user_id');
        $actorId ??= $request->session()->get('authenticated_user_id');

        abort_if(!$actorId, 401, 'Authenticated user could not be identified.');

        $user = User::query()
            ->with('role:id,role_name')
            ->find((int) $actorId);

        abort_if(!$user, 401, 'Authenticated user account could not be found.');
        abort_if(!$user->is_active, 403, 'This account is inactive.');

        return $user;
    }
}