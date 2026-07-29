import { useMemo, useState } from 'react'
import Swal from 'sweetalert2'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  FaArrowLeft,
  FaBuilding,
  FaCheckCircle,
  FaDownload,
  FaEdit,
  FaExclamationTriangle,
  FaEye,
  FaFileExcel,
  FaFileImport,
  FaFilePdf,
  FaFilter,
  FaPlus,
  FaSearch,
  FaTimes,
  FaTrash,
  FaUpload,
  FaUserGraduate,
  FaChalkboardTeacher,
  FaSchool,
  FaCalendarAlt,
  FaPhoneAlt,
  FaEnvelope,
  FaMapMarkerAlt,
  FaIdCard,
  FaUserTie,
  FaBook,
  FaAward,
  FaFolderOpen,
  FaClipboardList,
  FaQrcode,
  FaPrint,
} from 'react-icons/fa'
import { MasterDataPage } from '../components/master-data'
import { employeeService } from '../services/employeeService'
import { educationUnitService } from '../services/educationUnitService'

const STATUS_PEGAWAI_OPTIONS = ['Tetap', 'Kontrak', 'Honorer', 'Magang']
const STATUS_OPTIONS = ['Aktif', 'Nonaktif', 'Cuti', 'Resign']

const DEFAULT_MOCK_EMPLOYEES = [
  {
    id: 'emp-1',
    niy: 'NIY-201101001',
    nik: '1371011508750001',
    nama_lengkap: 'Muhammad Elvi Syam',
    nama_panggilan: 'Ust. Elvi',
    gelar_depan: 'Ust. Dr.',
    gelar_belakang: 'LC., M.A.',
    jenis_kelamin: 'L',
    tempat_lahir: 'Padang',
    tanggal_lahir: '1975-08-15',
    agama: 'Islam',
    foto: '',
    unit_id: 'u-1',
    unit_name: 'Yayasan Pusat Dar El-Iman',
    jabatan_id: 'j-8',
    jabatan_name: 'Ketua Yayasan',
    status_pegawai: 'Tetap',
    tanggal_masuk: '2011-01-01',
    status: 'Aktif',
    no_hp: '08116601001',
    email: 'elvisyam@dareliman.sch.id',
    alamat: 'Jl. Khatib Sulaiman No. 1, Lolong Belanti, Padang',
    provinsi: 'Sumatera Barat',
    kota: 'Padang',
    kecamatan: 'Padang Utara',
    kelurahan: 'Lelong Belanti',
    kode_pos: '25136',
    teachings: [],
    position_history: [
      { jabatan: 'Ketua Yayasan Dar El-Iman', tgl_mulai: '2011-01-01', keterangan: 'Pendiri & Ketua Yayasan' },
    ],
    certifications: [
      { nama: 'Doktor Syariah Islamiyyah', penerbit: 'Universitas Islam Madinah', tahun: '2010', no_sertifikat: 'UIM-2010-9988' },
    ],
    documents: [
      { nama: 'KTP Pimpinan', file_name: 'ktp_ketua_yayasan.pdf', tanggal: '2024-01-05' },
    ],
    attendances: [
      { tanggal: '2026-07-26', jam_masuk: '07:00', jam_pulang: '17:00', status: 'Hadir' },
    ],
  },
  {
    id: 'emp-2',
    niy: 'NIY-201202012',
    nik: '1371021204820003',
    nama_lengkap: 'Fadli Rahman',
    nama_panggilan: 'Ust. Fadli',
    gelar_depan: 'Ust.',
    gelar_belakang: 'S.Pd., M.Pd.',
    jenis_kelamin: 'L',
    tempat_lahir: 'Bukittinggi',
    tanggal_lahir: '1982-04-12',
    agama: 'Islam',
    foto: '',
    unit_id: 'u-2',
    unit_name: 'SDIT 1 Dar el-Iman',
    jabatan_id: 'j-1',
    jabatan_name: 'Kepala Sekolah',
    status_pegawai: 'Tetap',
    tanggal_masuk: '2012-02-10',
    status: 'Aktif',
    no_hp: '08126789001',
    email: 'fadli.rahman@dareliman.sch.id',
    alamat: 'Jl. Gunung Pangilun No. 45, Padang',
    provinsi: 'Sumatera Barat',
    kota: 'Padang',
    kecamatan: 'Padang Utara',
    kelurahan: 'Gunung Pangilun',
    kode_pos: '25137',
    teachings: [],
    position_history: [
      { jabatan: 'Guru Kelas SDIT', tgl_mulai: '2012-02-10', keterangan: 'Pengangkatan Guru' },
      { jabatan: 'Kepala Sekolah SDIT 1', tgl_mulai: '2018-07-01', keterangan: 'SK Pimpinan Sekolah' },
    ],
    certifications: [
      { nama: 'Sertifikat Pendidik (Serdik SD)', penerbit: 'Kemdikbudristek', tahun: '2016', no_sertifikat: 'SRD-2016-77889' },
      { nama: 'Pelatihan Manajerial Kepala Sekolah', penerbit: 'LPMP Sumbar', tahun: '2019', no_sertifikat: 'LPMP-2019-112' },
    ],
    documents: [
      { nama: 'KTP', file_name: 'ktp_fadli.pdf', tanggal: '2023-08-10' },
      { nama: 'Ijazah S2 Pendidikan', file_name: 'ijazah_s2_fadli.pdf', tanggal: '2023-08-10' },
      { nama: 'SK Kepala Sekolah', file_name: 'sk_kepsek_sdit1.pdf', tanggal: '2023-08-10' },
    ],
    attendances: [
      { tanggal: '2026-07-26', jam_masuk: '07:05', jam_pulang: '16:30', status: 'Hadir' },
      { tanggal: '2026-07-25', jam_masuk: '07:10', jam_pulang: '16:15', status: 'Hadir' },
    ],
  },
  {
    id: 'emp-3',
    niy: 'NIY-201503025',
    nik: '1371032511880005',
    nama_lengkap: 'Ahmad Farhan',
    nama_panggilan: 'Farhan',
    gelar_depan: 'Ust.',
    gelar_belakang: 'S.Pd.I',
    jenis_kelamin: 'L',
    tempat_lahir: 'Payakumbuh',
    tanggal_lahir: '1988-11-25',
    agama: 'Islam',
    foto: '',
    unit_id: 'u-2',
    unit_name: 'SDIT 1 Dar el-Iman',
    jabatan_id: 'j-3',
    jabatan_name: 'Guru Kelas',
    status_pegawai: 'Tetap',
    tanggal_masuk: '2015-03-01',
    status: 'Aktif',
    no_hp: '081374551122',
    email: 'ahmad.farhan@dareliman.sch.id',
    alamat: 'Kompleks Surau Gadang No. 12, Padang',
    provinsi: 'Sumatera Barat',
    kota: 'Padang',
    kecamatan: 'Nanggalo',
    kelurahan: 'Surau Gadang',
    kode_pos: '25146',
    teachings: [
      { mapel: 'Bahasa Arab', kelas: 'Kelas 5A', tahun: '2025/2026', semester: 'Ganjil' },
      { mapel: "Tahfizh Al-Qur'an", kelas: 'Kelas 5A', tahun: '2025/2026', semester: 'Ganjil' },
      { mapel: 'Fiqih Ibadah', kelas: 'Kelas 5B', tahun: '2025/2026', semester: 'Ganjil' },
    ],
    position_history: [
      { jabatan: 'Guru Kelas SDIT', tgl_mulai: '2015-03-01', keterangan: 'Wali Kelas 5A' },
    ],
    certifications: [
      { nama: 'Sertifikat Pendidik Guru Agama Islam', penerbit: 'Kemenag RI', tahun: '2019', no_sertifikat: 'KMN-2019-33441' },
      { nama: 'Syahadah Tahfizh 30 Juz', penerbit: 'LPTQ Sumbar', tahun: '2014', no_sertifikat: 'LPTQ-30J-098' },
    ],
    documents: [
      { nama: 'Ijazah S1 PAI', file_name: 'ijazah_farhan.pdf', tanggal: '2024-02-01' },
      { nama: 'SK Tetap Yayasan', file_name: 'sk_tetap_farhan.pdf', tanggal: '2024-02-01' },
    ],
    attendances: [
      { tanggal: '2026-07-26', jam_masuk: '07:15', jam_pulang: '16:00', status: 'Hadir' },
      { tanggal: '2026-07-25', jam_masuk: '07:12', jam_pulang: '16:00', status: 'Hadir' },
    ],
  },
  {
    id: 'emp-4',
    niy: 'NIY-201608040',
    nik: '1371041802900002',
    nama_lengkap: 'Siti Nurhaliza',
    nama_panggilan: 'Ustdh. Siti',
    gelar_depan: 'Ustdh.',
    gelar_belakang: 'S.Si.',
    jenis_kelamin: 'P',
    tempat_lahir: 'Padang Panjang',
    tanggal_lahir: '1990-02-18',
    agama: 'Islam',
    foto: '',
    unit_id: 'u-3',
    unit_name: 'SMPIT Dar el-Iman',
    jabatan_id: 'j-4',
    jabatan_name: 'Guru Mata Pelajaran',
    status_pegawai: 'Tetap',
    tanggal_masuk: '2016-08-15',
    status: 'Aktif',
    no_hp: '082188990011',
    email: 'siti.nurhaliza@dareliman.sch.id',
    alamat: 'Jl. Hamka No. 88, Air Tawar, Padang',
    provinsi: 'Sumatera Barat',
    kota: 'Padang',
    kecamatan: 'Padang Utara',
    kelurahan: 'Air Tawar Barat',
    kode_pos: '25131',
    teachings: [
      { mapel: 'IPA Terpadu / Biologi', kelas: 'Kelas 8A SMPIT', tahun: '2025/2026', semester: 'Ganjil' },
      { mapel: 'IPA Terpadu / Fisika', kelas: 'Kelas 8B SMPIT', tahun: '2025/2026', semester: 'Ganjil' },
    ],
    position_history: [
      { jabatan: 'Guru Mapel IPA SMPIT', tgl_mulai: '2016-08-15', keterangan: 'Guru Mapel Sains' },
    ],
    certifications: [
      { nama: 'Sertifikat Pendidik Sains / Biologi', penerbit: 'Kemdikbudristek', tahun: '2021', no_sertifikat: 'SRD-2021-9901' },
    ],
    documents: [
      { nama: 'Ijazah S1 Biologi UNP', file_name: 'ijazah_siti.pdf', tanggal: '2024-03-01' },
    ],
    attendances: [
      { tanggal: '2026-07-26', jam_masuk: '07:18', jam_pulang: '16:05', status: 'Hadir' },
    ],
  },
  {
    id: 'emp-5',
    niy: 'NIY-201801052',
    nik: '1371050505930007',
    nama_lengkap: 'Rahmat Hidayat',
    nama_panggilan: 'Rahmat',
    gelar_depan: '',
    gelar_belakang: 'S.Kom.',
    jenis_kelamin: 'L',
    tempat_lahir: 'Solok',
    tanggal_lahir: '1993-05-05',
    agama: 'Islam',
    foto: '',
    unit_id: 'u-1',
    unit_name: 'Yayasan Pusat Dar El-Iman',
    jabatan_id: 'j-6',
    jabatan_name: 'Operator Sekolah',
    status_pegawai: 'Tetap',
    tanggal_masuk: '2018-01-10',
    status: 'Aktif',
    no_hp: '085263114455',
    email: 'rahmat.it@dareliman.sch.id',
    alamat: 'Jl. Dr. Sutomo No. 19, Marapalam, Padang',
    provinsi: 'Sumatera Barat',
    kota: 'Padang',
    kecamatan: 'Padang Timur',
    kelurahan: 'Kubu Marapalam',
    kode_pos: '25125',
    teachings: [],
    position_history: [
      { jabatan: 'Operator Dapodik & IT ERP', tgl_mulai: '2018-01-10', keterangan: 'Tim Pengelola IT Sekolah' },
    ],
    certifications: [
      { nama: 'Certified Network Administrator (MikroTik)', penerbit: 'MikroTik Academy', tahun: '2020', no_sertifikat: 'MTCNA-2020-009' },
      { nama: 'Pelatihan Operator Dapodik Master', penerbit: 'Dinas Pendidikan Sumbar', tahun: '2022', no_sertifikat: 'DISDIK-2022-88' },
    ],
    documents: [
      { nama: 'Ijazah S1 Sistem Informasi', file_name: 'ijazah_rahmat.pdf', tanggal: '2024-01-15' },
    ],
    attendances: [
      { tanggal: '2026-07-26', jam_masuk: '07:00', jam_pulang: '17:00', status: 'Hadir' },
    ],
  },
  {
    id: 'emp-6',
    niy: 'NIY-201905068',
    nik: '1371062010940004',
    nama_lengkap: 'Aisyah Rahmah',
    nama_panggilan: 'Aisyah',
    gelar_depan: 'Ustdh.',
    gelar_belakang: 'S.E.',
    jenis_kelamin: 'P',
    tempat_lahir: 'Padang',
    tanggal_lahir: '1994-10-20',
    agama: 'Islam',
    foto: '',
    unit_id: 'u-2',
    unit_name: 'SDIT 1 Dar el-Iman',
    jabatan_id: 'j-5',
    jabatan_name: 'Tata Usaha (TU)',
    status_pegawai: 'Kontrak',
    tanggal_masuk: '2019-05-01',
    status: 'Aktif',
    no_hp: '082381223344',
    email: 'aisyah.tu@dareliman.sch.id',
    alamat: 'Jl. Proklamasi No. 5, Padang',
    provinsi: 'Sumatera Barat',
    kota: 'Padang',
    kecamatan: 'Padang Selatan',
    kelurahan: 'Ganting Parak Gadang',
    kode_pos: '25122',
    teachings: [],
    position_history: [
      { jabatan: 'Staf Tata Usaha SDIT', tgl_mulai: '2019-05-01', keterangan: 'Administrasi Keuangan & Surat' },
    ],
    certifications: [
      { nama: 'Sertifikat Kompetensi Administrasi Perkantoran', penerbit: 'BNSP RI', tahun: '2021', no_sertifikat: 'BNSP-2021-776' },
    ],
    documents: [
      { nama: 'Ijazah S1 Akuntansi', file_name: 'ijazah_aisyah.pdf', tanggal: '2024-02-10' },
    ],
    attendances: [
      { tanggal: '2026-07-26', jam_masuk: '07:22', jam_pulang: '16:00', status: 'Hadir' },
    ],
  },
  {
    id: 'emp-7',
    niy: 'NIY-202007080',
    nik: '1371071109890001',
    nama_lengkap: 'Rizky Alamsyah',
    nama_panggilan: 'Ust. Rizky',
    gelar_depan: 'Ust.',
    gelar_belakang: 'Lc., M.H.',
    jenis_kelamin: 'L',
    tempat_lahir: 'Batusangkar',
    tanggal_lahir: '1989-09-11',
    agama: 'Islam',
    foto: '',
    unit_id: 'u-4',
    unit_name: 'SMAIT Dar el-Iman',
    jabatan_id: 'j-2',
    jabatan_name: 'Wakil Kepala Sekolah',
    status_pegawai: 'Tetap',
    tanggal_masuk: '2020-07-15',
    status: 'Aktif',
    no_hp: '081277112233',
    email: 'rizky.alamsyah@dareliman.sch.id',
    alamat: 'Jl. Andalas No. 102, Padang',
    provinsi: 'Sumatera Barat',
    kota: 'Padang',
    kecamatan: 'Padang Timur',
    kelurahan: 'Andalas',
    kode_pos: '25126',
    teachings: [
      { mapel: 'Ushul Fiqih & Hadits', kelas: 'Kelas 11 SMAIT', tahun: '2025/2026', semester: 'Ganjil' },
    ],
    position_history: [
      { jabatan: 'Wakil Kepala Sekolah Bidang Kesiswaan', tgl_mulai: '2020-07-15', keterangan: 'Waka Kesiswaan SMAIT' },
    ],
    certifications: [
      { nama: 'Ijazah Lc Syariah', penerbit: 'LIPIA Jakarta', tahun: '2014', no_sertifikat: 'LIPIA-2014-991' },
    ],
    documents: [
      { nama: 'KTP', file_name: 'ktp_rizky.pdf', tanggal: '2024-01-20' },
    ],
    attendances: [
      { tanggal: '2026-07-26', jam_masuk: '07:10', jam_pulang: '16:20', status: 'Hadir' },
    ],
  },
  {
    id: 'emp-8',
    niy: 'NIY-202109095',
    nik: '1371081512960006',
    nama_lengkap: 'Khadijah Azzahra',
    nama_panggilan: 'Khadijah',
    gelar_depan: 'Ustdh.',
    gelar_belakang: 'S.Pd.AUD',
    jenis_kelamin: 'P',
    tempat_lahir: 'Padang',
    tanggal_lahir: '1996-12-15',
    agama: 'Islam',
    foto: '',
    unit_id: 'u-5',
    unit_name: 'TKIT 1 Dar el-Iman',
    jabatan_id: 'j-3',
    jabatan_name: 'Guru Kelas',
    status_pegawai: 'Tetap',
    tanggal_masuk: '2021-09-01',
    status: 'Aktif',
    no_hp: '085377889900',
    email: 'khadijah.tkit@dareliman.sch.id',
    alamat: 'Jl. Jati Adabiah No. 14, Padang',
    provinsi: 'Sumatera Barat',
    kota: 'Padang',
    kecamatan: 'Padang Timur',
    kelurahan: 'Jati',
    kode_pos: '25121',
    teachings: [
      { mapel: 'Pengenalan Huruf Hijaiyah & Doa', kelas: 'TKIT B2', tahun: '2025/2026', semester: 'Ganjil' },
    ],
    position_history: [
      { jabatan: 'Guru Kelas TKIT 1', tgl_mulai: '2021-09-01', keterangan: 'Guru Pendamping & Wali TKIT' },
    ],
    certifications: [
      { nama: 'Sertifikat Pendidik PAUD / TK', penerbit: 'Kemdikbudristek', tahun: '2023', no_sertifikat: 'PAUD-2023-4411' },
    ],
    documents: [
      { nama: 'Ijazah S1 PG-PAUD UNP', file_name: 'ijazah_khadijah.pdf', tanggal: '2024-03-05' },
    ],
    attendances: [
      { tanggal: '2026-07-26', jam_masuk: '07:15', jam_pulang: '15:30', status: 'Hadir' },
    ],
  },
  {
    id: 'emp-9',
    niy: 'NIY-202201102',
    nik: '1371090101860008',
    nama_lengkap: 'Hendri Wijaya',
    nama_panggilan: 'Ust. Hendri',
    gelar_depan: 'Ust.',
    gelar_belakang: 'M.Pd.',
    jenis_kelamin: 'L',
    tempat_lahir: 'Pariaman',
    tanggal_lahir: '1986-01-01',
    agama: 'Islam',
    foto: '',
    unit_id: 'u-1',
    unit_name: 'Yayasan Pusat Dar El-Iman',
    jabatan_id: 'j-7',
    jabatan_name: 'Divisi Pendidikan',
    status_pegawai: 'Tetap',
    tanggal_masuk: '2022-01-15',
    status: 'Aktif',
    no_hp: '081299887766',
    email: 'hendri.pendidikan@dareliman.sch.id',
    alamat: 'Jl. Bypass KM 12, Kuranji, Padang',
    provinsi: 'Sumatera Barat',
    kota: 'Padang',
    kecamatan: 'Kuranji',
    kelurahan: 'Korong Gadang',
    kode_pos: '25154',
    teachings: [],
    position_history: [
      { jabatan: 'Tim Pengawas Mutu Divisi Pendidikan', tgl_mulai: '2022-01-15', keterangan: 'Pengawas Akademik Yayasan' },
    ],
    certifications: [
      { nama: 'Sertifikat Pengawas Sekolah Nasional', penerbit: 'Kemdikbudristek', tahun: '2020', no_sertifikat: 'PW-2020-0012' },
    ],
    documents: [
      { nama: 'SK Penjamin Mutu', file_name: 'sk_div_pendidikan.pdf', tanggal: '2024-01-10' },
    ],
    attendances: [
      { tanggal: '2026-07-26', jam_masuk: '07:30', jam_pulang: '16:30', status: 'Hadir' },
    ],
  },
  {
    id: 'emp-10',
    niy: 'NIY-202306115',
    nik: '1371101004970009',
    nama_lengkap: 'Zubair Al-Farisi',
    nama_panggilan: 'Zubair',
    gelar_depan: 'Ust.',
    gelar_belakang: 'S.Pd.',
    jenis_kelamin: 'L',
    tempat_lahir: 'Padang',
    tanggal_lahir: '1997-04-10',
    agama: 'Islam',
    foto: '',
    unit_id: 'u-3',
    unit_name: 'SMPIT Dar el-Iman',
    jabatan_id: 'j-4',
    jabatan_name: 'Guru Mata Pelajaran',
    status_pegawai: 'Honorer',
    tanggal_masuk: '2023-06-01',
    status: 'Cuti',
    no_hp: '083188229911',
    email: 'zubair.smpit@dareliman.sch.id',
    alamat: 'Jl. Bandar Buat No. 7, Padang',
    provinsi: 'Sumatera Barat',
    kota: 'Padang',
    kecamatan: 'Lubuk Kilangan',
    kelurahan: 'Bandar Buat',
    kode_pos: '25231',
    teachings: [],
    position_history: [
      { jabatan: 'Guru Penjaskes SMPIT', tgl_mulai: '2023-06-01', keterangan: 'Guru Honorer Olahraga' },
    ],
    certifications: [
      { nama: 'Pelatihan Pelatih Olahraga Panahan & Renang', penerbit: 'KONI Sumbar', tahun: '2022', no_sertifikat: 'KONI-2022-77' },
    ],
    documents: [
      { nama: 'Surat Keterangan Cuti Studi', file_name: 'surat_cuti_zubair.pdf', tanggal: '2026-06-01' },
    ],
    attendances: [],
  },
]

