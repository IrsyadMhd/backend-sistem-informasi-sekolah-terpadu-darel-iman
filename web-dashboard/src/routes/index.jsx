import { Suspense, lazy } from 'react'
import { Navigate, Outlet, createBrowserRouter } from 'react-router-dom'

const DashboardLayout = lazy(() => import('../layouts/DashboardLayout'))
const DashboardPage = lazy(() => import('../pages/DashboardPage'))
const StudentDataPage = lazy(() => import('../pages/StudentDataPage'))
const StudentsPage = lazy(() => import('../pages/StudentsPage'))
const EducationUnitsPage = lazy(() => import('../pages/EducationUnitsPage'))
const AttendancePage = lazy(() => import('../pages/AttendancePage'))
const TahfizhPage = lazy(() => import('../pages/TahfizhPage'))
const AcademicPage = lazy(() => import('../pages/AcademicPage'))
const NotificationsPage = lazy(() => import('../pages/NotificationsPage'))
const LoginPage = lazy(() => import('../pages/LoginPage'))
const BeritaPublikPage = lazy(() => import('../pages/BeritaPublikPage'))
const PengaturanPage = lazy(() => import('../pages/PengaturanPage'))
const LaporanAbsensiPage = lazy(() => import('../pages/LaporanAbsensiPage'))
const LaporanTahfizhPage = lazy(() => import('../pages/LaporanTahfizhPage'))
const LaporanAkademikPage = lazy(() => import('../pages/LaporanAkademikPage'))
const LaporanSiswaPage = lazy(() => import('../pages/LaporanSiswaPage'))
const LaporanAlumniPage = lazy(() => import('../pages/LaporanAlumniPage'))
const ParentsPage = lazy(() => import('../pages/ParentsPage'))
const UserProfileManagementPage = lazy(() => import('../pages/UserProfileManagementPage'))
const EmployeesPage = lazy(() => import('../pages/EmployeesPage'))
const MasterKelasPage = lazy(() => import('../pages/MasterKelasPage'))
const MasterJabatanPage = lazy(() => import('../pages/MasterJabatanPage'))
const MasterHakAksesPage = lazy(() => import('../pages/MasterHakAksesPage'))
const MasterJenisUnitPendidikanPage = lazy(() => import('../pages/MasterJenisUnitPendidikanPage'))
const MasterTahunAjaranPage = lazy(() => import('../pages/MasterTahunAjaranPage'))
const MasterModulSemesterPage = lazy(() => import('../pages/MasterModulSemesterPage'))
const MasterKurikulumPage = lazy(() => import('../pages/MasterKurikulumPage'))
const MasterSubjectPage = lazy(() => import('../pages/MasterSubjectPage'))
const MasterCapaianPembelajaranPage = lazy(() => import('../pages/MasterCapaianPembelajaranPage'))
const MasterTujuanPembelajaranPage = lazy(() => import('../pages/MasterTujuanPembelajaranPage'))
const LmsModulAjarPage = lazy(() => import('../pages/LmsModulAjarPage'))
const LmsMateriPage = lazy(() => import('../pages/LmsMateriPage'))
const LmsMediaPage = lazy(() => import('../pages/LmsMediaPage'))
const LmsReferensiPage = lazy(() => import('../pages/LmsReferensiPage'))
const LmsAktivitasBelajarPage = lazy(() => import('../pages/LmsAktivitasBelajarPage'))
const LmsDiskusiPage = lazy(() => import('../pages/LmsDiskusiPage'))
const LmsPenugasanPage = lazy(() => import('../pages/LmsPenugasanPage'))
const LmsPengumpulanTugasPage = lazy(() => import('../pages/LmsPengumpulanTugasPage'))
const LmsPresensiPage = lazy(() => import('../pages/LmsPresensiPage'))
const LmsKisiKisiPage = lazy(() => import('../pages/LmsKisiKisiPage'))
const LmsBankSoalPage = lazy(() => import('../pages/LmsBankSoalPage'))
const LmsUjianPage = lazy(() => import('../pages/LmsUjianPage'))
const LmsPenilaianPage = lazy(() => import('../pages/LmsPenilaianPage'))
const LmsRaporPage = lazy(() => import('../pages/LmsRaporPage'))
const StudentCrudPage = lazy(() => import('../pages/StudentCrudPage'))
const MultiRoleDashboardPage = lazy(() => import('../pages/MultiRoleDashboardPage'))
import RouteErrorElement from '../components/common/RouteErrorElement'

