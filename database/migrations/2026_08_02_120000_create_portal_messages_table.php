<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_messages')) {
            return;
        }

        Schema::create('portal_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('sender_user_id');
            $table->uuid('recipient_user_id');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
            $table->index(['recipient_user_id', 'read_at']);
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('sender_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('recipient_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_messages');
    }
};
