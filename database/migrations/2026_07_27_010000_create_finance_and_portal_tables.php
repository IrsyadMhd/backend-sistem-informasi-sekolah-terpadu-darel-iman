<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');
        }

        // 1. Fee Categories (Kategori Biaya / SPP / Uang Pangkal)
        Schema::create('fee_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->boolean('is_recurring')->default(true);
            $table->decimal('default_amount', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();
        });

        // 2. Student Bills (Tagihan Siswa)
        Schema::create('student_bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('fee_category_id');
            $table->uuid('academic_year_id');
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->string('status', 20)->default('UNPAID'); // UNPAID, PARTIAL, PAID, CANCELLED
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            // Indexes & Foreign Keys
            $table->index(['student_id', 'status']);
            $table->index('due_date');
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $table->foreign('fee_category_id')->references('id')->on('fee_categories')->restrictOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
        });

        // 3. Bill Payments (Pembayaran Tagihan / Kwitansi)
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bill_id');
            $table->string('invoice_number', 100)->unique();
            $table->string('payment_method', 50)->default('CASH'); // CASH, TRANSFER, MIDTRANS_VA, QRIS
            $table->decimal('paid_amount', 12, 2);
            $table->timestampTz('paid_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->jsonb('payment_gateway_payload')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            // Indexes & Foreign Keys
            $table->index('bill_id');
            $table->foreign('bill_id')->references('id')->on('student_bills')->restrictOnDelete();
        });

        // 4. Notifications (Portal Broadcast & Alerting)
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('type', 50)->default('general');
                $table->string('title');
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->timestampTz('read_at')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestampsTz();

                // Indexes & Foreign Keys
                $table->index(['user_id', 'is_read']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('bill_payments');
        Schema::dropIfExists('student_bills');
        Schema::dropIfExists('fee_categories');
    }
};
