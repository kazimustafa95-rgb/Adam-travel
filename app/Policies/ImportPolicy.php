<?php

namespace App\Policies;

use App\Models\Import;
use App\Models\User;

class ImportPolicy
{
    public function view(User $user, Import $import): bool
    {
        return $import->user_id === $user->id;
    }

    public function update(User $user, Import $import): bool
    {
        return $import->user_id === $user->id;
    }
}
