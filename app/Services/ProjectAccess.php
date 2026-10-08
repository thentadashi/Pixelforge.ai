<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProjectAccess
{
    public static function find(User $user, int $id): object
    {
        $project = DB::table('projects')->where('id', $id)->when(! $user->isAdmin(), fn ($query) => $query->where('client_id', $user->id))->first();
        abort_unless($project, 404);

        return $project;
    }
}
