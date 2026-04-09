<?php

namespace App\Policies;

use App\Models\Coordinate;
use App\Models\User;

class CoordinatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('coordinates.manage');
    }

    public function view(User $user, Coordinate $coordinate): bool
    {
        return $user->hasPermission('coordinates.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('coordinates.manage');
    }

    public function update(User $user, Coordinate $coordinate): bool
    {
        return $user->hasPermission('coordinates.manage');
    }

    public function delete(User $user, Coordinate $coordinate): bool
    {
        return $user->hasPermission('coordinates.manage');
    }
}