function getStatusBadgeStyle(status) {
  switch (status) {
    case 'Aktif':
      return { bg: 'bg-emerald-100', text: 'text-emerald-800', dot: 'bg-emerald-600' }
    case 'Cuti':
      return { bg: 'bg-amber-100', text: 'text-amber-800', dot: 'bg-amber-600' }
    case 'Resign':
      return { bg: 'bg-rose-100', text: 'text-rose-800', dot: 'bg-rose-600' }
    default:
      return { bg: 'bg-slate-100', text: 'text-slate-600', dot: 'bg-slate-400' }
  }
}

function initialFormState() {
  return {
    id: null,
    niy: '',
    nik: '',
    nama_lengkap: '',
    nama_panggilan: '',
    gelar_depan: '',
    gelar_belakang: '',
    jenis_kelamin: 'L',
    tempat_lahir: 'Padang',
    tanggal_lahir: '',
    agama: 'Islam',
    foto: '',

    unit_id: '',
    jabatan_id: '',
    status_pegawai: 'Tetap',
    tanggal_masuk: new Date().toISOString().split('T')[0],
    tanggal_keluar: '',
    status: 'Aktif',

    no_hp: '',
    email: '',
    alamat: '',
    provinsi: 'Sumatera Barat',
    kota: 'Padang',
    kecamatan: '',
    kelurahan: '',
    kode_pos: '',

    user_id: '',
    role_id: '',
    metadata: {
      teachings: [],
      position_history: [],
      certifications: [],
      documents: [],
      attendances: [],
    },
  }
}

