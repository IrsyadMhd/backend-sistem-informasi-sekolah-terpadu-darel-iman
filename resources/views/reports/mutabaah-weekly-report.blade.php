<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $doc_title ?? 'Laporan Perkembangan & Mutabaah Pekanan' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 12mm 12mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            line-height: 1.25;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        /* ── KOP SURAT RESMI ── */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .kop-logo {
            width: 55px;
            text-align: center;
            vertical-align: middle;
        }
        .kop-text {
            text-align: center;
            vertical-align: middle;
            padding: 0 8px;
        }
        .kop-yayasan {
            font-size: 10.5pt;
            font-weight: bold;
            color: #0E5C44;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        .kop-unit {
            font-size: 12pt;
            font-weight: bold;
            color: #064E3B;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        .kop-motto {
            font-size: 7.5pt;
            font-style: italic;
            color: #475569;
            margin-bottom: 2px;
        }
        .kop-address {
            font-size: 7.5pt;
            color: #334155;
        }
        .kop-contact {
            font-size: 7pt;
            color: #64748b;
        }
        .kop-divider-thick {
            height: 2px;
            background-color: #0E5C44;
            margin-top: 5px;
            margin-bottom: 1px;
        }
        .kop-divider-thin {
            height: 0.75px;
            background-color: #0E5C44;
            margin-bottom: 8px;
        }

        /* ── JUDUL DOKUMEN ── */
        .doc-header {
            text-align: center;
            margin-bottom: 8px;
        }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            color: #0E5C44;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin: 0 0 2px 0;
        }
        .doc-period {
            font-size: 8.5pt;
            font-weight: bold;
            color: #047857;
            margin: 0 0 2px 0;
        }
        .doc-meta {
            font-size: 7pt;
            color: #64748b;
            margin: 0;
        }

        /* ── KARTU IDENTITAS SISWA ── */
        .identity-box {
            width: 100%;
            border: 1px solid #CBD5E1;
            border-radius: 4px;
            background-color: #F8FAFC;
            margin-bottom: 8px;
            padding: 4px 6px;
        }
        .identity-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }
        .identity-table td {
            padding: 1.5px 3px;
            vertical-align: middle;
        }
        .identity-label {
            width: 16%;
            color: #64748b;
            font-weight: normal;
        }
        .identity-colon {
            width: 2%;
            text-align: center;
            color: #64748b;
        }
        .identity-val {
            width: 32%;
            font-weight: bold;
            color: #0F172A;
        }

        /* ── SECTION HEADER ── */
        .section-header {
            background-color: #0E5C44;
            color: #FFFFFF;
            font-size: 8pt;
            font-weight: bold;
            padding: 3px 6px;
            border-radius: 3px 3px 0 0;
            margin-top: 7px;
            margin-bottom: 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .section-header-amber {
            background-color: #D97706;
            color: #FFFFFF;
            font-size: 8pt;
            font-weight: bold;
            padding: 3px 6px;
            border-radius: 3px 3px 0 0;
            margin-top: 7px;
            margin-bottom: 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ── TABEL DATA ── */
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
            margin-bottom: 6px;
        }
        table.report-table th {
            background-color: #E2E8F0;
            color: #0F172A;
            font-weight: bold;
            text-align: center;
            padding: 3.5px 3px;
            border: 0.75px solid #CBD5E1;
            vertical-align: middle;
        }
        table.report-table td {
            padding: 3px 4px;
            border: 0.75px solid #E2E8F0;
            vertical-align: middle;
        }
        table.report-table tr:nth-child(even) {
            background-color: #F8FAFC;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }

        /* Badge Status */
        .status-badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 6.5pt;
            font-weight: bold;
            text-align: center;
        }
        .status-good { background-color: #DCFCE7; color: #166534; }
        .status-less { background-color: #FEF3C7; color: #92400E; }
        .status-not_done { background-color: #FEE2E2; color: #991B1B; }
        .status-na { background-color: #F1F5F9; color: #64748B; }

        /* ── SUMMARY STATS BOX ── */
        .stats-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .stats-grid td {
            width: 25%;
            padding: 2px;
        }
        .stat-card {
            border: 1px solid #CBD5E1;
            border-radius: 4px;
            padding: 4px 6px;
            text-align: center;
            background-color: #F8FAFC;
        }
        .stat-title {
            font-size: 6.5pt;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }
        .stat-value {
            font-size: 11pt;
            font-weight: bold;
            color: #0E5C44;
            margin-top: 1px;
        }

        /* ── CATATAN PEMBIMBING ── */
        .notes-card {
            border: 1px solid #CBD5E1;
            border-left: 3px solid #0E5C44;
            border-radius: 3px;
            background-color: #F8FAFC;
            padding: 5px 8px;
            font-size: 7pt;
            color: #334155;
            margin-bottom: 6px;
        }

        /* ── PENUTUP & ARABIC DOA ── */
        .closing-box {
            text-align: center;
            padding: 4px 6px;
            margin: 6px 0 8px 0;
            background-color: #F0FDF4;
            border: 0.75px solid #BBF7D0;
            border-radius: 4px;
        }
        .closing-text {
            font-size: 7pt;
            color: #166534;
            margin-bottom: 2px;
        }
        .closing-arabic {
            font-size: 8pt;
            color: #0E5C44;
            font-weight: bold;
            margin: 1px 0;
        }

        /* ── KOTAK TANDA TANGAN RESMI ── */
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .signature-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 4px;
            font-size: 7.5pt;
        }
        .sig-role {
            font-weight: bold;
            color: #0F172A;
            margin-bottom: 2px;
        }
        .sig-sub {
            font-size: 6.5pt;
            color: #64748b;
            margin-bottom: 6px;
        }
        .sig-space {
            height: 40px;
        }
        .sig-stamp-box {
            height: 40px;
            border: 0.75px dashed #CBD5E1;
            border-radius: 3px;
            background-color: #F8FAFC;
            margin: 0 auto;
            width: 80%;
            text-align: center;
            line-height: 40px;
            color: #059669;
            font-size: 6.5pt;
            font-weight: bold;
        }
        .sig-name {
            font-weight: bold;
            color: #0F172A;
            border-top: 0.75px solid #CBD5E1;
            display: inline-block;
            min-width: 140px;
            padding-top: 2px;
            margin-top: 2px;
        }
        .sig-nip {
            font-size: 6.5pt;
            color: #64748b;
        }

        /* ── FOOTER DOKUMEN ── */
        .doc-footer {
            margin-top: 8px;
            border-top: 0.5px solid #CBD5E1;
            padding-top: 3px;
            font-size: 6.5pt;
            color: #94A3B8;
            width: 100%;
        }
        .doc-footer table {
            width: 100%;
            border-collapse: collapse;
        }
    </style>
</head>
<body>

    <!-- 1. KOP SURAT LEMBAGA & UNIT PENDIDIKAN -->
    <table class="kop-table">
        <tr>
            <td class="kop-text">
                <div class="kop-yayasan">{{ $foundation_name }}</div>
                <div class="kop-unit">{{ $unit_name }}</div>
                @if(!empty($unit_motto))
                    <div class="kop-motto">"{{ $unit_motto }}"</div>
                @endif
                <div class="kop-address">{{ $unit_address }}</div>
                <div class="kop-contact">
                    @if(!empty($unit_npsn)) NPSN: {{ $unit_npsn }} &bull; @endif
                    @if(!empty($unit_akreditasi)) Terakreditasi {{ $unit_akreditasi }} &bull; @endif
                    @if(!empty($unit_phone)) Telp: {{ $unit_phone }} &bull; @endif
                    Email: {{ $unit_email }}
                </div>
            </td>
        </tr>
    </table>
    <div class="kop-divider-thick"></div>
    <div class="kop-divider-thin"></div>

    <!-- 2. JUDUL DOKUMEN LAPORAN PEKANAN -->
    <div class="doc-header">
        <h1 class="doc-title">{{ $doc_title ?? 'LAPORAN PERKEMBANGAN & MUTABA\'AH PEKANAN SANTRI' }}</h1>
        <div class="doc-period">PERIODE: {{ strtoupper($period_text) }}</div>
        <div class="doc-meta">
            Dokumen Resmi SIMSIT Terpadu &bull; Dicetak: {{ $printed_at }} &bull; No. Dokumen: {{ $doc_number }}
        </div>
    </div>

    <!-- 3. KARTU IDENTITAS PESERTA DIDIK -->
    <div class="identity-box">
        <table class="identity-table">
            <tr>
                <td class="identity-label">Nama Santri/Siswa</td>
                <td class="identity-colon">:</td>
                <td class="identity-val">{{ $student->full_name }}</td>
                <td class="identity-label">Kelas / Rombel</td>
                <td class="identity-colon">:</td>
                <td class="identity-val">{{ $class_name }}</td>
            </tr>
            <tr>
                <td class="identity-label">NIS / NISN</td>
                <td class="identity-colon">:</td>
                <td class="identity-val">{{ $student->nis ?? '-' }} / {{ $student->nisn ?? '-' }}</td>
                <td class="identity-label">Wali Kelas / Musyrif</td>
                <td class="identity-colon">:</td>
                <td class="identity-val">{{ $wali_kelas_name }}</td>
            </tr>
            <tr>
                <td class="identity-label">Orang Tua / Wali</td>
                <td class="identity-colon">:</td>
                <td class="identity-val">{{ $parent_name }}</td>
                <td class="identity-label">Program & Status</td>
                <td class="identity-colon">:</td>
                <td class="identity-val">{{ ucfirst($program_type) }} &bull; Aktif</td>
            </tr>
        </table>
    </div>

    <!-- 4. RINGKASAN KPI CAPAIAN PEKANAN -->
    <table class="stats-grid">
        <tr>
            <td>
                <div class="stat-card">
                    <div class="stat-title">Skor Kepatuhan Pekan</div>
                    <div class="stat-value">{{ round($summary['score'] ?? 0) }}%</div>
                </div>
            </td>
            <td>
                <div class="stat-card">
                    <div class="stat-title">Hari Terisi</div>
                    <div class="stat-value">{{ $summary['filled_days'] ?? 0 }} / {{ $summary['total_days'] ?? 7 }} Hari</div>
                </div>
            </td>
            <td>
                <div class="stat-card">
                    <div class="stat-title">Amalan Terlaksana (Baik)</div>
                    <div class="stat-value" style="color: #059669;">{{ $summary['good'] ?? 0 }} Checklist</div>
                </div>
            </td>
            <td>
                <div class="stat-card">
                    <div class="stat-title">Predikat Pekanan</div>
                    <div class="stat-value" style="font-size: 8.5pt; color: #0284C7;">
                        {{ $summary['status_label'] ?? 'Pencapaian Baik' }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- 5. BAGIAN 1: KEDISIPLINAN & PRESENSI HARIAN -->
    <div class="section-header">1. Rekapitulasi Presensi & Kedisiplinan Harian</div>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 25%;">Hari, Tanggal</th>
                <th style="width: 25%;">Presensi Pagi (Gerbang)</th>
                <th style="width: 25%;">Waktu Tap / Masuk</th>
                <th style="width: 25%;">Catatan Kedisiplinan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($weekly_attendance as $att)
            <tr>
                <td class="text-left font-bold">{{ $att['day_date'] }}</td>
                <td class="text-center">
                    <span class="status-badge {{ $att['status_class'] }}">{{ $att['status_label'] }}</span>
                </td>
                <td class="text-center">{{ $att['time'] ?? '—' }}</td>
                <td class="text-center">{{ $att['note'] ?? 'Tertib / Tepat Waktu' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="text-center" style="color: #64748b; padding: 6px;">
                    Belum ada rekaman presensi pada pekan ini.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- 6. BAGIAN 2: MUTABA'AH SHALAT FARDHU 5 WAKTU & AMALAN SUNNAH -->
    <div class="section-header">2. Rekapitulasi Shalat Fardhu 5 Waktu & Amalan Sunnah</div>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 18%;">Hari, Tanggal</th>
                <th style="width: 13%;">Subuh</th>
                <th style="width: 13%;">Zuhur</th>
                <th style="width: 13%;">Ashar</th>
                <th style="width: 13%;">Maghrib</th>
                <th style="width: 13%;">Isya'</th>
                <th style="width: 17%;">Amalan Sunnah</th>
            </tr>
        </thead>
        <tbody>
            @forelse($weekly_worship as $w)
            <tr>
                <td class="text-left font-bold">{{ $w['day_name'] }}, {{ $w['date_short'] }}</td>
                <td class="text-center"><span class="status-badge {{ $w['subuh_class'] }}">{{ $w['subuh_label'] }}</span></td>
                <td class="text-center"><span class="status-badge {{ $w['zuhur_class'] }}">{{ $w['zuhur_label'] }}</span></td>
                <td class="text-center"><span class="status-badge {{ $w['ashar_class'] }}">{{ $w['ashar_label'] }}</span></td>
                <td class="text-center"><span class="status-badge {{ $w['maghrib_class'] }}">{{ $w['maghrib_label'] }}</span></td>
                <td class="text-center"><span class="status-badge {{ $w['isya_class'] }}">{{ $w['isya_label'] }}</span></td>
                <td class="text-center" style="font-size: 6.5pt;">{{ $w['sunnah_summary'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center" style="color: #64748b; padding: 6px;">
                    Belum ada data rekaman shalat harian pada pekan ini.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- 7. BAGIAN 3: CAPAIAN TAHSIN & TAHFIZH AL-QUR'AN -->
    <div class="section-header-amber">3. Capaian Tahsin & Tahfizh Al-Qur'an</div>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 25%;">Hafalan / Setoran Terakhir</th>
                <th style="width: 20%;">Tanggal Setoran</th>
                <th style="width: 18%;">Jumlah Baris / Ayat</th>
                <th style="width: 17%;">Target Pekanan</th>
                <th style="width: 20%;">Predikat Kelancaran</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-left font-bold">{{ $tahfizh_data['last_surah'] ?? 'Surah Al-Mulk (Ayat 1-30)' }}</td>
                <td class="text-center">{{ $tahfizh_data['last_date'] ?? $printed_date }}</td>
                <td class="text-center">{{ $tahfizh_data['total_lines'] ?? '15 Baris / 1 Halaman' }}</td>
                <td class="text-center">{{ $tahfizh_data['target_lines'] ?? '15 Baris / Pekan' }}</td>
                <td class="text-center">
                    <span class="status-badge status-good">{{ $tahfizh_data['status'] ?? 'Mumtaz (Sangat Lancar)' }}</span>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- 8. BAGIAN 4: CATATAN & EVALUASI PEMBIMBING -->
    <div class="section-header" style="background-color: #0369A1;">4. Catatan & Bimbingan Wali Kelas / Musyrif</div>
    <div class="notes-card">
        <strong>Pesan Pembimbing:</strong><br>
        {{ $supervisor_notes ?? 'Alhamdulillah ananda menunjukkan akhlak yang mulia, kedisiplinan yang baik, dan semangat dalam mengikuti halaqah Al-Qur\'an serta KBM sekolah. Mohon orang tua senantiasa mendampingi dan memotivasi shalat fardhu berjamaah serta muraja\'ah mandiri saat berada di rumah.' }}
    </div>

    <!-- 9. PENUTUP & ARABIC DOA -->
    <div class="closing-box">
        <div class="closing-text">
            Keberhasilan pendidikan dan pembentukan karakter ananda adalah buah dari sinergi kokoh antara sekolah dan keluarga di rumah.
        </div>
        <div class="closing-arabic">
            جَزَاكُمُ اللَّهُ خَيْرًا وَبَARAKALLAHU FIIKUM
        </div>
    </div>

    <!-- 10. KOTAK TANDA TANGAN RESMI (3 PIHAK) -->
    <table class="signature-table">
        <tr>
            <td>
                <div class="sig-role">Orang Tua / Wali Murid</div>
                <div class="sig-sub">Verifikasi & Paraf Mandiri</div>
                @if($parent_signature)
                    <div class="sig-stamp-box">
                        TERVERIFIKASI DIGITAL<br>
                        {{ $parent_signature->signed_at ? \Carbon\Carbon::parse($parent_signature->signed_at)->format('d/m/Y H:i') : 'Disetujui' }}
                    </div>
                @else
                    <div class="sig-space"></div>
                @endif
                <div class="sig-name">{{ $parent_name }}</div>
                <div class="sig-nip">Orang Tua / Wali Santri</div>
            </td>
            <td>
                <div class="sig-role">Musyrif / Wali Kelas</div>
                <div class="sig-sub">Pembimbing Halaqah & Kelas</div>
                <div class="sig-space"></div>
                <div class="sig-name">{{ $wali_kelas_name }}</div>
                <div class="sig-nip">Wali Kelas / Musyrif Terpadu</div>
            </td>
            <td>
                <div class="sig-role">Mengetahui,</div>
                <div class="sig-sub">Pimpinan / Kepala Sekolah</div>
                <div class="sig-space"></div>
                <div class="sig-name">{{ $principal_name }}</div>
                <div class="sig-nip">NIP/NIY: {{ $principal_nip }}</div>
            </td>
        </tr>
    </table>

    <!-- 11. FOOTER DOKUMEN SISTEM -->
    <div class="doc-footer">
        <table>
            <tr>
                <td class="text-left">
                    Sistem Manajemen Sekolah Terpadu (SIMSIT) &bull; Modul Mutaba'ah & Perkembangan Siswa
                </td>
                <td class="text-right">
                    Halaman 1 dari 1
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
