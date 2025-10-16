<?php

// app/Models/Role.php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Role extends SpatieRole
{

    public function users(): MorphToMany
    {
        // 'model' ist der Morph-Name in Spaties Tabelle model_has_roles
        return $this->morphedByMany(User::class, 'model', 'model_has_roles', 'role_id', 'model_id');
    }

    public function shouldDelete(): bool
    {

        // Irgend ein User hat die Rolle noch zugeordnet
        if ($this->users()->exists()) {
            return false;
        }

        if ($this->name == 'super_admin') {
            return false;
        }

        $this->delete();

        return true;
    }
}
