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
        // 1. Perkaya tabel discipline_categories untuk mendukung Buku Saku Tata Tertib Pesantren/Sekolah
        if (Schema::hasTable('discipline_categories')) {
            Schema::table('discipline_categories', function (Blueprint $table) {
                if (! Schema::hasColumn('discipline_categories', 'cluster')) {
                    $table->string('cluster')->default('peraturan_umum')->after('type'); // peraturan_umum, kelas_halaqoh, asrama, masjid
                }
                if (! Schema::hasColumn('discipline_categories', 'pasal_number')) {
                    $table->string('pasal_number')->nullable()->after('cluster');
                }
                if (! Schema::hasColumn('discipline_categories', 'rule_name')) {
                    $table->string('rule_name')->nullable()->after('pasal_number'); // e.g. "Santri wajib menjalankan ibadah..."
                }
                if (! Schema::hasColumn('discipline_categories', 'violation_detail')) {
                    $table->text('violation_detail')->nullable()->after('name'); // e.g. "Melukai dengan sengaja"
                }
                if (! Schema::hasColumn('discipline_categories', 'direct_consequence')) {
                    $table->string('direct_consequence')->nullable()->after('point_weight'); // e.g. "Pakaian disita", "Diberdirikan"
                }
                if (! Schema::hasColumn('discipline_categories', 'is_permanent')) {
                    $table->boolean('is_permanent')->default(false)->after('direct_consequence'); // PERMANENT points
                }
                if (! Schema::hasColumn('discipline_categories', 'target_unit_type')) {
                    $table->string('target_unit_type')->default('all')->after('is_permanent'); // all, boarding, fullday
                }
                if (! Schema::hasColumn('discipline_categories', 'education_unit_id')) {
                    $table->uuid('education_unit_id')->nullable()->after('target_unit_type');
                    $table->foreign('education_unit_id')->references('id')->on('education_units')->onDelete('set null');
                }
            });
        }

        // 2. Master Ambang Batas Poin Sanksi & Surat Peringatan (Tabel Point 1 - 250)
        if (! Schema::hasTable('discipline_threshold_rules')) {
            Schema::create('discipline_threshold_rules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->integer('stage_order')->default(1);
                $table->integer('min_points')->default(0);
                $table->integer('max_points')->nullable(); // null for 250+
                $table->string('sanction_name'); // e.g. "Sanksi Ringan", "Surat Teguran (ST)", "SP1", "SP2", "Dikeluarkan"
                $table->text('sanction_details')->nullable();
                $table->text('notes')->nullable();
                $table->string('letter_type')->nullable(); // st, sp1, sp2, keluar
                $table->boolean('requires_parent_call')->default(false);
                $table->boolean('is_expulsion')->default(false);
                $table->string('target_unit_type')->default('all'); // all, boarding, fullday
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. Perkaya Ledger Transaksi Poin Siswa (student_point_transactions)
        if (Schema::hasTable('student_point_transactions')) {
            Schema::table('student_point_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('student_point_transactions', 'academic_year_id')) {
                    $table->uuid('academic_year_id')->nullable()->after('student_id');
                    $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('set null');
                }
                if (! Schema::hasColumn('student_point_transactions', 'semester_id')) {
                    $table->uuid('semester_id')->nullable()->after('academic_year_id');
                    $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('set null');
                }
                if (! Schema::hasColumn('student_point_transactions', 'recorder_role')) {
                    $table->string('recorder_role')->nullable()->after('reported_by_id'); // Musyrif, Wali Kelas, Guru Mapel, Kepsek, Divisi Pendidikan
                }
                if (! Schema::hasColumn('student_point_transactions', 'incident_location')) {
                    $table->string('incident_location')->nullable()->after('transaction_date'); // kelas, asrama, masjid, lingkungan_pondok, luar_pondok
                }
                if (! Schema::hasColumn('student_point_transactions', 'direct_consequence_notes')) {
                    $table->text('direct_consequence_notes')->nullable()->after('description');
                }
                if (! Schema::hasColumn('student_point_transactions', 'evidence_path')) {
                    $table->string('evidence_path')->nullable()->after('direct_consequence_notes');
                }
            });
        }

        // 4. Log Surat Peringatan & Pemanggilan Orang Tua
        if (! Schema::hasTable('student_warning_letters')) {
            Schema::create('student_warning_letters', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('letter_number')->unique();
                $table->uuid('student_id');
                $table->uuid('academic_year_id')->nullable();
                $table->uuid('semester_id')->nullable();
                $table->enum('letter_type', ['st', 'sp1', 'sp2', 'keluar'])->default('st');
                $table->integer('cumulative_points')->default(0);
                $table->date('issue_date');
                $table->dateTime('parent_summon_date')->nullable();
                $table->enum('parent_summon_status', ['menunggu_kehadiran', 'hadir', 'tidak_hadir', 'selesai_konseling'])->default('menunggu_kehadiran');
                $table->text('notes')->nullable();
                $table->text('parent_response_notes')->nullable();
                $table->uuid('issued_by_id')->nullable();
                $table->string('signed_by_role')->nullable(); // Waka Kesantrian, Kepala Sekolah, Divisi Pendidikan
                $table->string('pdf_path')->nullable();
                $table->timestamps();

                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
                $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('set null');
                $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('set null');
                $table->foreign('issued_by_id')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_warning_letters');
        Schema::dropIfExists('discipline_threshold_rules');

        if (Schema::hasTable('student_point_transactions')) {
            Schema::table('student_point_transactions', function (Blueprint $table) {
                $table->dropForeign(['academic_year_id']);
                $table->dropForeign(['semester_id']);
                $table->dropColumn([
                    'academic_year_id',
                    'semester_id',
                    'recorder_role',
                    'incident_location',
                    'direct_consequence_notes',
                    'evidence_path',
                ]);
            });
        }

        if (Schema::hasTable('discipline_categories')) {
            Schema::table('discipline_categories', function (Blueprint $table) {
                $table->dropForeign(['education_unit_id']);
                $table->dropColumn([
                    'cluster',
                    'pasal_number',
                    'rule_name',
                    'violation_detail',
                    'direct_consequence',
                    'is_permanent',
                    'target_unit_type',
                    'education_unit_id',
                ]);
            });
        }
    }
};
