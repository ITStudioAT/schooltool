<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import116', function (Blueprint $table) {
            $table->unique(
                ['school_id', 'schoolyear_id', 'student_code'],
                'import116_schoolyear_student_unique'
            );
        });
    }
};
