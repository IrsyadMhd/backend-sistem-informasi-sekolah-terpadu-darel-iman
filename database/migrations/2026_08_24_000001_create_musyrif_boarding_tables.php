<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Log Tasmi' / Ujian Tahfizh Sekali Duduk
        if (! Schema::hasTable('tahfizh_exams')) {
            Schema::create('tahfizh_exams', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('student_id');
                $table->uuid('examiner_id')->nullable(); // Musyrif / Penguji
                $table->string('exam_type')->default('tasmi_1_juz'); // tasmi_1_juz, tasmi_5_juz, tasmi_10_juz, tasmi_30_juz, ujian_tajwid
                $table->integer('juz_number')->nullable();
                $table->enum('tajwid_grade', ['A', 'B', 'C', 'D'])->default('A');
                $table->enum('makhraj_grade', ['A', 'B', 'C', 'D'])->default('A');
                $table->decimal('final_score', 5, 2)->default(100.00);
                $table->string('certificate_path')->nullable();
                $table->text('notes')->nullable();
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            });
        }

        // 2. Master Kategori Pelanggaran & Prestasi Kedisiplinan
        if (! Schema::hasTable('discipline_categories')) {
            Schema::create('discipline_categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->enum('type', ['violation', 'achievement'])->default('violation'); // Sanksi / Prestasi
                $table->enum('level', ['ringan', 'sedang', 'berat'])->nullable();
                $table->string('code')->unique();
                $table->string('name');
                $table->integer('point_weight')->default(0); // Minus (e.g., -10) atau Plus (e.g., +15)
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. Ledger Transaksi Poin Kedisiplinan Siswa
        if (! Schema::hasTable('student_point_transactions')) {
            Schema::create('student_point_transactions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('student_id');
                $table->uuid('category_id')->nullable();
                $table->uuid('reported_by_id')->nullable(); // Musyrif / Guru
                $table->integer('points')->default(0);
                $table->date('transaction_date');
                $table->text('description')->nullable();
                $table->enum('status', ['dalam_pengawasan', 'selesai_sanksi', 'dirujuk_bk', 'tercatat'])->default('tercatat');
                $table->timestamps();

                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
                $table->foreign('category_id')->references('id')->on('discipline_categories')->onDelete('set null');
            });
        }

        // 4. Klinik Sekolah & Log Kesehatan Santri
        if (! Schema::hasTable('clinic_logs')) {
            Schema::create('clinic_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('student_id');
                $table->uuid('handled_by_musyrif_id')->nullable();
                $table->dateTime('symptom_start_at')->nullable();
                $table->text('symptoms');
                $table->string('medicine_given')->nullable();
                $table->text('rest_recommendation')->nullable();
                $table->enum('status', ['rawat_jalan', 'istirahat_uksh', 'dirujuk_rs', 'sembuh'])->default('rawat_jalan');
                $table->timestamps();

                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            });
        }

        // 5. Riwayat Kesehatan / Penyakit Bawaan & Alergi
        if (! Schema::hasTable('student_medical_histories')) {
            Schema::create('student_medical_histories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('student_id');
                $table->text('congenital_diseases')->nullable(); // Penyakit bawaan
                $table->text('allergies')->nullable(); // Alergi
                $table->string('emergency_contact_phone')->nullable();
                $table->text('special_instructions')->nullable();
                $table->timestamps();

                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            });
        }

        // 6. Log Penitipan Barang Berharga Asrama (HP / Laptop)
        if (! Schema::hasTable('dormitory_deposits')) {
            Schema::create('dormitory_deposits', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('student_id');
                $table->uuid('musyrif_id')->nullable();
                $table->enum('item_type', ['smartphone', 'laptop', 'tablet', 'elektronik_lain'])->default('smartphone');
                $table->string('item_name');
                $table->string('serial_number')->nullable();
                $table->dateTime('deposited_at');
                $table->dateTime('retrieved_at')->nullable();
                $table->enum('status', ['deposited', 'retrieved'])->default('deposited');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dormitory_deposits');
        Schema::dropIfExists('student_medical_histories');
        Schema::dropIfExists('clinic_logs');
        Schema::dropIfExists('student_point_transactions');
        Schema::dropIfExists('discipline_categories');
        Schema::dropIfExists('tahfizh_exams');
    }
};
