<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SipdeiBillingService
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected int $cacheTtl;

    public function __construct()
    {
        $setting = \App\Models\SiteSetting::current();
        $this->baseUrl = (string) ($setting->sipdei_base_url ?: config('services.sipdei.base_url', 'https://sipdei.dareliman.tech'));
        $this->apiKey = $setting->sipdei_api_key ?: config('services.sipdei.api_key');
        $this->cacheTtl = (int) ($setting->sipdei_cache_ttl ?: config('services.sipdei.cache_ttl', 600));
    }

    /**
     * Memeriksa apakah integrasi SIPDEI sudah dikonfigurasi.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->baseUrl);
    }

    /**
     * Mengambil riwayat tagihan siswa dari API SIPDEI dengan caching.
     *
     * @param string $nisPembayaran
     * @param array $filters (id_tahun_ajaran, bulan_tagihan, bulan_tagihan_end, status_tagihan, id_item_pembayaran, limit, page)
     * @param bool $forceRefresh
     * @return array
     */
    public function getBillingHistory(string $nisPembayaran, array $filters = [], bool $forceRefresh = false): array
    {
        $cleanNis = trim($nisPembayaran);
        if ($cleanNis === '') {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'NIS Pembayaran tidak valid atau kosong.',
                'data' => null,
            ];
        }

        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'status_code' => 503,
                'message' => 'Layanan SIPDEI belum dikonfigurasi di server.',
                'data' => null,
            ];
        }

        // Filter hanya parameter yang diizinkan sesuai spek API SIPDEI
        $allowedParams = [
            'id_tahun_ajaran',
            'bulan_tagihan',
            'bulan_tagihan_end',
            'status_tagihan',
            'id_item_pembayaran',
            'limit',
            'page',
        ];

        $queryParams = [];
        foreach ($allowedParams as $paramKey) {
            if (isset($filters[$paramKey]) && $filters[$paramKey] !== '' && $filters[$paramKey] !== null) {
                $queryParams[$paramKey] = $filters[$paramKey];
            }
        }

        $cacheKey = 'sipdei:tagihan:' . $cleanNis . ':' . md5(json_encode($queryParams));

        if (!$forceRefresh && Cache::has($cacheKey)) {
            $cachedData = Cache::get($cacheKey);
            if (is_array($cachedData)) {
                $cachedData['from_cache'] = true;
                return $cachedData;
            }
        }

        $endpoint = rtrim($this->baseUrl, '/') . '/api/partner/tagihan-siswa/' . rawurlencode($cleanNis) . '/riwayat';

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout(10)
                ->get($endpoint, $queryParams);

            $statusCode = $response->status();
            $body = $response->json();

            if ($response->successful()) {
                $result = [
                    'success' => true,
                    'status_code' => 200,
                    'message' => 'Data tagihan berhasil diambil dari SIPDEI.',
                    'data' => $body['data'] ?? [],
                    'pagination' => $body['pagination'] ?? [
                        'total' => count($body['data']['tagihan'] ?? []),
                        'limit' => $queryParams['limit'] ?? 10,
                        'page' => $queryParams['page'] ?? 1,
                        'pages' => 1,
                    ],
                    'from_cache' => false,
                ];

                // Cache hasil sukses
                Cache::put($cacheKey, $result, $this->cacheTtl);
                return $result;
            }

            // Tangani status code error spesifik sesuai dokumentasi
            $errorMessage = match ($statusCode) {
                401 => 'Autentikasi SIPDEI gagal (X-API-Key tidak valid).',
                404 => $body['message'] ?? 'NIS Pembayaran tidak ditemukan di SIPDEI atau daftar ulang belum disetujui.',
                409 => 'NIS Pembayaran menunjuk lebih dari satu data siswa di SIPDEI.',
                422 => 'Parameter query filter tidak valid.',
                429 => 'Terlalu banyak permintaan ke server SIPDEI (Rate limit tercapai). Coba lagi beberapa saat.',
                503 => 'Layanan SIPDEI sedang dalam pemeliharaan atau belum aktif.',
                default => $body['message'] ?? 'Gagal mengambil tagihan dari server SIPDEI (Kode: ' . $statusCode . ').',
            };

            Log::warning('SIPDEI Billing API non-200 response', [
                'nis' => $cleanNis,
                'status' => $statusCode,
                'body' => $body,
            ]);

            return [
                'success' => false,
                'status_code' => $statusCode,
                'message' => $errorMessage,
                'data' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('SIPDEI Billing API Exception', [
                'nis' => $cleanNis,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => 500,
                'message' => 'Koneksi ke server SIPDEI terputus atau batas waktu tercapai.',
                'data' => null,
            ];
        }
    }

    /**
     * Memetakan tagihan SIPDEI ke dalam format standar StudentBill yang diharapkan
     * oleh aplikasi mobile (ParentBillsScreen) dan web dashboard.
     *
     * @param array $sipdeiTagihanList
     * @param Student $student
     * @return array
     */
    public function formatBillsForPortal(array $sipdeiTagihanList, Student $student): array
    {
        $formatted = [];

        foreach ($sipdeiTagihanList as $index => $item) {
            $tagihanId = $item['id_log_tagihan_siswa'] ?? ($index + 1);
            $namaItem = $item['item_pembayaran'] ?? 'Biaya Pendidikan';
            $bulanFy = $item['bulan_tagihan_FY'] ?? $item['bulan_tagihan'] ?? '';
            $statusRaw = strtoupper((string) ($item['status_tagihan'] ?? 'BELUM'));

            $status = match ($statusRaw) {
                'SUDAH' => 'PAID',
                'SEBAGIAN' => 'PARTIAL',
                default => 'UNPAID',
            };

            $totalBiaya = (int) ($item['total_biaya'] ?? 0);
            $dibayar = (int) ($item['dibayar'] ?? 0);
            $sisa = (int) ($item['sisa_tagihan'] ?? ($totalBiaya - $dibayar));

            // Perkiraan jatuh tempo (misal tanggal 10 dari bulan tagihan)
            $dueDate = null;
            if (!empty($item['bulan_tagihan'])) {
                $dueDate = $item['bulan_tagihan'] . '-10';
            }

            $payments = [];
            if ($dibayar > 0) {
                $payments[] = [
                    'id' => 'sipdei_pay_' . $tagihanId,
                    'bill_id' => 'sipdei_' . $tagihanId,
                    'payment_number' => 'SIPDEI-PAY-' . $tagihanId,
                    'amount' => $dibayar,
                    'payment_date' => !empty($item['bulan_tagihan']) ? $item['bulan_tagihan'] . '-01' : now()->toDateString(),
                    'payment_method' => 'Bank / Virtual Account SIPDEI',
                    'status' => 'SUCCESS',
                    'receipt_number' => 'RCP-SIPDEI-' . $tagihanId,
                    'notes' => 'Pembayaran terverifikasi di sistem keuangan SIPDEI',
                ];
            }

            $title = $namaItem . ($bulanFy ? ' - ' . $bulanFy : '');

            $formatted[] = [
                'id' => 'sipdei_' . $tagihanId,
                'student_id' => $student->id,
                'academic_year_id' => null,
                'fee_category_id' => 'sipdei_cat_' . ($item['id_item_pembayaran'] ?? 'spp'),
                'title' => $title,
                'description' => 'Tagihan resmi ' . $namaItem . ' dari SIPDEI Yayasan',
                'amount' => $totalBiaya,
                'paid_amount' => $dibayar,
                'remaining_amount' => max(0, $sisa),
                'due_date' => $dueDate,
                'status' => $status,
                'source' => 'sipdei',
                'fee_category' => [
                    'id' => 'sipdei_cat_' . ($item['id_item_pembayaran'] ?? 'spp'),
                    'name' => $namaItem,
                    'code' => 'SIPDEI',
                    'category' => $item['waktu_pembayaran'] ?? 'BULANAN',
                ],
                'payments' => $payments,
                'metadata' => [
                    'sipdei_id' => $tagihanId,
                    'waktu_pembayaran' => $item['waktu_pembayaran'] ?? 'BULANAN',
                    'bulan_tagihan' => $item['bulan_tagihan'] ?? null,
                    'bulan_tagihan_fy' => $bulanFy,
                    'status_tagihan_sipdei' => $statusRaw,
                ],
            ];
        }

        return $formatted;
    }

    /**
     * Membuat instruksi pembayaran via Virtual Account Bank Nagari atau BSI pada SIPDEI.
     *
     * @param string $nisPembayaran
     * @param string $bank 'NAGARI' atau 'BSI'
     * @param array $items Array dari item: ['jenis' => 'TAGIHAN'|'TUNGGAKAN'|'DANA_ORTU_ASUH', 'id' => int, 'bayar' => int]
     * @return array
     */
    public function createVaPayment(string $nisPembayaran, string $bank, array $items): array
    {
        $cleanNis = trim($nisPembayaran);
        if ($cleanNis === '') {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'NIS Pembayaran tidak valid atau kosong.',
                'data' => null,
            ];
        }

        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'status_code' => 503,
                'message' => 'Layanan SIPDEI belum dikonfigurasi di server. Silakan hubungi Super Admin / Admin untuk memasukkan API Key pada Pengaturan Sistem.',
                'data' => null,
            ];
        }

        $bankUpper = strtoupper(trim($bank));
        if (!in_array($bankUpper, ['NAGARI', 'BSI'])) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Bank yang dipilih tidak valid. Pilihan yang didukung: NAGARI atau BSI.',
                'data' => null,
            ];
        }

        if (empty($items)) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Pilih setidaknya satu tagihan yang akan dibayar.',
                'data' => null,
            ];
        }

        // Validasi dan sanitasi item sesuai spek SIPDEI
        $sanitizedItems = [];
        foreach ($items as $item) {
            $jenis = strtoupper((string) ($item['jenis'] ?? 'TAGIHAN'));
            if (!in_array($jenis, ['TAGIHAN', 'TUNGGAKAN', 'DANA_ORTU_ASUH'])) {
                $jenis = 'TAGIHAN';
            }

            $sanitizedItems[] = [
                'jenis' => $jenis,
                'id' => (int) ($item['id'] ?? 0),
                'bayar' => (int) ($item['bayar'] ?? 0),
            ];
        }

        $endpoint = rtrim($this->baseUrl, '/') . '/api/partner/tagihan-siswa/' . rawurlencode($cleanNis) . '/pembayaran-va';

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout(15)
                ->post($endpoint, [
                    'bank' => $bankUpper,
                    'items' => $sanitizedItems,
                ]);

            $statusCode = $response->status();
            $body = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status_code' => 200,
                    'message' => 'Nomor Virtual Account resmi berhasil diterbitkan oleh SIPDEI.',
                    'data' => $body['data'] ?? [],
                ];
            }

            $errorMessage = match ($statusCode) {
                401 => 'Autentikasi SIPDEI gagal (X-API-Key tidak valid atau kedaluwarsa).',
                404 => $body['message'] ?? 'NIS Pembayaran tidak ditemukan di SIPDEI atau daftar ulang belum disetujui.',
                409 => 'NIS Pembayaran menunjuk lebih dari satu data siswa di SIPDEI.',
                422 => $body['message'] ?? 'Data item atau nominal bayar tidak valid sesuai ketentuan SIPDEI.',
                429 => 'Terlalu banyak permintaan pembuatan VA ke server SIPDEI (Maks 5/menit). Mohon tunggu sejenak.',
                502 => 'Nomor VA atau kode biller siswa belum dapat dibentuk di SIPDEI. Hubungi pengelola yayasan.',
                503 => 'Layanan pembuatan VA SIPDEI sedang dalam pemeliharaan.',
                default => $body['message'] ?? 'Gagal membuat pembayaran VA di server SIPDEI (Kode: ' . $statusCode . ').',
            };

            Log::warning('SIPDEI Create VA Payment non-200 response', [
                'nis' => $cleanNis,
                'bank' => $bankUpper,
                'status' => $statusCode,
                'body' => $body,
            ]);

            return [
                'success' => false,
                'status_code' => $statusCode,
                'message' => $errorMessage,
                'errors' => $body['errors'] ?? null,
                'data' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('SIPDEI Create VA Payment Exception', [
                'nis' => $cleanNis,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => 500,
                'message' => 'Gagal terhubung ke server SIPDEI untuk membuat pembayaran Virtual Account: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }
}
