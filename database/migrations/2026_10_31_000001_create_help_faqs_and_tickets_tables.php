<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('help_faqs')) {
            Schema::create('help_faqs', function (Blueprint $table) {
                if (DB::getDriverName() === 'pgsql') {
                    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                } else {
                    $table->uuid('id')->primary();
                }
                $table->string('category')->index();
                $table->string('question');
                $table->text('answer');
                $table->json('tags')->nullable();
                $table->string('action_url')->nullable();
                $table->string('action_label')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->integer('helpful_votes')->default(0);
                $table->integer('unhelpful_votes')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('help_tickets')) {
            Schema::create('help_tickets', function (Blueprint $table) {
                if (DB::getDriverName() === 'pgsql') {
                    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                } else {
                    $table->uuid('id')->primary();
                }
                $table->string('ticket_number')->unique();
                $table->uuid('user_id')->nullable()->index();
                $table->string('name');
                $table->string('email');
                $table->string('phone')->nullable();
                $table->string('role')->nullable();
                $table->string('category')->index();
                $table->string('priority')->default('Sedang');
                $table->string('subject');
                $table->text('description');
                $table->string('status')->default('open')->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_tickets');
        Schema::dropIfExists('help_faqs');
    }
};
