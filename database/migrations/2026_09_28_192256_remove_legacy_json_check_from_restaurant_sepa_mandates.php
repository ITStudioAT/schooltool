<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
            || ! Schema::hasColumn('restaurant_sepa_mandates', 'child_entries')
            || ! in_array(Schema::getColumnType('restaurant_sepa_mandates', 'child_entries'), ['text', 'mediumtext', 'longtext'], true)) {
            return;
        }
        $checks = DB::table('information_schema.TABLE_CONSTRAINTS as tables')
            ->join('information_schema.CHECK_CONSTRAINTS as checks', function ($join): void {
                $join->on('checks.CONSTRAINT_SCHEMA', '=', 'tables.CONSTRAINT_SCHEMA')
                    ->on('checks.CONSTRAINT_NAME', '=', 'tables.CONSTRAINT_NAME');
            })
            ->where('tables.TABLE_SCHEMA', $connection->getDatabaseName())
            ->where('tables.TABLE_NAME', 'restaurant_sepa_mandates')
            ->where('tables.CONSTRAINT_TYPE', 'CHECK')
            ->get(['tables.CONSTRAINT_NAME', 'checks.CHECK_CLAUSE']);
        foreach ($checks as $check) {
            if (! preg_match('/\Ajson_valid\(`?child_entries`?\)\z/i', preg_replace('/\s+/', '', $check->CHECK_CLAUSE))) {
                continue;
            }
            $name = $connection->getSchemaGrammar()->wrap($check->CONSTRAINT_NAME);
            $drop = $connection->isMaria() ? 'DROP CONSTRAINT' : 'DROP CHECK';
            DB::statement("ALTER TABLE `restaurant_sepa_mandates` {$drop} {$name}");
        }
        // An inverse JSON check would reject encrypted mandates; leave the repaired TEXT schema intact.
    }
};
