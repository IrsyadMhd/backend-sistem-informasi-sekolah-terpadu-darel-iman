<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_notes') && Schema::hasColumn('student_notes', 'teacher_id')) {
            Schema::table('student_notes', function (Blueprint $table) {
                $table->uuid('teacher_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Keep nullable for non-teacher recorders (Kepsek, Musyrif, Waka Kesiswaan, Divisi Pendidikan).
    }
};

