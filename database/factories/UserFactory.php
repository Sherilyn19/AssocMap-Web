<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * database/factories/UserFactory.php
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        $roleId = DB::table('roles')
            ->where('role_name', 'Field Officer')
            ->value('id');

        if (! $roleId) {
            throw new RuntimeException('Seed the roles table before using UserFactory.');
        }

        return [
            'name' => fake('en_PH')->name(),
            'email' => fake('en_PH')->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('Demo@12345'),
            'role_id' => (int) $roleId,
            'association_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function associationMember(int $associationId): static
    {
        $roleId = DB::table('roles')
            ->where('role_name', 'Association Member')
            ->value('id');

        if (! $roleId) {
            throw new RuntimeException('Association Member role is missing.');
        }

        return $this->state(fn (): array => [
            'role_id' => (int) $roleId,
            'association_id' => $associationId,
        ]);
    }
}
