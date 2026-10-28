<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\FeeCategory;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentBillingSeeder extends Seeder
{
    public function run(): void
    {
        $activeAy = AcademicYear::where('is_active', true)->first()
            ?? AcademicYear::first();

        if (! $activeAy) {
            $this->command?->error('Academic Year tidak ditemukan!');
            return;
        }

        // 1. Buat / Ambil Kategori Biaya Resmi
        $feeCategories = [
            'SPP' => FeeCategory::firstOrCreate(
                ['code' => 'SPP'],
                [
                    'name' => 'SPP Bulanan',
                    'is_recurring' => true,
                    'default_amount' => 500000,
                    'description' => 'Iuran Pembinaan Pendidikan (SPP) Bulanan',
                ]
            ),
            'GEDUNG' => FeeCategory::firstOrCreate(
                ['code' => 'GEDUNG'],
                [
                    'name' => 'Infaq Pengembangan Gedung & Fasilitas',
                    'is_recurring' => false,
                    'default_amount' => 2500000,
                    'description' => 'Dana pengembangan sarana, prasarana, dan fasilitas santri',
                ]
            ),
            'KEGIATAN' => FeeCategory::firstOrCreate(
                ['code' => 'KEGIATAN'],
                [
                    'name' => 'Biaya Modul & Kegiatan Belajar',
                    'is_recurring' => false,
                    'default_amount' => 750000,
                    'description' => 'Buku materi ajar, modul digital, dan praktikum kurikulum',
                ]
            ),
            'SERAGAM' => FeeCategory::firstOrCreate(
                ['code' => 'SERAGAM'],
                [
                    'name' => 'Perlengkapan Seragam & Atribut Santri',
                    'is_recurring' => false,
                    'default_amount' => 600000,
                    'description' => 'Paket seragam sekolah, batik khas, dan atribut yayasan',
                ]
            ),
        ];

        // 2. Ambil seluruh siswa aktif
        $students = Student::with('educationUnit')->where('is_active', true)->get();

        if ($students->isEmpty()) {
            $students = Student::with('educationUnit')->take(100)->get();
        }

        $this->command?->info("Menghapus data tagihan lama & menyusun data baru untuk {$students->count()} siswa...");

        DB::table('bill_payments')->delete();
        DB::table('student_bills')->delete();

        $invoiceCounter = 100001;

        $months = [
            ['name' => 'Juli 2026', 'due' => '2026-07-10', 'paid' => true, 'pay_date' => '2026-07-06 09:30:00'],
            ['name' => 'Agustus 2026', 'due' => '2026-08-10', 'paid' => true, 'pay_date' => '2026-08-08 14:15:00'],
            ['name' => 'September 2026', 'due' => '2026-09-10', 'paid' => true, 'pay_date' => '2026-09-05 10:45:00'],
            ['name' => 'Oktober 2026', 'due' => '2026-10-10', 'paid' => false, 'pay_date' => null],
            ['name' => 'November 2026', 'due' => '2026-11-10', 'paid' => false, 'pay_date' => null],
            ['name' => 'Desember 2026', 'due' => '2026-12-10', 'paid' => false, 'pay_date' => null],
        ];

        $billsBatch = [];
        $paymentsBatch = [];
        $now = now();

        foreach ($students as $student) {
            $unitLevel = strtoupper((string) ($student->educationUnit?->level ?? 'SDIT'));
            $isPonpes = str_contains($unitLevel, 'MAHAD') || str_contains($unitLevel, 'PONPES') || str_contains($unitLevel, 'PESANTREN');

            $sppAmount = match (true) {
                $isPonpes => 1250000,
                str_contains($unitLevel, 'SMA') => 750000,
                str_contains($unitLevel, 'SMP') => 650000,
                str_contains($unitLevel, 'TK') || str_contains($unitLevel, 'PAUD') => 350000,
                default => 500000,
            };

            $cleanNis = preg_replace('/\D/', '', (string) ($student->nis ?? '000000'));
            if (empty($cleanNis)) {
                $cleanNis = '20260' . rand(1000, 9999);
            }
            $vaBsi = '9023' . $cleanNis;

            // 1. Tagihan Awal Masuk (Juli 2026)
            $oneTimeBills = [
                [
                    'cat' => 'GEDUNG',
                    'title' => 'Infaq Pengembangan Gedung & Fasilitas TA 2026/2027',
                    'amount' => 2500000,
                    'due' => '2026-07-20',
                    'paid' => true,
                    'pay_date' => '2026-07-02 11:20:00',
                ],
                [
                    'cat' => 'KEGIATAN',
                    'title' => 'Biaya Modul & Kegiatan Belajar TA 2026/2027',
                    'amount' => 750000,
                    'due' => '2026-07-20',
                    'paid' => true,
                    'pay_date' => '2026-07-03 13:40:00',
                ],
                [
                    'cat' => 'SERAGAM',
                    'title' => 'Perlengkapan Seragam & Atribut Santri TA 2026/2027',
                    'amount' => 600000,
                    'due' => '2026-07-20',
                    'paid' => true,
                    'pay_date' => '2026-07-04 15:10:00',
                ],
            ];

            foreach ($oneTimeBills as $ot) {
                $billId = (string) Str::uuid();
                $billsBatch[] = [
                    'id' => $billId,
                    'student_id' => $student->id,
                    'fee_category_id' => $feeCategories[$ot['cat']]->id,
                    'academic_year_id' => $activeAy->id,
                    'title' => $ot['title'],
                    'amount' => $ot['amount'],
                    'due_date' => $ot['due'],
                    'status' => 'PAID',
                    'metadata' => json_encode([
                        'item_pembayaran' => $feeCategories[$ot['cat']]->name,
                        'waktu_pembayaran' => 'TAHUNAN',
                        'dibayar' => $ot['amount'],
                        'sisa_tagihan' => 0,
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $paymentsBatch[] = [
                    'id' => (string) Str::uuid(),
                    'bill_id' => $billId,
                    'invoice_number' => 'INV/' . Carbon::parse($ot['pay_date'])->format('Ym') . '/BSI-' . ($invoiceCounter++),
                    'payment_method' => 'Bank Syariah Indonesia (BSI VA)',
                    'paid_amount' => $ot['amount'],
                    'paid_at' => $ot['pay_date'],
                    'payment_gateway_payload' => json_encode([
                        'channel' => 'BSI Mobile',
                        'va_number' => $vaBsi,
                        'ref_no' => 'BSI-' . Str::upper(Str::random(10)),
                    ]),
                    'metadata' => json_encode([
                        'payment_number' => 'PAY-' . Str::upper(Str::random(8)),
                        'receipt_number' => 'RCP-' . Str::upper(Str::random(8)),
                        'notes' => 'Pembayaran terverifikasi otomatis via BSI Virtual Account',
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // 2. Tagihan SPP Bulanan (Juli - Desember 2026)
            foreach ($months as $m) {
                $isPaid = $m['paid'];
                $billId = (string) Str::uuid();

                $billsBatch[] = [
                    'id' => $billId,
                    'student_id' => $student->id,
                    'fee_category_id' => $feeCategories['SPP']->id,
                    'academic_year_id' => $activeAy->id,
                    'title' => "SPP Bulan {$m['name']}",
                    'amount' => $sppAmount,
                    'due_date' => $m['due'],
                    'status' => $isPaid ? 'PAID' : 'UNPAID',
                    'metadata' => json_encode([
                        'item_pembayaran' => 'SPP',
                        'waktu_pembayaran' => 'BULANAN',
                        'bulan_tagihan' => Carbon::parse($m['due'])->format('Y-m'),
                        'bulan_tagihan_FY' => $m['name'],
                        'dibayar' => $isPaid ? $sppAmount : 0,
                        'sisa_tagihan' => $isPaid ? 0 : $sppAmount,
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($isPaid && $m['pay_date']) {
                    $paymentsBatch[] = [
                        'id' => (string) Str::uuid(),
                        'bill_id' => $billId,
                        'invoice_number' => 'INV/' . Carbon::parse($m['pay_date'])->format('Ym') . '/BSI-' . ($invoiceCounter++),
                        'payment_method' => 'Bank Syariah Indonesia (BSI VA)',
                        'paid_amount' => $sppAmount,
                        'paid_at' => $m['pay_date'],
                        'payment_gateway_payload' => json_encode([
                            'channel' => 'BSI Mobile',
                            'va_number' => $vaBsi,
                            'ref_no' => 'BSI-' . Str::upper(Str::random(10)),
                        ]),
                        'metadata' => json_encode([
                            'payment_number' => 'PAY-' . Str::upper(Str::random(8)),
                            'receipt_number' => 'RCP-' . Str::upper(Str::random(8)),
                            'notes' => 'Pembayaran SPP terverifikasi otomatis via BSI Virtual Account',
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        // Simpan dalam batch 500 rows
        $this->command?->info("Menyimpan " . count($billsBatch) . " data tagihan...");
        foreach (array_chunk($billsBatch, 500) as $chunk) {
            DB::table('student_bills')->insert($chunk);
        }

        $this->command?->info("Menyimpan " . count($paymentsBatch) . " data riwayat transaksi...");
        foreach (array_chunk($paymentsBatch, 500) as $chunk) {
            DB::table('bill_payments')->insert($chunk);
        }

        $this->command?->info('Berhasil membuat seluruh data tagihan SPP & pembayaran dinamis!');
    }
}
