<?php

namespace App\Services\Materials;

use App\Models\MaterialWorkspace;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MaterialWorkspaceService
{
    public function resolveActiveWorkspace(User $user): MaterialWorkspace
    {
        $existingDefault = MaterialWorkspace::query()
            ->where('user_id', (int) $user->id)
            ->where('is_default', true)
            ->orderBy('id')
            ->first();

        if ($existingDefault instanceof MaterialWorkspace) {
            return $existingDefault;
        }

        return DB::transaction(function () use ($user): MaterialWorkspace {
            $workspace = MaterialWorkspace::query()
                ->where('user_id', (int) $user->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $workspace instanceof MaterialWorkspace) {
                return MaterialWorkspace::query()->create([
                    'user_id' => (int) $user->id,
                    'name' => 'Workspace',
                    'is_default' => true,
                ]);
            }

            MaterialWorkspace::query()
                ->where('user_id', (int) $user->id)
                ->where('id', '<>', (int) $workspace->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $workspace->is_default = true;
            $workspace->save();

            return $workspace->fresh();
        });
    }
}
