<?php

namespace App\Helpers;

class QuranJuzHelper
{
    /**
     * Titik awal (surah dan ayat) untuk setiap Juz dari Juz 1 hingga Juz 30.
     * Mengikuti Mushaf Standar Indonesia (Kemenag RI).
     */
    protected static array $juzStarts = [
        1  => ["surah" => 1,  "ayah" => 1],
        2  => ["surah" => 2,  "ayah" => 142],
        3  => ["surah" => 2,  "ayah" => 253],
        4  => ["surah" => 3,  "ayah" => 93],
        5  => ["surah" => 4,  "ayah" => 24],
        6  => ["surah" => 4,  "ayah" => 148],
        7  => ["surah" => 5,  "ayah" => 83],
        8  => ["surah" => 6,  "ayah" => 111],
        9  => ["surah" => 7,  "ayah" => 88],
        10 => ["surah" => 8,  "ayah" => 41],
        11 => ["surah" => 9,  "ayah" => 93],
        12 => ["surah" => 11, "ayah" => 6],
        13 => ["surah" => 12, "ayah" => 53],
        14 => ["surah" => 15, "ayah" => 1],
        15 => ["surah" => 17, "ayah" => 1],
        16 => ["surah" => 18, "ayah" => 75],
        17 => ["surah" => 21, "ayah" => 1],
        18 => ["surah" => 23, "ayah" => 1],
        19 => ["surah" => 25, "ayah" => 21],
        20 => ["surah" => 27, "ayah" => 56],
        21 => ["surah" => 29, "ayah" => 46],
        22 => ["surah" => 33, "ayah" => 31],
        23 => ["surah" => 36, "ayah" => 28],
        24 => ["surah" => 39, "ayah" => 32],
        25 => ["surah" => 41, "ayah" => 47],
        26 => ["surah" => 46, "ayah" => 1],
        27 => ["surah" => 51, "ayah" => 31],
        28 => ["surah" => 58, "ayah" => 1],
        29 => ["surah" => 67, "ayah" => 1],
        30 => ["surah" => 78, "ayah" => 1],
    ];

