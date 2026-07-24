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
    element: <RouteTerlindungi />,
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
                <DashboardPage />
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
                    <StudentsPage />
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
                path: 'rombel',
                element: (
                  <BungkusLazy>
                    <StudentsPage />
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
        ],
      },
    ],
  },
])
