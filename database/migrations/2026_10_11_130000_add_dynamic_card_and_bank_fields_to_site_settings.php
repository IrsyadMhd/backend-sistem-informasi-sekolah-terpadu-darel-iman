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
        Schema::table('site_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('site_settings', 'website')) {
                $table->string('website', 255)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('site_settings', 'email')) {
                $table->string('email', 255)->nullable()->after('website');
            }
            if (!Schema::hasColumn('site_settings', 'bank_name')) {
                $table->string('bank_name', 100)->nullable()->after('email');
            }
            if (!Schema::hasColumn('site_settings', 'bank_account_number')) {
                $table->string('bank_account_number', 50)->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('site_settings', 'bank_account_name')) {
                $table->string('bank_account_name', 150)->nullable()->after('bank_account_number');
            }
            if (!Schema::hasColumn('site_settings', 'card_rules_student')) {
                $table->text('card_rules_student')->nullable()->after('bank_account_name');
            }
            if (!Schema::hasColumn('site_settings', 'card_rules_employee')) {
                $table->text('card_rules_employee')->nullable()->after('card_rules_student');
            }
            if (!Schema::hasColumn('site_settings', 'card_footer_motto')) {
                $table->string('card_footer_motto', 255)->nullable()->after('card_rules_employee');
            }
        });

        // Initialize default operational values on existing settings record
        DB::table('site_settings')->where('id', 1)->update([
            'address' => DB::raw("COALESCE(address, 'Jl. Gajah Mada No. 28 Padang, Sumatera Barat')"),
            'phone' => DB::raw("COALESCE(phone, '(0751) 123456')"),
            'motto' => DB::raw("COALESCE(motto, 'Berilmu, Berakhlak, Beramal')"),
            'website' => 'dareliman.or.id',
            'email' => 'sekretariat@dareliman.or.id',
            'bank_name' => 'Bank Syariah Indonesia (BSI)',
            'bank_account_number' => '777-1234-567',
            'bank_account_name' => 'Yayasan Dar El-Iman Padang',
            'card_footer_motto' => "Generasi Beriman, Berilmu,\nBerakhlak Mulia",
            'card_rules_employee' => "1. Kartu ini adalah milik resmi Yayasan Dar el-Iman.\n2. Wajib dibawa & dikenakan selama jam kerja.\n3. Apabila menemukan kartu ini, harap mengembalikan ke kantor yayasan.\n4. QR Code digunakan untuk absensi & verifikasi SIMSIT.",
            'card_rules_student' => "1. Kartu ini adalah kartu identitas resmi siswa Yayasan Dar el-Iman.\n2. Wajib dibawa & dikenakan selama jam KBM sekolah.\n3. Apabila menemukan kartu ini, harap mengembalikan ke piket sekolah.\n4. QR Code digunakan untuk absensi gerbang & verifikasi SIMSIT.",
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $columns = [
                'website',
                'email',
                'bank_name',
                'bank_account_number',
                'bank_account_name',
                'card_rules_student',
                'card_rules_employee',
                'card_footer_motto',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('site_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