function BungkusLazy({ children }) {
  return <Suspense fallback={<section className="panel">Memuat halaman...</section>}>{children}</Suspense>
}

function RouteTerlindungi() {
  const token = localStorage.getItem('school_erp_token')

  if (!token) {
    return <Navigate to="/masuk" replace />
  }

  return <Outlet />
}

export const router = createBrowserRouter([
  {
    path: '/',
    element: (
      <BungkusLazy>
        <BeritaPublikPage />
      </BungkusLazy>
    ),
  },
  {
    path: '/masuk',
    element: (
      <BungkusLazy>
        <LoginPage />
      </BungkusLazy>
    ),
  },
  {
    path: '/auth',
    element: (
      <BungkusLazy>
        <LoginPage />
      </BungkusLazy>
    ),
  },
  {
    path: '/authentication',
    element: (
      <BungkusLazy>
        <LoginPage />
      </BungkusLazy>
    ),
  },
  {
    element: <RouteTerlindungi />,
    errorElement: <RouteErrorElement />,
    children: [
      {
        path: '/dashboard',
        element: (
          <BungkusLazy>
            <DashboardLayout />
          </BungkusLazy>
        ),
        children: [
          {
            index: true,
            element: (
              <BungkusLazy>
                <MultiRoleDashboardPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'pemantauan',
            element: (
              <BungkusLazy>
                <DashboardPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'crud-demo',
            element: (
              <BungkusLazy>
                <StudentCrudPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'students',
            element: (
              <BungkusLazy>
                <StudentDataPage />
              </BungkusLazy>
            ),
            children: [
              {
                index: true,
                element: (
                  <BungkusLazy>
                    <StudentsPage />
                  </BungkusLazy>
                ),
              },
              {
                path: 'input',
                element: (
                  <BungkusLazy>
                    <StudentsPage />
                  </BungkusLazy>
                ),
              },
              {
                path: 'kelas',
                element: (
                  <BungkusLazy>
                    <MasterKelasPage />
                  </BungkusLazy>
                ),
              },
              {
                path: 'unit-pendidikan',
                element: (
                  <BungkusLazy>
                    <EducationUnitsPage />
                  </BungkusLazy>
                ),
              },
              {
                path: 'pegawai',
                element: (
                  <BungkusLazy>
                    <EmployeesPage />
                  </BungkusLazy>
                ),
              },
              {
                path: 'jabatan',
                element: (
                  <BungkusLazy>
                    <MasterJabatanPage />
                  </BungkusLazy>
                ),
              },
              {
                path: 'rombel',
                element: (
                  <BungkusLazy>
                    <MasterKelasPage />
                  </BungkusLazy>
                ),
              },
              {
                path: 'laporan',
                element: (
                  <BungkusLazy>
                    <StudentsPage />
                  </BungkusLazy>
                ),
              },
            ],
          },
          {
            path: 'employees',
            element: (
              <BungkusLazy>
                <EmployeesPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'master-jabatan',
            element: (
              <BungkusLazy>
                <MasterJabatanPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'master-jenis-unit',
            element: (
              <BungkusLazy>
                <MasterJenisUnitPendidikanPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'master-tahun-ajaran',
            element: (
              <BungkusLazy>
                <MasterTahunAjaranPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'hak-akses',
            element: (
              <BungkusLazy>
                <MasterHakAksesPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'attendance',
            element: (
              <BungkusLazy>
                <AttendancePage />
              </BungkusLazy>
            ),
          },
          {
            path: 'tahfizh',
            element: (
              <BungkusLazy>
                <TahfizhPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'academic',
            element: (
              <BungkusLazy>
                <AcademicPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'modul-ajar',
            element: (
              <BungkusLazy>
                <LmsModulAjarPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/modul-ajar',
            element: (
              <BungkusLazy>
                <LmsModulAjarPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'master-modul-semester',
            element: (
              <BungkusLazy>
                <MasterModulSemesterPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'master-kurikulum',
            element: (
              <BungkusLazy>
                <MasterKurikulumPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'master-subjects',
            element: (
              <BungkusLazy>
                <MasterSubjectPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'master-capaian-pembelajaran',
            element: (
              <BungkusLazy>
                <MasterCapaianPembelajaranPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/capaian-pembelajaran',
            element: (
              <BungkusLazy>
                <MasterCapaianPembelajaranPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'master-tujuan-pembelajaran',
            element: (
              <BungkusLazy>
                <MasterTujuanPembelajaranPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/tujuan-pembelajaran',
            element: (
              <BungkusLazy>
                <MasterTujuanPembelajaranPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'materi-pembelajaran',
            element: (
              <BungkusLazy>
                <LmsMateriPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/materi-pembelajaran',
            element: (
              <BungkusLazy>
                <LmsMateriPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/materi',
            element: (
              <BungkusLazy>
                <LmsMateriPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'media-pembelajaran',
            element: (
              <BungkusLazy>
                <LmsMediaPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/media-pembelajaran',
            element: (
              <BungkusLazy>
                <LmsMediaPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/media',
            element: (
              <BungkusLazy>
                <LmsMediaPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'referensi-pembelajaran',
            element: (
              <BungkusLazy>
                <LmsReferensiPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/referensi-pembelajaran',
            element: (
              <BungkusLazy>
                <LmsReferensiPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/referensi',
            element: (
              <BungkusLazy>
                <LmsReferensiPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'aktivitas-belajar',
            element: (
              <BungkusLazy>
                <LmsAktivitasBelajarPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/aktivitas-belajar',
            element: (
              <BungkusLazy>
                <LmsAktivitasBelajarPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/aktivitas',
            element: (
              <BungkusLazy>
                <LmsAktivitasBelajarPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'diskusi-kelas',
            element: (
              <BungkusLazy>
                <LmsDiskusiPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/diskusi-kelas',
            element: (
              <BungkusLazy>
                <LmsDiskusiPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/diskusi',
            element: (
              <BungkusLazy>
                <LmsDiskusiPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'penugasan',
            element: (
              <BungkusLazy>
                <LmsPenugasanPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/penugasan',
            element: (
              <BungkusLazy>
                <LmsPenugasanPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'pengumpulan-tugas',
            element: (
              <BungkusLazy>
                <LmsPengumpulanTugasPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/pengumpulan-tugas',
            element: (
              <BungkusLazy>
                <LmsPengumpulanTugasPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'presensi-pembelajaran',
            element: (
              <BungkusLazy>
                <LmsPresensiPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/presensi-pembelajaran',
            element: (
              <BungkusLazy>
                <LmsPresensiPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'kisi-kisi-ujian',
            element: (
              <BungkusLazy>
                <LmsKisiKisiPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/kisi-kisi',
            element: (
              <BungkusLazy>
                <LmsKisiKisiPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'bank-soal',
            element: (
              <BungkusLazy>
                <LmsBankSoalPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/bank-soal',
            element: (
              <BungkusLazy>
                <LmsBankSoalPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'ujian-online',
            element: (
              <BungkusLazy>
                <LmsUjianPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/ujian-online',
            element: (
              <BungkusLazy>
                <LmsUjianPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/ujian',
            element: (
              <BungkusLazy>
                <LmsUjianPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'penilaian',
            element: (
              <BungkusLazy>
                <LmsPenilaianPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/penilaian',
            element: (
              <BungkusLazy>
                <LmsPenilaianPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/penilaian-rapor',
            element: (
              <BungkusLazy>
                <LmsRaporPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'lms/rapor',
            element: (
              <BungkusLazy>
                <LmsRaporPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'parents',
            element: (
              <BungkusLazy>
                <ParentsPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'notifications',
            element: (
              <BungkusLazy>
                <NotificationsPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'pengaturan',
            element: (
              <BungkusLazy>
                <PengaturanPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'laporan-absensi',
            element: (
              <BungkusLazy>
                <LaporanAbsensiPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'laporan-tahfizh',
            element: (
              <BungkusLazy>
                <LaporanTahfizhPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'laporan-akademik',
            element: (
              <BungkusLazy>
                <LaporanAkademikPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'laporan-siswa',
            element: (
              <BungkusLazy>
                <LaporanSiswaPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'laporan-alumni',
            element: (
              <BungkusLazy>
                <LaporanAlumniPage />
              </BungkusLazy>
            ),
          },
          {
            path: 'profil-akun',
            element: (
              <BungkusLazy>
                <UserProfileManagementPage />
              </BungkusLazy>
            ),
          },
        ],
      },
    ],
  },
])