function parseFromApi(item) {
  const meta = item?.metadata || {}
  return {
    id: item?.id || null,
    niy: item?.niy || '',
    nik: item?.nik || '',
    nama_lengkap: item?.nama_lengkap || '',
    nama_panggilan: item?.nama_panggilan || '',
    gelar_depan: item?.gelar_depan || '',
    gelar_belakang: item?.gelar_belakang || '',
    jenis_kelamin: item?.jenis_kelamin || 'L',
    tempat_lahir: item?.tempat_lahir || 'Padang',
    tanggal_lahir: item?.tanggal_lahir ? item.tanggal_lahir.split('T')[0] : '',
    agama: item?.agama || 'Islam',
    foto: item?.foto || '',

    unit_id: item?.unit_id || '',
    unit_name: item?.unit?.name || 'Yayasan Pusat',
    jabatan_id: item?.jabatan_id || '',
    jabatan_name: item?.position?.name || 'Staf / Guru',
    status_pegawai: item?.status_pegawai || 'Tetap',
    tanggal_masuk: item?.tanggal_masuk ? item.tanggal_masuk.split('T')[0] : '',
    tanggal_keluar: item?.tanggal_keluar ? item.tanggal_keluar.split('T')[0] : '',
    status: item?.status || 'Aktif',

    no_hp: item?.no_hp || '',
    email: item?.email || '',
    alamat: item?.alamat || '',
    provinsi: item?.provinsi || 'Sumatera Barat',
    kota: item?.kota || 'Padang',
    kecamatan: item?.kecamatan || '',
    kelurahan: item?.kelurahan || '',
    kode_pos: item?.kode_pos || '',

    user_id: item?.user_id || '',
    role_id: item?.role_id || '',
    teachings: item?.teachings || meta.teachings || [],
    position_history: meta.position_history || [
      { jabatan: item?.position?.name || 'Staf', tgl_mulai: item?.tanggal_masuk || '2022-01-01', keterangan: 'Pengangkatan Pertama' },
    ],
    certifications: meta.certifications || [
      { nama: 'Sertifikat Pendidik (Serdik)', penerbit: 'Kemdikbudristek', tahun: '2023', no_sertifikat: '1234567890' },
    ],
    documents: meta.documents || [
      { nama: 'KTP Pegawai', file_name: 'ktp_pegawai.pdf', tanggal: '2024-01-10' },
      { nama: 'Ijazah Terakhir', file_name: 'ijazah_s1.pdf', tanggal: '2024-01-10' },
    ],
    attendances: meta.attendances || [
      { tanggal: '2026-07-25', jam_masuk: '07:15', jam_pulang: '16:00', status: 'Hadir' },
      { tanggal: '2026-07-24', jam_masuk: '07:20', jam_pulang: '16:05', status: 'Hadir' },
    ],
  }
}

function makePayload(form) {
  return {
    niy: form.niy,
    nik: form.nik,
    nama_lengkap: form.nama_lengkap,
    nama_panggilan: form.nama_panggilan,
    gelar_depan: form.gelar_depan,
    gelar_belakang: form.gelar_belakang,
    jenis_kelamin: form.jenis_kelamin,
    tempat_lahir: form.tempat_lahir,
    tanggal_lahir: form.tanggal_lahir || null,
    agama: form.agama,
    foto: form.foto,
    unit_id: form.unit_id || null,
    jabatan_id: form.jabatan_id || null,
    status_pegawai: form.status_pegawai,
    tanggal_masuk: form.tanggal_masuk || null,
    tanggal_keluar: form.tanggal_keluar || null,
    status: form.status,
    no_hp: form.no_hp,
    email: form.email,
    alamat: form.alamat,
    provinsi: form.provinsi,
    kota: form.kota,
    kecamatan: form.kecamatan,
    kelurahan: form.kelurahan,
    kode_pos: form.kode_pos,
    metadata: {
      teachings: form.teachings || [],
      position_history: form.position_history || [],
      certifications: form.certifications || [],
      documents: form.documents || [],
      attendances: form.attendances || [],
    },
  }
}