    /**
     * Metadata komprehensif rentang 30 Juz Al-Qur'an (Surah & Ayat Awal hingga Akhir).
     */
    protected static array $juzCatalog = [
        1  => ["juz" => 1,  "name" => "Juz 1", "surah_start" => 1,  "ayah_start" => 1,  "surah_end" => 2,  "ayah_end" => 141, "surah_start_name" => "Al-Fatihah", "surah_end_name" => "Al-Baqarah", "text_range" => "Surah Al-Fatihah (1:1) – Al-Baqarah (2:141)"],
        2  => ["juz" => 2,  "name" => "Juz 2", "surah_start" => 2,  "ayah_start" => 142,"surah_end" => 2,  "ayah_end" => 252, "surah_start_name" => "Al-Baqarah", "surah_end_name" => "Al-Baqarah", "text_range" => "Surah Al-Baqarah (2:142–252)"],
        3  => ["juz" => 3,  "name" => "Juz 3", "surah_start" => 2,  "ayah_start" => 253,"surah_end" => 3,  "ayah_end" => 92,  "surah_start_name" => "Al-Baqarah", "surah_end_name" => "Ali 'Imran", "text_range" => "Surah Al-Baqarah (2:253) – Ali 'Imran (3:92)"],
        4  => ["juz" => 4,  "name" => "Juz 4", "surah_start" => 3,  "ayah_start" => 93, "surah_end" => 4,  "ayah_end" => 23,  "surah_start_name" => "Ali 'Imran", "surah_end_name" => "An-Nisa'", "text_range" => "Surah Ali 'Imran (3:93) – An-Nisa' (4:23)"],
        5  => ["juz" => 5,  "name" => "Juz 5", "surah_start" => 4,  "ayah_start" => 24, "surah_end" => 4,  "ayah_end" => 147, "surah_start_name" => "An-Nisa'", "surah_end_name" => "An-Nisa'", "text_range" => "Surah An-Nisa' (4:24–147)"],
        6  => ["juz" => 6,  "name" => "Juz 6", "surah_start" => 4,  "ayah_start" => 148,"surah_end" => 5,  "ayah_end" => 82,  "surah_start_name" => "An-Nisa'", "surah_end_name" => "Al-Ma'idah", "text_range" => "Surah An-Nisa' (4:148) – Al-Ma'idah (5:82)"],
        7  => ["juz" => 7,  "name" => "Juz 7", "surah_start" => 5,  "ayah_start" => 83, "surah_end" => 6,  "ayah_end" => 110, "surah_start_name" => "Al-Ma'idah", "surah_end_name" => "Al-An'am", "text_range" => "Surah Al-Ma'idah (5:83) – Al-An'am (6:110)"],
        8  => ["juz" => 8,  "name" => "Juz 8", "surah_start" => 6,  "ayah_start" => 111,"surah_end" => 7,  "ayah_end" => 87,  "surah_start_name" => "Al-An'am", "surah_end_name" => "Al-A'raf", "text_range" => "Surah Al-An'am (6:111) – Al-A'raf (7:87)"],
        9  => ["juz" => 9,  "name" => "Juz 9", "surah_start" => 7,  "ayah_start" => 88, "surah_end" => 8,  "ayah_end" => 40,  "surah_start_name" => "Al-A'raf", "surah_end_name" => "Al-Anfal", "text_range" => "Surah Al-A'raf (7:88) – Al-Anfal (8:40)"],
        10 => ["juz" => 10, "name" => "Juz 10","surah_start" => 8,  "ayah_start" => 41, "surah_end" => 9,  "ayah_end" => 92,  "surah_start_name" => "Al-Anfal", "surah_end_name" => "At-Taubah", "text_range" => "Surah Al-Anfal (8:41) – At-Taubah (9:92)"],
        11 => ["juz" => 11, "name" => "Juz 11","surah_start" => 9,  "ayah_start" => 93, "surah_end" => 11, "ayah_end" => 5,   "surah_start_name" => "At-Taubah", "surah_end_name" => "Hud", "text_range" => "Surah At-Taubah (9:93) – Hud (11:5)"],
        12 => ["juz" => 12, "name" => "Juz 12","surah_start" => 11, "ayah_start" => 6,  "surah_end" => 12, "ayah_end" => 52,  "surah_start_name" => "Hud", "surah_end_name" => "Yusuf", "text_range" => "Surah Hud (11:6) – Yusuf (12:52)"],
        13 => ["juz" => 13, "name" => "Juz 13","surah_start" => 12, "ayah_start" => 53, "surah_end" => 14, "ayah_end" => 52,  "surah_start_name" => "Yusuf", "surah_end_name" => "Ibrahim", "text_range" => "Surah Yusuf (12:53) – Ibrahim (14:52)"],
        14 => ["juz" => 14, "name" => "Juz 14","surah_start" => 15, "ayah_start" => 1,  "surah_end" => 16, "ayah_end" => 128, "surah_start_name" => "Al-Hijr", "surah_end_name" => "An-Nahl", "text_range" => "Surah Al-Hijr (15:1) – An-Nahl (16:128)"],
        15 => ["juz" => 15, "name" => "Juz 15","surah_start" => 17, "ayah_start" => 1,  "surah_end" => 18, "ayah_end" => 74,  "surah_start_name" => "Al-Isra'", "surah_end_name" => "Al-Kahf", "text_range" => "Surah Al-Isra' (17:1) – Al-Kahf (18:74)"],
        16 => ["juz" => 16, "name" => "Juz 16","surah_start" => 18, "ayah_start" => 75, "surah_end" => 20, "ayah_end" => 135, "surah_start_name" => "Al-Kahf", "surah_end_name" => "Ta-Ha", "text_range" => "Surah Al-Kahf (18:75) – Ta-Ha (20:135)"],
        17 => ["juz" => 17, "name" => "Juz 17","surah_start" => 21, "ayah_start" => 1,  "surah_end" => 22, "ayah_end" => 78,  "surah_start_name" => "Al-Anbiya'", "surah_end_name" => "Al-Hajj", "text_range" => "Surah Al-Anbiya' (21:1) – Al-Hajj (22:78)"],
        18 => ["juz" => 18, "name" => "Juz 18","surah_start" => 23, "ayah_start" => 1,  "surah_end" => 25, "ayah_end" => 20,  "surah_start_name" => "Al-Mu'minun", "surah_end_name" => "Al-Furqan", "text_range" => "Surah Al-Mu'minun (23:1) – Al-Furqan (25:20)"],
        19 => ["juz" => 19, "name" => "Juz 19","surah_start" => 25, "ayah_start" => 21, "surah_end" => 27, "ayah_end" => 55,  "surah_start_name" => "Al-Furqan", "surah_end_name" => "An-Naml", "text_range" => "Surah Al-Furqan (25:21) – An-Naml (27:55)"],
        20 => ["juz" => 20, "name" => "Juz 20","surah_start" => 27, "ayah_start" => 56, "surah_end" => 29, "ayah_end" => 45,  "surah_start_name" => "An-Naml", "surah_end_name" => "Al-'Ankabut", "text_range" => "Surah An-Naml (27:56) – Al-'Ankabut (29:45)"],
        21 => ["juz" => 21, "name" => "Juz 21","surah_start" => 29, "ayah_start" => 46, "surah_end" => 33, "ayah_end" => 30,  "surah_start_name" => "Al-'Ankabut", "surah_end_name" => "Al-Ahzab", "text_range" => "Surah Al-'Ankabut (29:46) – Al-Ahzab (33:30)"],
        22 => ["juz" => 22, "name" => "Juz 22","surah_start" => 33, "ayah_start" => 31, "surah_end" => 36, "ayah_end" => 27,  "surah_start_name" => "Al-Ahzab", "surah_end_name" => "Yasin", "text_range" => "Surah Al-Ahzab (33:31) – Yasin (36:27)"],
        23 => ["juz" => 23, "name" => "Juz 23","surah_start" => 36, "ayah_start" => 28, "surah_end" => 39, "ayah_end" => 31,  "surah_start_name" => "Yasin", "surah_end_name" => "Az-Zumar", "text_range" => "Surah Yasin (36:28) – Az-Zumar (39:31)"],
        24 => ["juz" => 24, "name" => "Juz 24","surah_start" => 39, "ayah_start" => 32, "surah_end" => 41, "ayah_end" => 46,  "surah_start_name" => "Az-Zumar", "surah_end_name" => "Fussilat", "text_range" => "Surah Az-Zumar (39:32) – Fussilat (41:46)"],
        25 => ["juz" => 25, "name" => "Juz 25","surah_start" => 41, "ayah_start" => 47, "surah_end" => 45, "ayah_end" => 37,  "surah_start_name" => "Fussilat", "surah_end_name" => "Al-Jatsiyah", "text_range" => "Surah Fussilat (41:47) – Al-Jatsiyah (45:37)"],
        26 => ["juz" => 26, "name" => "Juz 26","surah_start" => 46, "ayah_start" => 1,  "surah_end" => 51, "ayah_end" => 30,  "surah_start_name" => "Al-Ahqaf", "surah_end_name" => "Adz-Dzariyat", "text_range" => "Surah Al-Ahqaf (46:1) – Adz-Dzariyat (51:30)"],
        27 => ["juz" => 27, "name" => "Juz 27","surah_start" => 51, "ayah_start" => 31, "surah_end" => 57, "ayah_end" => 29,  "surah_start_name" => "Adz-Dzariyat", "surah_end_name" => "Al-Hadid", "text_range" => "Surah Adz-Dzariyat (51:31) – Al-Hadid (57:29)"],
        28 => ["juz" => 28, "name" => "Juz 28","surah_start" => 58, "ayah_start" => 1,  "surah_end" => 66, "ayah_end" => 12,  "surah_start_name" => "Al-Mujadilah", "surah_end_name" => "At-Tahrim", "text_range" => "Surah Al-Mujadilah (58:1) – At-Tahrim (66:12)"],
        29 => ["juz" => 29, "name" => "Juz 29 (Tabarak)","surah_start" => 67, "ayah_start" => 1,  "surah_end" => 77, "ayah_end" => 50,  "surah_start_name" => "Al-Mulk", "surah_end_name" => "Al-Mursalat", "text_range" => "Surah Al-Mulk (67:1) – Al-Mursalat (77:50)"],
        30 => ["juz" => 30, "name" => "Juz 30 (Juz 'Amma)","surah_start" => 78, "ayah_start" => 1,  "surah_end" => 114,"ayah_end" => 6,   "surah_start_name" => "An-Naba'", "surah_end_name" => "An-Nas", "text_range" => "Surah An-Naba' (78:1) – An-Nas (114:6) (dikenal sebagai Juz 'Amma)"],
    ];

