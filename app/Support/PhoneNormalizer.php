<?php

namespace App\Support;

/**
 * Normalisasi nomor HP Indonesia secara terpusat.
 *
 * Semua lookup login yang berbasis nomor HP (users.phone, parents.phone,
 * employees.no_hp) harus melewati normalizer ini agar format berikut dapat
 * resolve ke akun yang sama:
 *
 *   0812xxxxxxx
 *   62812xxxxxxx
 *   +62812xxxxxxx
 *   0812-xxxx-xxxx
 */
final class PhoneNormalizer
{
    /**
     * Canonical E.164 tanpa leading "+": 628xxxxxxxx.
     *
     * @return string Selalu kembalikan string digit (kosong bila input tidak valid).
     */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    /**
     * Apakah string menyerupai nomor HP (semua digit, panjang 9-15)?
     * Dipakai untuk memutuskan apakah identifier perlu dicocokkan via kolom
     * phone terlebih dahulu, tanpa membuang NIS/NIY/NIK yang kebetulan numerik.
     */
    public static function isLikelyPhone(string $identifier): bool
    {
        return preg_match('/^[0-9+\s\-().]{9,16}$/', $identifier) === 1;
    }

    /**
     * Daftar varian yang harus dicocokkan ke kolom phone (raw, normal).
     * Mencakup bentuk awal, bentuk tanpa format, dan bentuk canonical.
     */
    public static function variants(string $phone): array
    {
        $raw = trim($phone);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        $canonical = static::normalize($raw);

        $list = array_values(array_filter([
            $raw,
            $digits,
            $canonical,
            $canonical !== '' ? '0'.substr($canonical, 2) : null,
        ], fn (?string $v) => $v !== null && $v !== ''));

        return array_values(array_unique($list));
    }
}
