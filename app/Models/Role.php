<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * app/Models/Role.php
 * Lookup table for the 3 authenticated roles: System Administrator,
 * Field Officer, Association Member. Public User has no row/account.
 */
class Role extends Model
{
    protected $fillable = ['role_name'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}