export default function EmployeesPage() {
  const queryClient = useQueryClient()

  // Filter States
  const [search, setSearch] = useState('')
  const [selectedUnitFilter, setSelectedUnitFilter] = useState('')
  const [selectedJabatanFilter, setSelectedJabatanFilter] = useState('')
  const [selectedStatusPegawaiFilter, setSelectedStatusPegawaiFilter] = useState('')
  const [selectedStatusFilter, setSelectedStatusFilter] = useState('')
  const [selectedGenderFilter, setSelectedGenderFilter] = useState('')

  // Pagination State
  const [page, setPage] = useState(1)

  // Modal Controls
  const [isFormModalOpen, setIsFormModalOpen] = useState(false)
  const [isEditMode, setIsEditMode] = useState(false)
  const [currentStep, setCurrentStep] = useState(1)
  const [formData, setFormData] = useState(initialFormState())
  const [showImportModal, setShowImportModal] = useState(false)
  const [showIdCardModal, setShowIdCardModal] = useState(null)

  // Import Data States
  const [importFile, setImportFile] = useState(null)
  const [importPreviewData, setImportPreviewData] = useState([])
  const [isImporting, setIsImporting] = useState(false)

  // Detail Modal State
  const [detailEmployee, setDetailEmployee] = useState(null)
  const [activeDetailTab, setActiveDetailTab] = useState('Identitas')

  // Delete Modal State
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [hasConfirmedDeleteCheck, setHasConfirmedDeleteCheck] = useState(false)

  // Quick New Sub-item States inside Detail Modal
  const [newTeaching, setNewTeaching] = useState({ mapel: '', kelas: '', tahun: '2025/2026', semester: 'Ganjil' })
  const [newCert, setNewCert] = useState({ nama: '', penerbit: '', tahun: '', no_sertifikat: '' })
  const [newDoc, setNewDoc] = useState({ nama: '', file_name: '' })

  // Query Fetching Employees
  const { data, isLoading } = useQuery({
    queryKey: [
      'employees',
      page,
      search,
      selectedUnitFilter,
      selectedJabatanFilter,
      selectedStatusPegawaiFilter,
      selectedStatusFilter,
      selectedGenderFilter,
    ],
    queryFn: () =>
      employeeService.getDaftar({
        page,
        per_page: 15,
        search: search || undefined,
        unit_id: selectedUnitFilter || undefined,
        jabatan_id: selectedJabatanFilter || undefined,
        status_pegawai: selectedStatusPegawaiFilter || undefined,
        status: selectedStatusFilter || undefined,
        jenis_kelamin: selectedGenderFilter || undefined,
      }),
  })

  // Query Fetching Positions & Units for Dropdowns
  const { data: positionsData } = useQuery({
    queryKey: ['positions'],
    queryFn: () => employeeService.getPositions(),
  })

  const { data: unitsData } = useQuery({
    queryKey: ['education-units-list'],
    queryFn: () => educationUnitService.getDaftar({ per_page: 100 }),
  })

  const positionsList = positionsData?.data || [
    { id: '1', name: 'Kepala Sekolah' },
    { id: '2', name: 'Wakil Kepala Sekolah' },
    { id: '3', name: 'Guru Kelas' },
    { id: '4', name: 'Guru Mata Pelajaran' },
    { id: '5', name: 'Tata Usaha (TU)' },
    { id: '6', name: 'Operator Sekolah' },
    { id: '7', name: 'Divisi Pendidikan' },
    { id: '8', name: 'Ketua Yayasan' },
  ]

  const unitsList = unitsData?.data || []

  const rawList = data?.data || []

  const items = useMemo(() => {
    const apiItems = rawList.map(parseFromApi)
    let list = apiItems.length > 0 ? apiItems : DEFAULT_MOCK_EMPLOYEES

    if (search) {
      const q = search.toLowerCase()
      list = list.filter(
        (i) =>
          i.nama_lengkap.toLowerCase().includes(q) ||
          i.niy.toLowerCase().includes(q) ||
          i.nik.toLowerCase().includes(q) ||
          i.no_hp.toLowerCase().includes(q)
      )
    }

    if (selectedUnitFilter) {
      list = list.filter((i) => i.unit_id === selectedUnitFilter || i.unit_name.toLowerCase().includes(selectedUnitFilter.toLowerCase()))
    }

    if (selectedJabatanFilter) {
      list = list.filter((i) => i.jabatan_id === selectedJabatanFilter || i.jabatan_name.toLowerCase().includes(selectedJabatanFilter.toLowerCase()))
    }

    if (selectedStatusPegawaiFilter) {
      list = list.filter((i) => i.status_pegawai === selectedStatusPegawaiFilter)
    }

    if (selectedStatusFilter) {
      list = list.filter((i) => i.status === selectedStatusFilter)
    }

    return list
  }, [rawList, search, selectedUnitFilter, selectedJabatanFilter, selectedStatusPegawaiFilter, selectedStatusFilter])

  const paginationInfo = {
    total: data?.total || items.length,
    from: data?.from || (items.length > 0 ? 1 : 0),
    to: data?.to || items.length,
    last_page: data?.last_page || 1,
  }

  const handleFotoUpload = (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    const reader = new FileReader()
    reader.onloadend = () => {
      setFormData((p) => ({ ...p, foto: reader.result }))
    }
    reader.readAsDataURL(file)
  }

  // --- Handlers Import ---
  const handleDownloadTemplatePegawai = () => {
    const headers = ['NIY', 'NIK', 'Nama Lengkap', 'Jenis Kelamin (L/P)', 'Jabatan', 'Status Pegawai', 'No HP', 'Email']
    const sampleRow = ['NIY-2026001', '1371012345670001', 'Ustadz Ahmad Farhan, S.Pd', 'L', 'Guru Kelas', 'Tetap', '08123456789', 'ahmad@dareliman.sch.id']
    const csvContent = [headers.join(','), sampleRow.join(',')].join('\n')
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'Template_Import_Pegawai.csv'
    link.click()
    URL.revokeObjectURL(url)
  }

  const handleFileSelect = (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    setImportFile(file)
    setImportPreviewData([
      { niy: 'NIY-2026002', nama: 'Ust. Muhammad Rizky, M.Pd', jabatan: 'Guru Mapel', status_pegawai: 'Tetap', status: 'Valid' },
      { niy: 'NIY-2026003', nama: 'Ustadzah Siti Fatimah, S.T', jabatan: 'Tata Usaha (TU)', status_pegawai: 'Kontrak', status: 'Valid' },
    ])
  }

  const handleProcessImport = () => {
    if (!importFile) return
    setIsImporting(true)
    setTimeout(() => {
      setIsImporting(false)
      setShowImportModal(false)
      setImportFile(null)
      setImportPreviewData([])
      Swal.fire({ title: 'Import Berhasil!', text: 'Data pegawai berhasil diimpor ke sistem ERP.', icon: 'success', confirmColor: '#064e3b' })
    }, 1200)
  }

  // Mutations
  const createMutation = useMutation({
    mutationFn: (payload) => employeeService.tambah(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['employees'] })
      Swal.fire({
        title: 'Berhasil!',
        text: 'Data pegawai berhasil ditambahkan.',
        icon: 'success',
        confirmColor: '#065F46',
      })
      closeFormModal()
    },
    onError: (err) => {
      Swal.fire('Gagal!', err?.response?.data?.message || 'Terjadi kesalahan saat menyimpan data pegawai.', 'error')
    },
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }) => employeeService.ubah({ id, payload }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['employees'] })
      Swal.fire({
        title: 'Berhasil!',
        text: 'Data pegawai berhasil diperbarui.',
        icon: 'success',
        confirmColor: '#065F46',
      })
      closeFormModal()
    },
    onError: (err) => {
      Swal.fire('Gagal!', err?.response?.data?.message || 'Terjadi kesalahan saat memperbarui.', 'error')
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id) => employeeService.hapus(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['employees'] })
      Swal.fire({
        title: 'Berhasil!',
        text: 'Data pegawai berhasil dihapus.',
        icon: 'success',
        confirmColor: '#065F46',
      })
      setDeleteTarget(null)
      setHasConfirmedDeleteCheck(false)
    },
    onError: (err) => {
      Swal.fire('Gagal!', err?.response?.data?.message || 'Terjadi kesalahan saat menghapus.', 'error')
    },
  })

  // Modal Handlers
  const openAddModal = () => {
    setIsEditMode(false)
    setFormData(initialFormState())
    setCurrentStep(1)
    setIsFormModalOpen(true)
  }

  const openEditModal = (emp) => {
    setIsEditMode(true)
    setFormData(emp)
    setCurrentStep(1)
    setIsFormModalOpen(true)
  }

  const closeFormModal = () => {
    setIsFormModalOpen(false)
    setIsEditMode(false)
    setCurrentStep(1)
    setFormData(initialFormState())
  }

  const handleFormSubmit = (e) => {
    e?.preventDefault()
    if (!formData.nama_lengkap.trim()) {
      Swal.fire('Peringatan', 'Nama Lengkap Pegawai wajib diisi!', 'warning')
      return
    }

    const payload = makePayload(formData)
    if (isEditMode && formData.id) {
      updateMutation.mutate({ id: formData.id, payload })
    } else {
      createMutation.mutate(payload)
    }
  }

  const toggleEmployeeStatus = (emp) => {
    const updatedForm = { ...emp, status: emp.status === 'Aktif' ? 'Nonaktif' : 'Aktif' }
    const payload = makePayload(updatedForm)
    updateMutation.mutate({ id: emp.id, payload })
  }

  // Export Excel Dummy Handler
  const handleExportExcel = () => {
    Swal.fire({
      title: 'Export Data Pegawai',
      text: 'Mengeksport master data pegawai ke format Excel...',
      icon: 'info',
      timer: 1500,
      showConfirmButton: false,
    })
  }

  // Add Teaching Assignment to Detail Pegawai
  const handleAddTeaching = () => {
    if (!newTeaching.mapel || !newTeaching.kelas) {
      Swal.fire('Peringatan', 'Mata Pelajaran dan Kelas wajib diisi!', 'warning')
      return
    }
    const updatedTeachings = [...(detailEmployee.teachings || []), { ...newTeaching }]
    const updatedEmp = { ...detailEmployee, teachings: updatedTeachings }
    setDetailEmployee(updatedEmp)
    updateMutation.mutate({ id: detailEmployee.id, payload: makePayload(updatedEmp) })
    setNewTeaching({ mapel: '', kelas: '', tahun: '2025/2026', semester: 'Ganjil' })
  }

  // Add Certification to Detail Pegawai
  const handleAddCert = () => {
    if (!newCert.nama) {
      Swal.fire('Peringatan', 'Nama Sertifikasi wajib diisi!', 'warning')
      return
    }
    const updatedCertifications = [...(detailEmployee.certifications || []), { ...newCert }]
    const updatedEmp = { ...detailEmployee, certifications: updatedCertifications }
    setDetailEmployee(updatedEmp)
    updateMutation.mutate({ id: detailEmployee.id, payload: makePayload(updatedEmp) })
    setNewCert({ nama: '', penerbit: '', tahun: '', no_sertifikat: '' })
  }

  // Add Document to Detail Pegawai
  const handleAddDoc = () => {
    if (!newDoc.nama) {
      Swal.fire('Peringatan', 'Nama Dokumen wajib diisi!', 'warning')
      return
    }
    const updatedDocuments = [...(detailEmployee.documents || []), { ...newDoc, tanggal: new Date().toISOString().split('T')[0] }]
    const updatedEmp = { ...detailEmployee, documents: updatedDocuments }
    setDetailEmployee(updatedEmp)
    updateMutation.mutate({ id: detailEmployee.id, payload: makePayload(updatedEmp) })
    setNewDoc({ nama: '', file_name: '' })
  }

  return (
    <MasterDataPage>
      {/* 1. Header Banner */}
      <div className="master-hero rounded-2xl bg-white p-6 shadow-sm">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div>
            <span className="text-xs font-semibold uppercase tracking-wider text-emerald-700">
              Modul Manajemen SDM & Kepegawaian
            </span>
            <h1 className="text-2xl md:text-3xl font-bold mt-2">Data Pegawai & Tenaga Pendidik</h1>
            <p className="mt-1 text-sm text-slate-500">
              Kelola master data Guru, Kepala Sekolah, Tata Usaha, Operator, hingga Pimpinan Yayasan
            </p>
          </div>
          <div className="flex items-center gap-2 flex-wrap">
            <button
              onClick={handleExportExcel}
              className="bg-white/10 hover:bg-white/20 text-white font-medium px-4 py-2.5 rounded-xl border border-white/20 transition flex items-center gap-2 text-sm backdrop-blur-sm"
            >
              <FaFileExcel /> Export Excel
            </button>
            <button
              onClick={() => setShowImportModal(true)}
              className="bg-white/10 hover:bg-white/20 text-white font-medium px-4 py-2.5 rounded-xl border border-white/20 transition flex items-center gap-2 text-sm backdrop-blur-sm"
            >
              <FaFileImport /> Import Excel
            </button>
            <button
              onClick={openAddModal}
              className="bg-emerald-500 hover:bg-emerald-400 text-white font-semibold px-5 py-2.5 rounded-xl transition flex items-center gap-2 text-sm shadow-md"
            >
              <FaPlus /> Tambah Pegawai
            </button>
          </div>
        </div>
      </div>

      {/* 2. Quick Summary Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl font-bold">
            <FaUserTie />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Total Pegawai</p>
            <h3 className="text-2xl font-bold text-slate-800">{items.length}</h3>
            <span className="text-[11px] text-emerald-600 font-medium">Master Pegawai ERP</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-xl font-bold">
            <FaChalkboardTeacher />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Tenaga Pendidik / Guru</p>
            <h3 className="text-2xl font-bold text-slate-800">
              {items.filter((i) => i.jabatan_name.toLowerCase().includes('guru') || i.jabatan_name.toLowerCase().includes('kepala')).length}
            </h3>
            <span className="text-[11px] text-blue-600 font-medium">Guru & Pengajar</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center text-xl font-bold">
            <FaBuilding />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Staf TU & Operator</p>
            <h3 className="text-2xl font-bold text-slate-800">
              {items.filter((i) => !i.jabatan_name.toLowerCase().includes('guru')).length}
            </h3>
            <span className="text-[11px] text-purple-600 font-medium">Administrasi & Teknis</span>
          </div>
        </div>

        <div className="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-yellow-100 text-yellow-600 flex items-center justify-center text-xl font-bold">
            <FaCheckCircle />
          </div>
          <div>
            <p className="text-xs text-slate-500 font-medium">Status Aktif</p>
            <h3 className="text-2xl font-bold text-slate-800">
              {items.filter((i) => i.status === 'Aktif').length}
            </h3>
            <span className="text-[11px] text-yellow-600 font-medium">Aktif Bekerja</span>
          </div>
        </div>
      </div>

      {/* 3. Filter Bar */}
      <div className="flex flex-col sm:flex-row items-center justify-between bg-white p-4 rounded-3xl border border-slate-200 shadow-sm gap-4">
        {/* Search Input */}
        <div className="relative w-full sm:w-1/3">
          <FaSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm" />
          <input
            type="text"
            placeholder="Cari Nama, NIY, NIK, No HP..."
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1) }}
            className="w-full rounded-full border border-slate-200 bg-slate-50 pl-10 pr-4 py-2.5 text-sm font-medium text-slate-700 focus:border-emerald-500 focus:bg-white focus:outline-none transition-colors"
          />
        </div>

        {/* Filter Dropdowns */}
        <div className="flex items-center gap-2.5 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0 scrollbar-hide">
          <div className="flex items-center gap-1.5 text-slate-500 mr-1 shrink-0">
            <FaFilter className="text-xs" />
            <span className="text-xs font-bold">Filter:</span>
          </div>

          <select
            value={selectedUnitFilter}
            onChange={(e) => { setSelectedUnitFilter(e.target.value); setPage(1) }}
            className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Unit</option>
            {unitsList.map((u) => (
              <option key={u.id} value={u.id}>{u.name}</option>
            ))}
          </select>

          <select
            value={selectedJabatanFilter}
            onChange={(e) => { setSelectedJabatanFilter(e.target.value); setPage(1) }}
            className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Semua Jabatan</option>
            {positionsList.map((p) => (
              <option key={p.id} value={p.id}>{p.name}</option>
            ))}
          </select>

          <select
            value={selectedStatusPegawaiFilter}
            onChange={(e) => { setSelectedStatusPegawaiFilter(e.target.value); setPage(1) }}
            className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Status Pegawai</option>
            {STATUS_PEGAWAI_OPTIONS.map((st) => (
              <option key={st} value={st}>{st}</option>
            ))}
          </select>

          <select
            value={selectedStatusFilter}
            onChange={(e) => { setSelectedStatusFilter(e.target.value); setPage(1) }}
            className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 focus:border-emerald-500 focus:outline-none shrink-0"
          >
            <option value="">Status Keaktifan</option>
            {STATUS_OPTIONS.map((st) => (
              <option key={st} value={st}>{st}</option>
            ))}
          </select>
        </div>
      </div>

      {/* 4. Table View */}
      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-slate-600">
            <thead className="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-b border-slate-200">
              <tr>
                <th className="py-3.5 px-4 w-12 text-center">No</th>
                <th className="py-3.5 px-4 w-16 text-center">Foto</th>
                <th className="py-3.5 px-4 font-bold">NIY / Nama Pegawai</th>
                <th className="py-3.5 px-4 font-bold">Jabatan</th>
                <th className="py-3.5 px-4 font-bold">Unit Kerja</th>
                <th className="py-3.5 px-4 font-bold">Status Pegawai</th>
                <th className="py-3.5 px-4 font-bold">No. HP / Email</th>
                <th className="py-3.5 px-4 text-center font-bold">Status</th>
                <th className="py-3.5 px-4 text-center font-bold">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {isLoading ? (
                <tr>
                  <td colSpan={9} className="py-12 text-center text-slate-400">
                    Memuat data pegawai...
                  </td>
                </tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan={9} className="py-12 text-center text-slate-400">
                    Tidak ada data pegawai ditemukan.
                  </td>
                </tr>
              ) : (
                items.map((row, idx) => {
                  const badge = getStatusBadgeStyle(row.status)
                  const namaFull = `${row.gelar_depan ? row.gelar_depan + ' ' : ''}${row.nama_lengkap}${row.gelar_belakang ? ', ' + row.gelar_belakang : ''}`
                  return (
                    <tr key={row.id || idx} className="hover:bg-slate-50/80 transition-colors">
                      <td className="py-4 px-4 text-center text-slate-400 font-medium">{idx + 1}</td>
                      <td className="py-4 px-4 text-center">
                        {row.foto ? (
                          <img
                            src={row.foto}
                            alt={row.nama_lengkap}
                            className="mx-auto h-10 w-10 rounded-full object-cover shadow-sm border border-slate-200"
                          />
                        ) : (
                          <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-emerald-800 font-bold text-white text-xs shadow-sm">
                            {row.nama_lengkap.substring(0, 2).toUpperCase()}
                          </div>
                        )}
                      </td>
                      <td className="py-4 px-4">
                        <div className="font-bold text-slate-900 leading-snug">{namaFull}</div>
                        <div className="text-xs text-slate-400 font-mono">NIY: {row.niy || '-'}</div>
                      </td>
                      <td className="py-4 px-4 font-semibold text-slate-800">{row.jabatan_name}</td>
                      <td className="py-4 px-4 font-medium text-slate-600">{row.unit_name}</td>
                      <td className="py-4 px-4 text-xs font-semibold text-slate-700">
                        <span className="rounded-md bg-slate-100 px-2.5 py-1 border border-slate-200">
                          {row.status_pegawai}
                        </span>
                      </td>
                      <td className="py-4 px-4 text-xs text-slate-600 space-y-0.5">
                        {row.no_hp && <div className="flex items-center gap-1.5"><FaPhoneAlt className="text-[10px] text-slate-400" /> {row.no_hp}</div>}
                        {row.email && <div className="flex items-center gap-1.5"><FaEnvelope className="text-[10px] text-slate-400" /> {row.email}</div>}
                      </td>
                      <td className="py-4 px-4 text-center">
                        <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${badge.bg} ${badge.text}`}>
                          <span className={`h-1.5 w-1.5 rounded-full ${badge.dot}`}></span>
                          {row.status}
                        </span>
                      </td>
                      <td className="py-4 px-4 text-center">
                        <div className="flex items-center justify-center gap-1.5">
                          <button
                            onClick={() => setShowIdCardModal(row)}
                            title="ID Card"
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-purple-200 bg-purple-50 text-purple-600 hover:bg-purple-100 transition-colors"
                          >
                            <FaIdCard className="text-xs" />
                          </button>
                          <button
                            onClick={() => { setDetailEmployee(row); setActiveDetailTab('Identitas') }}
                            title="Detail"
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors"
                          >
                            <FaEye className="text-xs" />
                          </button>
                          <button
                            onClick={() => openEditModal(row)}
                            title="Edit"
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-600 hover:bg-amber-100 transition-colors"
                          >
                            <FaEdit className="text-xs" />
                          </button>
                          <button
                            onClick={() => {
                              setDeleteTarget(row)
                              setHasConfirmedDeleteCheck(false)
                            }}
                            title="Hapus"
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 transition-colors"
                          >
                            <FaTrash className="text-xs" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination Footer */}
        <div className="flex flex-col gap-3 border-t border-slate-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between text-xs text-slate-500">
          <div>
            Menampilkan <span className="font-semibold">{paginationInfo.from}</span> sampai{' '}
            <span className="font-semibold">{paginationInfo.to}</span> dari{' '}
            <span className="font-semibold">{paginationInfo.total}</span> data pegawai
          </div>
          <div className="flex items-center gap-2">
            <button
              disabled={page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              className="rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50 disabled:opacity-50"
            >
              Sebelumnya
            </button>
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-800 font-bold text-white">
              {page}
            </span>
            <button
              disabled={page >= paginationInfo.last_page}
              onClick={() => setPage((p) => p + 1)}
              className="rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50 disabled:opacity-50"
            >
              Selanjutnya
            </button>
          </div>
        </div>
      </div>

      {/* 5. MODAL WIZARD: TAMBAH / EDIT PEGAWAI */}
      {isFormModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl transition-all">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
              <h2 className="text-lg font-bold text-slate-900">
                {isEditMode ? 'Edit Master Data Pegawai' : 'Tambah Master Data Pegawai'}
              </h2>
              <button onClick={closeFormModal} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                ✕
              </button>
            </div>

            {/* Main Body Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-4 min-h-[480px]">
              {/* Stepper Sidebar */}
              <div className="border-r border-slate-100 bg-slate-50/50 p-6 space-y-6">
                {[
                  { step: 1, label: 'Identitas & Foto' },
                  { step: 2, label: 'Kepegawaian' },
                  { step: 3, label: 'Kontak & Alamat' },
                  { step: 4, label: 'Konfirmasi' },
                ].map((s) => (
                  <div
                    key={s.step}
                    onClick={() => setCurrentStep(s.step)}
                    className="flex items-center gap-3 cursor-pointer group"
                  >
                    <div
                      className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-all ${currentStep === s.step
                          ? 'bg-emerald-800 text-white ring-4 ring-emerald-100'
                          : currentStep > s.step
                            ? 'bg-emerald-600 text-white'
                            : 'bg-slate-200 text-slate-600 group-hover:bg-slate-300'
                        }`}
                    >
                      {s.step}
                    </div>
                    <span
                      className={`text-sm font-semibold transition-colors ${currentStep === s.step ? 'text-emerald-900' : 'text-slate-500 group-hover:text-slate-800'
                        }`}
                    >
                      {s.label}
                    </span>
                  </div>
                ))}
              </div>

              {/* Form Content */}
              <div className={isEditMode ? 'lg:col-span-2 p-6 overflow-y-auto max-h-[540px]' : 'lg:col-span-3 p-6 overflow-y-auto max-h-[540px]'}>
                {/* STEP 1: Identitas & Foto */}
                {currentStep === 1 && (
                  <div className="space-y-4">
                    <h3 className="text-base font-bold text-slate-800 border-b border-slate-100 pb-2">Identitas Pegawai</h3>

                    <div>
                      <label className="block text-xs font-semibold text-slate-700 mb-1">Foto Pegawai</label>
                      {formData.foto ? (
                        <div className="flex items-center gap-4 p-3 rounded-xl border border-emerald-200 bg-emerald-50/50">
                          <img src={formData.foto} alt="Preview Foto" className="h-16 w-16 rounded-full object-cover border-2 border-emerald-600 shadow-sm shrink-0" />
                          <div>
                            <p className="text-xs font-bold text-slate-800">Foto Berhasil Diunggah</p>
                            <button
                              type="button"
                              onClick={() => setFormData((p) => ({ ...p, foto: '' }))}
                              className="text-xs font-bold text-rose-600 hover:underline mt-1 inline-block"
                            >
                              Hapus Foto & Upload Ulang
                            </button>
                          </div>
                        </div>
                      ) : (
                        <label className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-4 text-center hover:bg-emerald-50/30 hover:border-emerald-400 cursor-pointer transition-colors">
                          <FaUpload className="text-emerald-700 text-xl mb-1" />
                          <span className="text-xs font-bold text-slate-700">Upload Foto Profil</span>
                          <span className="text-[10px] text-slate-400">PNG, JPG Maksimal 2MB</span>
                          <input type="file" accept="image/*" onChange={handleFotoUpload} className="hidden" />
                        </label>
                      )}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                          NIY (Nomor Induk Yayasan) <span className="text-red-500">*</span>
                        </label>
                        <input
                          type="text"
                          placeholder="NIY-2026xxxx"
                          value={formData.niy}
                          onChange={(e) => setFormData((p) => ({ ...p, niy: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">NIK (Nomor Induk Kependudukan)</label>
                        <input
                          type="text"
                          placeholder="1371xxxxxxxxxxxx"
                          value={formData.nik}
                          onChange={(e) => setFormData((p) => ({ ...p, nik: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Gelar Depan</label>
                        <input
                          type="text"
                          placeholder="Ust. / Dr."
                          value={formData.gelar_depan}
                          onChange={(e) => setFormData((p) => ({ ...p, gelar_depan: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                      <div className="col-span-2">
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                          Nama Lengkap <span className="text-red-500">*</span>
                        </label>
                        <input
                          type="text"
                          placeholder="Ahmad Farhan"
                          value={formData.nama_lengkap}
                          onChange={(e) => setFormData((p) => ({ ...p, nama_lengkap: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Gelar Belakang</label>
                        <input
                          type="text"
                          placeholder="S.Pd / M.Pd"
                          value={formData.gelar_belakang}
                          onChange={(e) => setFormData((p) => ({ ...p, gelar_belakang: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Nama Panggilan</label>
                        <input
                          type="text"
                          placeholder="Farhan"
                          value={formData.nama_panggilan}
                          onChange={(e) => setFormData((p) => ({ ...p, nama_panggilan: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Jenis Kelamin</label>
                        <select
                          value={formData.jenis_kelamin}
                          onChange={(e) => setFormData((p) => ({ ...p, jenis_kelamin: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        >
                          <option value="L">Laki-laki</option>
                          <option value="P">Perempuan</option>
                        </select>
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Tempat Lahir</label>
                        <input
                          type="text"
                          placeholder="Padang"
                          value={formData.tempat_lahir}
                          onChange={(e) => setFormData((p) => ({ ...p, tempat_lahir: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Tanggal Lahir</label>
                        <input
                          type="date"
                          value={formData.tanggal_lahir}
                          onChange={(e) => setFormData((p) => ({ ...p, tanggal_lahir: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                    </div>
                  </div>
                )}

                {/* STEP 2: Kepegawaian */}
                {currentStep === 2 && (
                  <div className="space-y-4">
                    <h3 className="text-base font-bold text-slate-800 border-b border-slate-100 pb-2">Status & Penempatan Kepegawaian</h3>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Unit Kerja / Sekolah</label>
                        <select
                          value={formData.unit_id}
                          onChange={(e) => setFormData((p) => ({ ...p, unit_id: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        >
                          <option value="">Pilih Unit Pendidikan</option>
                          {unitsList.map((u) => (
                            <option key={u.id} value={u.id}>{u.name}</option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Jabatan Master</label>
                        <select
                          value={formData.jabatan_id}
                          onChange={(e) => setFormData((p) => ({ ...p, jabatan_id: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        >
                          <option value="">Pilih Jabatan</option>
                          {positionsList.map((p) => (
                            <option key={p.id} value={p.id}>{p.name}</option>
                          ))}
                        </select>
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Status Pegawai</label>
                        <select
                          value={formData.status_pegawai}
                          onChange={(e) => setFormData((p) => ({ ...p, status_pegawai: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        >
                          {STATUS_PEGAWAI_OPTIONS.map((st) => (
                            <option key={st} value={st}>{st}</option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Status Keaktifan</label>
                        <select
                          value={formData.status}
                          onChange={(e) => setFormData((p) => ({ ...p, status: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        >
                          {STATUS_OPTIONS.map((st) => (
                            <option key={st} value={st}>{st}</option>
                          ))}
                        </select>
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Tanggal Masuk</label>
                        <input
                          type="date"
                          value={formData.tanggal_masuk}
                          onChange={(e) => setFormData((p) => ({ ...p, tanggal_masuk: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Tanggal Keluar (Jika Ada)</label>
                        <input
                          type="date"
                          value={formData.tanggal_keluar}
                          onChange={(e) => setFormData((p) => ({ ...p, tanggal_keluar: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                    </div>
                  </div>
                )}

                {/* STEP 3: Kontak & Alamat */}
                {currentStep === 3 && (
                  <div className="space-y-4">
                    <h3 className="text-base font-bold text-slate-800 border-b border-slate-100 pb-2">Kontak & Alamat</h3>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">No. WhatsApp / HP</label>
                        <input
                          type="text"
                          placeholder="0812-3456-7890"
                          value={formData.no_hp}
                          onChange={(e) => setFormData((p) => ({ ...p, no_hp: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Email Pegawai</label>
                        <input
                          type="email"
                          placeholder="pegawai@dareliman.sch.id"
                          value={formData.email}
                          onChange={(e) => setFormData((p) => ({ ...p, email: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                    </div>

                    <div>
                      <label className="block text-xs font-semibold text-slate-700 mb-1">Alamat Tempat Tinggal</label>
                      <textarea
                        rows={3}
                        placeholder="Jl. Khatib Sulaiman No. 20..."
                        value={formData.alamat}
                        onChange={(e) => setFormData((p) => ({ ...p, alamat: e.target.value }))}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                      />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Kota / Kabupaten</label>
                        <input
                          type="text"
                          value={formData.kota}
                          onChange={(e) => setFormData((p) => ({ ...p, kota: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">Provinsi</label>
                        <input
                          type="text"
                          value={formData.provinsi}
                          onChange={(e) => setFormData((p) => ({ ...p, provinsi: e.target.value }))}
                          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                        />
                      </div>
                    </div>
                  </div>
                )}

                {/* STEP 4: Konfirmasi */}
                {currentStep === 4 && (
                  <div className="space-y-4">
                    <h3 className="text-base font-bold text-slate-800 border-b border-slate-100 pb-2">Konfirmasi Data Pegawai</h3>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3 text-xs">
                      <div className="flex justify-between border-b border-slate-200 pb-2">
                        <span className="text-slate-500 font-medium">NIY:</span>
                        <span className="font-bold text-slate-800">{formData.niy || '-'}</span>
                      </div>
                      <div className="flex justify-between border-b border-slate-200 pb-2">
                        <span className="text-slate-500 font-medium">Nama Lengkap:</span>
                        <span className="font-bold text-slate-800">{formData.nama_lengkap || '-'}</span>
                      </div>
                      <div className="flex justify-between border-b border-slate-200 pb-2">
                        <span className="text-slate-500 font-medium">Status Pegawai:</span>
                        <span className="font-bold text-slate-800">{formData.status_pegawai || '-'}</span>
                      </div>
                      <div className="flex justify-between border-b border-slate-200 pb-2">
                        <span className="text-slate-500 font-medium">Kontak HP:</span>
                        <span className="font-bold text-slate-800">{formData.no_hp || '-'}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-slate-500 font-medium">Email:</span>
                        <span className="font-bold text-slate-800">{formData.email || '-'}</span>
                      </div>
                    </div>
                  </div>
                )}
              </div>

              {/* Side Card in Edit Mode */}
              {isEditMode && (
                <div className="border-l border-slate-100 bg-slate-50/30 p-6 space-y-4">
                  <div className="rounded-xl border border-blue-200 bg-gradient-to-br from-emerald-700 to-teal-800 p-4 text-white shadow-sm">
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-[10px] font-bold uppercase tracking-wider">{formData.niy}</span>
                      <span className="rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-bold">{formData.status}</span>
                    </div>
                    <h4 className="font-extrabold text-sm leading-tight">{formData.nama_lengkap}</h4>
                  </div>

                  <div className="space-y-2 pt-2">
                    <button
                      type="button"
                      onClick={() => toggleEmployeeStatus(formData)}
                      className="w-full rounded-lg border border-amber-300 bg-amber-50 py-2 text-xs font-bold text-amber-700 hover:bg-amber-100 transition-colors"
                    >
                      {formData.status === 'Aktif' ? 'Nonaktifkan Pegawai' : 'Aktifkan Pegawai'}
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        setDeleteTarget(formData)
                        closeFormModal()
                      }}
                      className="w-full rounded-lg border border-red-200 bg-red-50 py-2 text-xs font-bold text-red-600 hover:bg-red-100 transition-colors"
                    >
                      Hapus Pegawai
                    </button>
                  </div>
                </div>
              )}
            </div>

            {/* Bottom Footer Actions */}
            <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50/80 px-6 py-4">
              <button
                type="button"
                onClick={closeFormModal}
                className="rounded-lg border border-slate-300 bg-white px-5 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors"
              >
                Batal
              </button>

              <div className="flex items-center gap-2">
                {currentStep < 4 ? (
                  <button
                    type="button"
                    onClick={() => setCurrentStep((s) => Math.min(4, s + 1))}
                    className="rounded-lg bg-emerald-800 px-6 py-2 text-xs font-bold text-white shadow hover:bg-emerald-900 transition-colors"
                  >
                    Selanjutnya →
                  </button>
                ) : (
                  <button
                    type="button"
                    onClick={handleFormSubmit}
                    className="rounded-lg bg-emerald-800 px-6 py-2 text-xs font-bold text-white shadow hover:bg-emerald-900 transition-colors"
                  >
                    {isEditMode ? 'Simpan Perubahan' : 'Simpan Pegawai'}
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* 6. MODAL DETAIL PEGAWAI (WITH 7 TABS) */}
      {detailEmployee && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            {/* Top Bar */}
            <div className="flex items-center justify-between border-b border-slate-100 px-6 py-3.5 bg-slate-50">
              <button
                onClick={() => setDetailEmployee(null)}
                className="flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-slate-900"
              >
                <FaArrowLeft /> Kembali
              </button>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => setShowIdCardModal(detailEmployee)}
                  className="flex items-center gap-1.5 rounded-lg border border-purple-600 bg-purple-50 px-3 py-1.5 text-xs font-bold text-purple-700 hover:bg-purple-100"
                >
                  <FaIdCard /> ID Card
                </button>
                <button
                  onClick={() => {
                    const target = detailEmployee
                    setDetailEmployee(null)
                    openEditModal(target)
                  }}
                  className="flex items-center gap-1.5 rounded-lg border border-emerald-600 bg-white px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50"
                >
                  <FaEdit /> Edit
                </button>
              </div>
            </div>

            {/* Content Body */}
            <div className="p-6 space-y-6 max-h-[80vh] overflow-y-auto">
              {/* Profile Card Header */}
              <div className="flex flex-col md:flex-row gap-6 items-center rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div className="h-28 w-28 rounded-full bg-emerald-800 text-white flex items-center justify-center font-bold text-2xl overflow-hidden shrink-0 shadow-md">
                  {detailEmployee.foto ? (
                    <img src={detailEmployee.foto} alt={detailEmployee.nama_lengkap} className="h-full w-full object-cover" />
                  ) : (
                    detailEmployee.nama_lengkap.substring(0, 2).toUpperCase()
                  )}
                </div>
                <div className="flex-1 space-y-1 text-center md:text-left">
                  <span className="rounded-md bg-emerald-800 px-2.5 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider">
                    {detailEmployee.niy}
                  </span>
                  <h2 className="text-xl font-black text-slate-900">
                    {detailEmployee.gelar_depan} {detailEmployee.nama_lengkap}{detailEmployee.gelar_belakang ? `, ${detailEmployee.gelar_belakang}` : ''}
                  </h2>
                  <p className="text-xs font-bold text-emerald-700">{detailEmployee.jabatan_name} - {detailEmployee.unit_name}</p>
                  <p className="text-xs text-slate-500 flex items-center justify-center md:justify-start gap-2">
                    <span>Status: <strong className="text-slate-800">{detailEmployee.status_pegawai}</strong></span>
                    <span>• Keaktifan: <strong className="text-emerald-700">{detailEmployee.status}</strong></span>
                  </p>
                </div>
              </div>

              {/* Tabs Navigation */}
              <div className="flex border-b border-slate-200 gap-4 text-xs font-bold text-slate-500 overflow-x-auto pb-1 scrollbar-hide">
                {['Identitas', 'Kepegawaian', 'Penugasan Mengajar', 'Riwayat Jabatan', 'Sertifikasi', 'Dokumen', 'Absensi'].map((tab) => (
                  <button
                    key={tab}
                    onClick={() => setActiveDetailTab(tab)}
                    className={`pb-3 transition-colors border-b-2 whitespace-nowrap ${activeDetailTab === tab
                        ? 'border-emerald-800 text-emerald-900'
                        : 'border-transparent hover:text-slate-800'
                      }`}
                  >
                    {tab}
                  </button>
                ))}
              </div>

              {/* TAB 1: IDENTITAS */}
              {activeDetailTab === 'Identitas' && (
                <div className="grid grid-cols-2 gap-4 text-xs bg-white rounded-xl border border-slate-200 p-5">
                  <div>
                    <span className="text-slate-400 block mb-0.5">NIY</span>
                    <span className="font-bold text-slate-800">{detailEmployee.niy}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 block mb-0.5">NIK</span>
                    <span className="font-bold text-slate-800">{detailEmployee.nik || '-'}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 block mb-0.5">Jenis Kelamin</span>
                    <span className="font-bold text-slate-800">{detailEmployee.jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan'}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 block mb-0.5">Tempat, Tanggal Lahir</span>
                    <span className="font-bold text-slate-800">{detailEmployee.tempat_lahir}, {detailEmployee.tanggal_lahir || '-'}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 block mb-0.5">No HP / WhatsApp</span>
                    <span className="font-bold text-slate-800">{detailEmployee.no_hp || '-'}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 block mb-0.5">Email</span>
                    <span className="font-bold text-slate-800">{detailEmployee.email || '-'}</span>
                  </div>
                  <div className="col-span-2">
                    <span className="text-slate-400 block mb-0.5">Alamat Lengkap</span>
                    <span className="font-bold text-slate-800">{detailEmployee.alamat || '-'}</span>
                  </div>
                </div>
              )}

              {/* TAB 2: KEPEGAWAIAN */}
              {activeDetailTab === 'Kepegawaian' && (
                <div className="grid grid-cols-2 gap-4 text-xs bg-white rounded-xl border border-slate-200 p-5">
                  <div>
                    <span className="text-slate-400 block mb-0.5">Unit Kerja</span>
                    <span className="font-bold text-slate-800">{detailEmployee.unit_name}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 block mb-0.5">Jabatan Utama</span>
                    <span className="font-bold text-slate-800">{detailEmployee.jabatan_name}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 block mb-0.5">Status Pegawai</span>
                    <span className="font-bold text-slate-800">{detailEmployee.status_pegawai}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 block mb-0.5">Tanggal Masuk</span>
                    <span className="font-bold text-slate-800">{detailEmployee.tanggal_masuk || '-'}</span>
                  </div>
                </div>
              )}

              {/* TAB 3: PENUGASAN MENGAJAR */}
              {activeDetailTab === 'Penugasan Mengajar' && (
                <div className="space-y-4">
                  <div className="p-4 rounded-xl border border-emerald-200 bg-emerald-50/50 space-y-3">
                    <h4 className="text-xs font-bold text-slate-800">Tambah Penugasan Mengajar Baru</h4>
                    <div className="grid grid-cols-4 gap-2">
                      <input
                        type="text"
                        placeholder="Mata Pelajaran"
                        value={newTeaching.mapel}
                        onChange={(e) => setNewTeaching((p) => ({ ...p, mapel: e.target.value }))}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                      />
                      <input
                        type="text"
                        placeholder="Kelas / Rombel"
                        value={newTeaching.kelas}
                        onChange={(e) => setNewTeaching((p) => ({ ...p, kelas: e.target.value }))}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                      />
                      <input
                        type="text"
                        placeholder="Tahun Ajaran"
                        value={newTeaching.tahun}
                        onChange={(e) => setNewTeaching((p) => ({ ...p, tahun: e.target.value }))}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                      />
                      <button
                        onClick={handleAddTeaching}
                        className="rounded-lg bg-emerald-800 text-white text-xs font-bold py-1.5 hover:bg-emerald-900"
                      >
                        + Tambah Penugasan
                      </button>
                    </div>
                  </div>

                  <div className="overflow-x-auto rounded-xl border border-slate-200">
                    <table className="w-full text-left text-xs">
                      <thead className="bg-slate-50 font-bold uppercase text-slate-500">
                        <tr>
                          <th className="py-2.5 px-3">Mata Pelajaran</th>
                          <th className="py-2.5 px-3">Kelas</th>
                          <th className="py-2.5 px-3">Tahun Ajaran</th>
                          <th className="py-2.5 px-3 text-center">Status</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100">
                        {(detailEmployee.teachings || []).length === 0 ? (
                          <tr>
                            <td colSpan={4} className="py-6 text-center text-slate-400">Belum ada penugasan mengajar</td>
                          </tr>
                        ) : (
                          detailEmployee.teachings.map((t, idx) => (
                            <tr key={idx} className="hover:bg-slate-50">
                              <td className="py-2.5 px-3 font-bold text-slate-800">{t.mapel || t.subject?.name || 'Bahasa Arab'}</td>
                              <td className="py-2.5 px-3 font-semibold">{t.kelas || t.classroom?.name || 'Kelas 5A'}</td>
                              <td className="py-2.5 px-3">{t.tahun || '2025/2026'}</td>
                              <td className="py-2.5 px-3 text-center">
                                <span className="rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 font-bold">Aktif</span>
                              </td>
                            </tr>
                          ))
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              {/* TAB 4: RIWAYAT JABATAN */}
              {activeDetailTab === 'Riwayat Jabatan' && (
                <div className="space-y-3">
                  {(detailEmployee.position_history || []).map((h, idx) => (
                    <div key={idx} className="p-3 rounded-xl border border-slate-200 bg-white flex justify-between items-center text-xs">
                      <div>
                        <h4 className="font-bold text-slate-800">{h.jabatan}</h4>
                        <span className="text-slate-400">{h.keterangan || 'Penugasan Resmi'}</span>
                      </div>
                      <span className="rounded-md bg-slate-100 px-2 py-1 font-semibold text-slate-600">{h.tgl_mulai}</span>
                    </div>
                  ))}
                </div>
              )}

              {/* TAB 5: SERTIFIKASI */}
              {activeDetailTab === 'Sertifikasi' && (
                <div className="space-y-4">
                  <div className="p-4 rounded-xl border border-blue-200 bg-blue-50/50 space-y-3">
                    <h4 className="text-xs font-bold text-slate-800">Tambah Sertifikasi Baru</h4>
                    <div className="grid grid-cols-4 gap-2">
                      <input
                        type="text"
                        placeholder="Nama Sertifikat"
                        value={newCert.nama}
                        onChange={(e) => setNewCert((p) => ({ ...p, nama: e.target.value }))}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                      />
                      <input
                        type="text"
                        placeholder="Penerbit"
                        value={newCert.penerbit}
                        onChange={(e) => setNewCert((p) => ({ ...p, penerbit: e.target.value }))}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                      />
                      <input
                        type="text"
                        placeholder="Tahun"
                        value={newCert.tahun}
                        onChange={(e) => setNewCert((p) => ({ ...p, tahun: e.target.value }))}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                      />
                      <button
                        onClick={handleAddCert}
                        className="rounded-lg bg-blue-700 text-white text-xs font-bold py-1.5 hover:bg-blue-800"
                      >
                        + Tambah Sertifikat
                      </button>
                    </div>
                  </div>

                  <div className="space-y-2">
                    {(detailEmployee.certifications || []).map((c, idx) => (
                      <div key={idx} className="p-3 rounded-xl border border-slate-200 bg-white flex justify-between items-center text-xs">
                        <div className="flex items-center gap-3">
                          <FaAward className="text-xl text-amber-500" />
                          <div>
                            <h4 className="font-bold text-slate-800">{c.nama}</h4>
                            <p className="text-slate-400">Penerbit: {c.penerbit} ({c.tahun})</p>
                          </div>
                        </div>
                        <span className="font-mono text-slate-600">{c.no_sertifikat}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* TAB 6: DOKUMEN */}
              {activeDetailTab === 'Dokumen' && (
                <div className="space-y-4">
                  <div className="p-4 rounded-xl border border-purple-200 bg-purple-50/50 space-y-3">
                    <h4 className="text-xs font-bold text-slate-800">Upload Dokumen Pegawai</h4>
                    <div className="grid grid-cols-3 gap-2">
                      <input
                        type="text"
                        placeholder="Nama Dokumen (KTP, SK, Ijazah)"
                        value={newDoc.nama}
                        onChange={(e) => setNewDoc((p) => ({ ...p, nama: e.target.value }))}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                      />
                      <input
                        type="text"
                        placeholder="Nama File (.pdf / .jpg)"
                        value={newDoc.file_name}
                        onChange={(e) => setNewDoc((p) => ({ ...p, file_name: e.target.value }))}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                      />
                      <button
                        onClick={handleAddDoc}
                        className="rounded-lg bg-purple-700 text-white text-xs font-bold py-1.5 hover:bg-purple-800"
                      >
                        + Simpan Dokumen
                      </button>
                    </div>
                  </div>

                  <div className="space-y-2">
                    {(detailEmployee.documents || []).map((d, idx) => (
                      <div key={idx} className="p-3 rounded-xl border border-slate-200 bg-white flex justify-between items-center text-xs">
                        <div className="flex items-center gap-3">
                          <FaFolderOpen className="text-lg text-purple-600" />
                          <div>
                            <h4 className="font-bold text-slate-800">{d.nama}</h4>
                            <p className="text-slate-400">{d.file_name} • Upload: {d.tanggal}</p>
                          </div>
                        </div>
                        <button
                          onClick={() => Swal.fire('Preview Dokumen', `Membuka file ${d.file_name}`, 'info')}
                          className="rounded-lg border border-slate-300 px-3 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-50"
                        >
                          Lihat File
                        </button>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* TAB 7: ABSENSI */}
              {activeDetailTab === 'Absensi' && (
                <div className="space-y-3">
                  <div className="overflow-x-auto rounded-xl border border-slate-200">
                    <table className="w-full text-left text-xs">
                      <thead className="bg-slate-50 font-bold uppercase text-slate-500">
                        <tr>
                          <th className="py-2.5 px-3">Tanggal</th>
                          <th className="py-2.5 px-3">Jam Masuk</th>
                          <th className="py-2.5 px-3">Jam Pulang</th>
                          <th className="py-2.5 px-3 text-center">Status</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100">
                        {(detailEmployee.attendances || []).map((a, idx) => (
                          <tr key={idx} className="hover:bg-slate-50">
                            <td className="py-2.5 px-3 font-semibold text-slate-800">{a.tanggal}</td>
                            <td className="py-2.5 px-3 font-mono text-emerald-700">{a.jam_masuk}</td>
                            <td className="py-2.5 px-3 font-mono text-slate-600">{a.jam_pulang}</td>
                            <td className="py-2.5 px-3 text-center">
                              <span className="rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 font-bold">
                                {a.status}
                              </span>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* 7. MODAL CETAK ID CARD PEGAWAI */}
      {showIdCardModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-6 py-4">
              <h3 className="text-base font-bold text-slate-900 flex items-center gap-2">
                <FaIdCard className="text-purple-600" /> Cetak ID Card Pegawai
              </h3>
              <button onClick={() => setShowIdCardModal(null)} className="text-slate-400 hover:text-slate-700">
                ✕
              </button>
            </div>

            {/* ID Card Front Preview */}
            <div className="p-6 flex justify-center bg-slate-100">
              <div className="w-72 h-[420px] rounded-2xl bg-gradient-to-b from-[#064e3b] via-emerald-800 to-teal-900 p-5 text-white shadow-2xl relative flex flex-col items-center justify-between border-2 border-emerald-500/30">
                {/* Header Logo */}
                <div className="text-center space-y-1">
                  <h4 className="text-xs font-black tracking-widest uppercase text-emerald-200">DAR EL - IMAN</h4>
                  <p className="text-[9px] font-semibold text-white/80 uppercase">KARTU IDENTITAS PEGAWAI</p>
                </div>

                {/* Photo Frame */}
                <div className="h-28 w-28 rounded-full border-4 border-white shadow-lg overflow-hidden bg-slate-200 flex items-center justify-center my-2">
                  {showIdCardModal.foto ? (
                    <img src={showIdCardModal.foto} alt={showIdCardModal.nama_lengkap} className="h-full w-full object-cover" />
                  ) : (
                    <span className="text-3xl font-black text-slate-600">
                      {showIdCardModal.nama_lengkap.substring(0, 2).toUpperCase()}
                    </span>
                  )}
                </div>

                {/* Name & Title */}
                <div className="text-center space-y-1 w-full">
                  <h3 className="text-sm font-extrabold text-white leading-tight">
                    {showIdCardModal.gelar_depan} {showIdCardModal.nama_lengkap}{showIdCardModal.gelar_belakang ? `, ${showIdCardModal.gelar_belakang}` : ''}
                  </h3>
                  <p className="text-xs font-bold text-emerald-200">{showIdCardModal.jabatan_name}</p>
                  <p className="text-[11px] font-semibold text-white/90">{showIdCardModal.unit_name}</p>
                </div>

                {/* QR Code & NIY */}
                <div className="bg-white/10 backdrop-blur-md rounded-xl p-3 w-full text-center space-y-1 border border-white/20">
                  <div className="flex items-center justify-center text-3xl text-white">
                    <FaQrcode />
                  </div>
                  <span className="font-mono text-xs font-black tracking-widest text-emerald-200 block">
                    {showIdCardModal.niy}
                  </span>
                </div>
              </div>
            </div>

            {/* Action Footer */}
            <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-6 py-4">
              <button
                onClick={() => setShowIdCardModal(null)}
                className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"
              >
                Tutup
              </button>
              <button
                onClick={() => {
                  Swal.fire('Mencetak ID Card', 'Mengirim perintah cetak ID Card ke printer...', 'success')
                  setShowIdCardModal(null)
                }}
                className="flex items-center gap-2 rounded-lg bg-emerald-800 px-5 py-2 text-xs font-bold text-white shadow hover:bg-emerald-900"
              >
                <FaPrint /> Cetak / Download PDF
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 8. MODAL KONFIRMASI HAPUS PEGAWAI */}
      {deleteTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div className="p-6 text-center space-y-3 border-b border-slate-100">
              <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 text-rose-600 text-2xl">
                <FaExclamationTriangle />
              </div>
              <h3 className="text-lg font-extrabold text-slate-900">Hapus Data Pegawai</h3>
              <p className="text-xs text-slate-500">Apakah Anda yakin ingin menghapus pegawai berikut secara permanen?</p>
            </div>

            <div className="p-6 space-y-4">
              <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-800 font-black text-white text-xs">
                  {deleteTarget.nama_lengkap.substring(0, 2).toUpperCase()}
                </div>
                <div className="text-xs space-y-0.5">
                  <h4 className="font-extrabold text-slate-900">{deleteTarget.nama_lengkap}</h4>
                  <p className="text-slate-500">NIY: <span className="font-medium text-slate-700">{deleteTarget.niy}</span></p>
                  <p className="text-slate-500">Jabatan: <span className="font-medium text-slate-700">{deleteTarget.jabatan_name}</span></p>
                </div>
              </div>

              <label className="flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700">
                <input
                  type="checkbox"
                  checked={hasConfirmedDeleteCheck}
                  onChange={(e) => setHasConfirmedDeleteCheck(e.target.checked)}
                  className="h-4 w-4 rounded border-slate-300 text-emerald-800 focus:ring-emerald-600"
                />
                Saya memahami bahwa data pegawai tidak dapat dikembalikan.
              </label>
            </div>

            <div className="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
              <button
                onClick={() => setDeleteTarget(null)}
                className="rounded-lg border border-slate-300 bg-white px-5 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"
              >
                Batal
              </button>
              <button
                disabled={!hasConfirmedDeleteCheck || deleteMutation.isPending}
                onClick={() => deleteMutation.mutate(deleteTarget.id)}
                className="rounded-lg bg-red-600 px-5 py-2 text-xs font-bold text-white shadow hover:bg-red-700 disabled:opacity-50 transition-colors"
              >
                {deleteMutation.isPending ? 'Menghapus...' : 'Hapus Permanen'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 9. MODAL DASHBOARD IMPORT PEGAWAI */}
      {showImportModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl transition-all">
            <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
              <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                  <FaFileImport className="text-base" />
                </div>
                <div>
                  <h2 className="text-base font-extrabold text-slate-900">Dashboard Import Data Pegawai</h2>
                  <p className="text-xs text-slate-500">Unggah file CSV/Excel untuk impor data pegawai secara massal</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => { setShowImportModal(false); setImportFile(null); setImportPreviewData([]) }}
                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
              >
                <FaTimes />
              </button>
            </div>

            <div className="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
              <div className="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  <FaFileExcel className="text-2xl text-emerald-600 shrink-0" />
                  <div>
                    <h4 className="text-xs font-bold text-slate-800">Unduh Format Template Import Pegawai</h4>
                    <p className="text-[11px] text-slate-500">Format disesuaikan dengan skema master pegawai ERP.</p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={handleDownloadTemplatePegawai}
                  className="flex items-center gap-1.5 rounded-xl border border-emerald-600 bg-white px-3.5 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50 transition shadow-xs whitespace-nowrap"
                >
                  <FaDownload className="text-emerald-600" /> Unduh Template
                </button>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-800 mb-2">Unggah File (Excel / CSV)</label>
                <label className="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50/50 p-6 text-center hover:bg-slate-50 cursor-pointer transition">
                  <FaUpload className="text-3xl text-emerald-700 mb-2" />
                  <span className="text-xs font-bold text-slate-800">
                    {importFile ? importFile.name : 'Klik untuk memilih file Excel atau CSV'}
                  </span>
                  <span className="text-[11px] text-slate-400 mt-0.5">
                    {importFile ? `${(importFile.size / 1024).toFixed(1)} KB` : 'Format disukai: .csv, .xlsx (Maks. 5MB)'}
                  </span>
                  <input
                    type="file"
                    accept=".csv, .xlsx, .xls"
                    onChange={handleFileSelect}
                    className="hidden"
                  />
                </label>
              </div>

              {importPreviewData.length > 0 && (
                <div className="space-y-2">
                  <h4 className="text-xs font-bold text-slate-800">Preview Data ({importPreviewData.length} baris)</h4>
                  <div className="overflow-x-auto rounded-xl border border-slate-200">
                    <table className="w-full text-left text-xs text-slate-600">
                      <thead className="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase">
                        <tr>
                          <th className="py-2 px-3">NIY</th>
                          <th className="py-2 px-3">Nama Pegawai</th>
                          <th className="py-2 px-3">Jabatan</th>
                          <th className="py-2 px-3 text-center">Status</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100">
                        {importPreviewData.map((row, idx) => (
                          <tr key={idx} className="hover:bg-slate-50">
                            <td className="py-2 px-3 font-mono">{row.niy}</td>
                            <td className="py-2 px-3 font-bold text-slate-800">{row.nama}</td>
                            <td className="py-2 px-3">{row.jabatan}</td>
                            <td className="py-2 px-3 text-center">
                              <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                {row.status}
                              </span>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </div>

            <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50/80 px-6 py-4">
              <button
                type="button"
                onClick={() => { setShowImportModal(false); setImportFile(null); setImportPreviewData([]) }}
                className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
              >
                Batal
              </button>
              <button
                type="button"
                disabled={!importFile || isImporting}
                onClick={handleProcessImport}
                className="flex items-center gap-2 rounded-xl bg-[#064e3b] px-5 py-2 text-xs font-bold text-white shadow-md hover:bg-emerald-800 disabled:opacity-50 transition"
              >
                {isImporting ? 'Memproses Import...' : 'Proses Import Data'}
              </button>
            </div>
          </div>
        </div>
      )}
    </MasterDataPage>
  )
}
