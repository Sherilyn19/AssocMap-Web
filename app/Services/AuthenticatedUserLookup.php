<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\User;

final class AuthenticatedUserLookup
{
    public function find(int|string|null $userId): ?User
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        // Fetch current account and role information in one database query.
        // A left join preserves the user when its role is missing, allowing
        // the authentication middleware to reject that account normally.
        //
        // PostgreSQL packages the role separately so its id and timestamps
        // cannot overwrite fields belonging to the user.
        $user = User::query()
            ->leftJoin(
                'roles as authentication_role',
                'authentication_role.id',
                '=',
                'users.role_id'
            )
            ->select('users.*')
            ->selectRaw(
                'row_to_json(authentication_role) AS authentication_role_data'
            )
            ->where('users.id', $userId)
            ->first();

        if ($user === null) {
            return null;
        }

        $roleData = $user->getAttribute('authentication_role_data');

        // Remove the temporary query field from the User model.
        // Synchronize its original attributes so the lookup does not make
        // an otherwise unchanged user appear modified.
        $attributes = $user->getAttributes();
        unset($attributes['authentication_role_data']);
        $user->setRawAttributes($attributes, true);

        $role = null;

        if ($roleData !== null) {
            $roleAttributes = is_string($roleData)
                ? json_decode(
                    $roleData,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                )
                : $roleData;

            if (
                ! is_array($roleAttributes)
                || ! array_key_exists('id', $roleAttributes)
            ) {
                throw new \UnexpectedValueException(
                    'The account role could not be loaded.'
                );
            }

            // Hydrate an existing role without saving anything.
            // Retain all role fields and the user's database connection.
            $role = (new Role())->newFromBuilder(
                $roleAttributes,
                $user->getConnectionName()
            );
        }

        // Mark the relationship as loaded, even when no role exists.
        // Later access to $user->role therefore requires no extra query.
        $user->setRelation('role', $role);

        return $user;
    }
}