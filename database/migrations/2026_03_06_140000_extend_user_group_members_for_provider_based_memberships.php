<?php

use App\Models\UserGroupMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LINKED_USER_INDEX = 'ugm_linked_user_idx';

    private const SCHOOL_PROVIDER_INDEX = 'ugm_school_provider_idx';

    private const MEMBER_UNIQUE_INDEX = 'ugm_group_provider_ref_uniq';

    public function up(): void
    {
        Schema::table('user_group_members', function (Blueprint $table) {
            if ($this->hasIndex('user_group_members', 'user_group_members_user_group_id_index') === false) {
                $table->index('user_group_id');
            }
            if (Schema::hasColumn('user_group_members', 'user_id') && $this->hasForeignKey('user_group_members', 'user_group_members_user_id_foreign')) {
                $table->dropForeign(['user_id']);
            }
            if (Schema::hasColumn('user_group_members', 'added_by_user_id') && $this->hasForeignKey('user_group_members', 'user_group_members_added_by_user_id_foreign')) {
                $table->dropForeign(['added_by_user_id']);
            }
            if ($this->hasIndex('user_group_members', 'user_group_members_user_group_id_user_id_unique')) {
                $table->dropUnique('user_group_members_user_group_id_user_id_unique');
            }
            if ($this->hasIndex('user_group_members', 'user_group_members_user_id_index')) {
                $table->dropIndex('user_group_members_user_id_index');
            }
        });

        Schema::table('user_group_members', function (Blueprint $table) {
            if (! Schema::hasColumn('user_group_members', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('user_group_id')->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('user_group_members', 'linked_user_id')) {
                $table->unsignedBigInteger('linked_user_id')->nullable()->after('school_id');
            }
            if (! Schema::hasColumn('user_group_members', 'member_provider')) {
                $table->string('member_provider', 64)->nullable()->after('linked_user_id');
            }
            if (! Schema::hasColumn('user_group_members', 'member_ref')) {
                $table->string('member_ref', 191)->nullable()->after('member_provider');
            }
            if (! Schema::hasColumn('user_group_members', 'source_schoolyear_id')) {
                $table->unsignedBigInteger('source_schoolyear_id')->nullable()->after('member_ref');
            }
            if (! Schema::hasColumn('user_group_members', 'display_name')) {
                $table->string('display_name')->nullable()->after('source_schoolyear_id');
            }
            if (! Schema::hasColumn('user_group_members', 'display_email')) {
                $table->string('display_email')->nullable()->after('display_name');
            }
            if (! Schema::hasColumn('user_group_members', 'display_phone')) {
                $table->string('display_phone')->nullable()->after('display_email');
            }
            if (! Schema::hasColumn('user_group_members', 'display_schoolclass')) {
                $table->string('display_schoolclass')->nullable()->after('display_phone');
            }
            if (! Schema::hasColumn('user_group_members', 'display_children_label')) {
                $table->string('display_children_label')->nullable()->after('display_schoolclass');
            }
            if (! Schema::hasColumn('user_group_members', 'member_type_label')) {
                $table->string('member_type_label', 100)->nullable()->after('display_children_label');
            }
            if (! Schema::hasColumn('user_group_members', 'source_status')) {
                $table->string('source_status', 32)->nullable()->after('member_type_label');
            }
            if (! Schema::hasColumn('user_group_members', 'linked_user_status')) {
                $table->string('linked_user_status', 32)->nullable()->after('source_status');
            }
            if (! Schema::hasColumn('user_group_members', 'meta')) {
                $table->json('meta')->nullable()->after('linked_user_status');
            }
        });

        if (Schema::hasColumn('user_group_members', 'user_id')) {
            DB::table('user_group_members')->update([
                'linked_user_id' => DB::raw('user_id'),
            ]);
        }

        DB::table('user_group_members')
            ->join('user_groups', 'user_groups.id', '=', 'user_group_members.user_group_id')
            ->leftJoin('users as linked_users', 'linked_users.id', '=', 'user_group_members.linked_user_id')
            ->update([
                'user_group_members.school_id' => DB::raw('user_groups.school_id'),
                'user_group_members.member_provider' => DB::raw("'".addslashes(UserGroupMember::PROVIDER_USER)."'"),
                'user_group_members.member_ref' => DB::raw("CONCAT('user:', COALESCE(user_group_members.linked_user_id, 0))"),
                'user_group_members.display_name' => DB::raw("TRIM(CONCAT(COALESCE(linked_users.last_name, ''), ' ', COALESCE(linked_users.first_name, '')))"),
                'user_group_members.display_email' => DB::raw('linked_users.email'),
                'user_group_members.display_schoolclass' => DB::raw('linked_users.schoolclass'),
                'user_group_members.member_type_label' => DB::raw("'Benutzer'"),
                'user_group_members.source_status' => DB::raw("'".addslashes(UserGroupMember::SOURCE_STATUS_ACTIVE)."'"),
                'user_group_members.linked_user_status' => DB::raw("CASE WHEN user_group_members.linked_user_id IS NULL THEN '".addslashes(UserGroupMember::LINKED_USER_STATUS_NOT_APPLICABLE)."' ELSE '".addslashes(UserGroupMember::LINKED_USER_STATUS_LINKED)."' END"),
            ]);

        DB::table('user_group_members')
            ->where(function ($query) {
                $query->whereNull('display_name')->orWhere('display_name', '');
            })
            ->update([
                'display_name' => DB::raw("COALESCE(display_email, 'Benutzer')"),
            ]);

        Schema::table('user_group_members', function (Blueprint $table) {
            if (Schema::hasColumn('user_group_members', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });

        Schema::table('user_group_members', function (Blueprint $table) {
            if (Schema::hasColumn('user_group_members', 'added_by_user_id')) {
                $table->foreign('added_by_user_id')->references('id')->on('users')->nullOnDelete();
            }
            if ($this->hasIndex('user_group_members', self::LINKED_USER_INDEX) === false) {
                $table->index('linked_user_id', self::LINKED_USER_INDEX);
            }
            if ($this->hasIndex('user_group_members', self::SCHOOL_PROVIDER_INDEX) === false) {
                $table->index(['school_id', 'member_provider'], self::SCHOOL_PROVIDER_INDEX);
            }
            if ($this->hasIndex('user_group_members', self::MEMBER_UNIQUE_INDEX) === false) {
                $table->unique(['user_group_id', 'member_provider', 'member_ref'], self::MEMBER_UNIQUE_INDEX);
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_group_members', function (Blueprint $table) {
            if ($this->hasIndex('user_group_members', self::MEMBER_UNIQUE_INDEX)) {
                $table->dropUnique(self::MEMBER_UNIQUE_INDEX);
            }
            if ($this->hasIndex('user_group_members', self::SCHOOL_PROVIDER_INDEX)) {
                $table->dropIndex(self::SCHOOL_PROVIDER_INDEX);
            }
            if ($this->hasIndex('user_group_members', self::LINKED_USER_INDEX)) {
                $table->dropIndex(self::LINKED_USER_INDEX);
            }
            if ($this->hasIndex('user_group_members', 'user_group_members_user_group_id_index')) {
                $table->dropIndex('user_group_members_user_group_id_index');
            }
        });

        Schema::table('user_group_members', function (Blueprint $table) {
            if (! Schema::hasColumn('user_group_members', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('user_group_id');
            }
        });

        DB::table('user_group_members')->update([
            'user_id' => DB::raw('linked_user_id'),
        ]);

        Schema::table('user_group_members', function (Blueprint $table) {
            if (Schema::hasColumn('user_group_members', 'school_id')) {
                $table->dropConstrainedForeignId('school_id');
            }
            foreach ([
                'linked_user_id',
                'member_provider',
                'member_ref',
                'source_schoolyear_id',
                'display_name',
                'display_email',
                'display_phone',
                'display_schoolclass',
                'display_children_label',
                'member_type_label',
                'source_status',
                'linked_user_status',
                'meta',
            ] as $column) {
                if (Schema::hasColumn('user_group_members', $column)) {
                    $table->dropColumn($column);
                }
            }
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_group_id', 'user_id']);
            $table->index('user_id');
            if (Schema::hasColumn('user_group_members', 'added_by_user_id')) {
                if ($this->hasForeignKey('user_group_members', 'user_group_members_added_by_user_id_foreign')) {
                    $table->dropForeign(['added_by_user_id']);
                }
                $table->foreign('added_by_user_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function hasForeignKey(string $table, string $constraint): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.table_constraints')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
