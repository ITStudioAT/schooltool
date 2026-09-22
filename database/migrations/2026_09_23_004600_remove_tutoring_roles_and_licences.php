<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const RETIRED_ROLES = ['tutoring_user', 'tutoring_admin'];

    /** Permanently retire module assignments, preserving every shared account. */
    public function up(): void
    {
        DB::transaction(function (): void {
            $licenceIds = Schema::hasTable('licences')
                ? DB::table('licences')->where('name', 'Nachhilfetool')->get(['id', 'name'])
                    ->filter(fn (object $licence): bool => $licence->name === 'Nachhilfetool')->pluck('id')->all()
                : [];

            foreach (['licence_user_plans', 'school_user_licences', 'school_licences'] as $table) {
                if (Schema::hasTable($table) && $licenceIds !== []) {
                    DB::table($table)->whereIn('licence_id', $licenceIds)->delete();
                }
            }

            if ($licenceIds !== []) {
                DB::table('licences')->whereIn('id', $licenceIds)->delete();
            }

            foreach (['licence_user_plans', 'school_user_licences'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'role_name')) {
                    $ids = DB::table($table)->whereIn('role_name', self::RETIRED_ROLES)->get(['id', 'role_name'])
                        ->filter(fn (object $row): bool => in_array($row->role_name, self::RETIRED_ROLES, true))->pluck('id');
                    DB::table($table)->whereIn('id', $ids)->delete();
                }
            }

            $this->removeLicenceRoleReferences('licences', ['admin_role_names', 'user_role_names', 'licence_model']);
            $this->removeLicenceRoleReferences('school_licences', ['licence_model', 'user_licence_assignments']);

            $tables = config('permission.table_names');
            $roleTable = $tables['roles'];

            if (Schema::hasTable($roleTable)) {
                $roleIds = DB::table($roleTable)->whereIn('name', self::RETIRED_ROLES)->get(['id', 'name'])
                    ->filter(fn (object $role): bool => in_array($role->name, self::RETIRED_ROLES, true))->pluck('id');

                foreach (['model_has_roles', 'role_has_permissions'] as $pivot) {
                    if (Schema::hasTable($tables[$pivot])) {
                        DB::table($tables[$pivot])->whereIn('role_id', $roleIds)->delete();
                    }
                }

                DB::table($roleTable)->whereIn('id', $roleIds)->delete();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @param array<int, string> $columns */
    private function removeLicenceRoleReferences(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = array_values(array_intersect($columns, Schema::getColumnListing($table)));

        if ($columns === []) {
            return;
        }

        DB::table($table)->select(['id', ...$columns])->orderBy('id')->chunkById(200, function ($rows) use ($table, $columns): void {
            foreach ($rows as $row) {
                $updates = [];

                foreach ($columns as $column) {
                    $value = json_decode($row->{$column} ?? 'null');
                    $original = json_encode($value);

                    if (in_array($column, ['admin_role_names', 'user_role_names'], true)) {
                        $value = $this->removeRoleNames($value);
                    } elseif ($column === 'licence_model' && $value instanceof stdClass) {
                        foreach (['admin_role_names', 'user_role_names', 'affected_roles'] as $field) {
                            if (property_exists($value, $field)) {
                                $value->{$field} = $this->removeRoleNames($value->{$field});
                            }
                        }
                        foreach (['user_licence_required_by_role', 'user_licence_plans_by_role'] as $field) {
                            if (property_exists($value, $field)) {
                                $value->{$field} = $this->removeRoleKeys($value->{$field});
                            }
                        }
                    } elseif ($column === 'user_licence_assignments' && (is_array($value) || $value instanceof stdClass)) {
                        foreach ($value as $userId => $assignments) {
                            if (is_array($value)) {
                                $value[$userId] = $this->removeRoleKeys($assignments);
                            } else {
                                $value->{$userId} = $this->removeRoleKeys($assignments);
                            }
                        }
                    }

                    if (json_encode($value) !== $original) {
                        $updates[$column] = json_encode($value, JSON_THROW_ON_ERROR);
                    }
                }

                if ($updates !== []) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            }
        });
    }

    private function removeRoleNames(mixed $value): mixed
    {
        return is_array($value)
            ? array_values(array_filter($value, fn (mixed $role): bool => ! in_array($role, self::RETIRED_ROLES, true)))
            : $value;
    }

    private function removeRoleKeys(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            foreach (self::RETIRED_ROLES as $role) {
                unset($value->{$role});
            }
        }

        return $value;
    }
};
