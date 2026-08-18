<?php

namespace App\Enums\Mutabaah;

enum SupervisorType: string
{
    case Pembimbing = 'pembimbing';
    case WaliKelas = 'wali_kelas';
    case GuruPai = 'guru_pai';
    case GuruTahfizh = 'guru_tahfizh';
    case Musyrif = 'musyrif';
    case Musyrifah = 'musyrifah';
}
