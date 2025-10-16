<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\IndexRoleRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Http\Resources\Admin\RoleResource;
use App\Traits\PaginationTrait;

class SpaRoleController extends Controller
{
    use PaginationTrait;

    public function index(IndexRoleRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        $search_model = $validated['search_model'] ?? [];
        $query = Role::whereNotIn('name', ['super_admin'])->orderBy('name');

        // search_string (optional)
        if (! empty($search_model['search_string'])) {
            $search = $search_model['search_string'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $pagination = RoleResource::collection($query->paginate(config('spa.pagination')));
        return response()->json($this->makePagination($pagination), 200);
    }

    public function store(StoreRoleRequest $request)
    {

        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        $validated['guard_name'] = 'web';

        $role = Role::create($validated);

        return response()->json(new RoleResource($role), 200);
    }

    public function show(Role $role)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return response()->json(new RoleResource($role), 200);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        if (! $auth_user = $this->userHasRole(['super_admin']) || $role->name == 'super_admin') {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        $role->update($validated);

        return response()->json(new RoleResource($role), 200);
    }

    public function destroy(Role $role)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $role->shouldDelete()) {
            abort(403, 'Beim Löschen ist ein Fehler aufgetreten. Möglicherweise gibt es abhängige Daten.');
        }

        return response()->noContent();
    }

    public function destroyMultiple(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $ids = $request->all();

        Role::whereIn('id', $ids)->each(function ($item) {
            if (! $item->shouldDelete()) {
                abort(403, 'Beim Löschen ist ein Fehler aufgetreten. Möglicherweise gibt es abhängige Daten.');
            }
        });

        return response()->noContent();
    }
}