    /**
     * Dapatkan nomor Juz (1 s/d 30) untuk surah dan ayat tertentu.
     */
    public static function getJuz(int $surahNumber, int $ayahNumber = 1): int
    {
        if ($surahNumber < 1) return 1;
        if ($surahNumber >= 78) return 30;

        for ($juz = 30; $juz >= 1; $juz--) {
            $start = self::$juzStarts[$juz];
            if ($surahNumber > $start["surah"] || ($surahNumber === $start["surah"] && $ayahNumber >= $start["ayah"])) {
                return $juz;
            }
        }

        return 1;
    }

    /**
     * Dapatkan daftar Juz yang mencakup rentang ayat tertentu.
     */
    public static function getJuzForRange(int $surahNumber, int $ayahStart, int $ayahEnd): array
    {
        $juzStart = self::getJuz($surahNumber, $ayahStart);
        $juzEnd = self::getJuz($surahNumber, $ayahEnd);

        $juzList = [];
        for ($j = $juzStart; $j <= $juzEnd; $j++) {
            $juzList[] = $j;
        }

        return $juzList;
    }

    /**
     * Dapatkan informasi detail mengenai satu Juz tertentu (1 s/d 30).
     */
    public static function getJuzInfo(int $juz): ?array
    {
        return self::$juzCatalog[$juz] ?? null;
    }

    /**
     * Dapatkan seluruh metadata 30 Juz Al-Qur'an lengkap.
     */
    public static function getAllJuzMetadata(): array
    {
        return array_values(self::$juzCatalog);
    }
}
