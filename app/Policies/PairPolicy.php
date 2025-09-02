<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Pair;

class PairPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Anyone can view pairs
    }

    public function view(User $user, Pair $pair): bool
    {
        return true; // Anyone can view pairs
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Pair $pair): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, Pair $pair): bool
    {
        return $user->is_admin;
    }
}

