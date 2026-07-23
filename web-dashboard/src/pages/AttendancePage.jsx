import { useEffect, useMemo, useRef, useState } from 'react'
import Swal from 'sweetalert2'
import { attendanceService } from '../services/attendanceService'
import { useDaftarSiswa } from '../hooks/useStudents'
import { useAuthStore } from '../stores/authStore'

const ROLE_DIIJINKAN = ['Super Admin', 'Yayasan', 'Kepala Sekolah', 'Tata Usaha', 'Guru', 'Wali Kelas']

function hariIni() {
  return new Date().toISOString().slice(0, 10)
}

function jamSekarang() {
  return new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

export default function AttendancePage() {
  const [search, setSearch] = useState('')
  const [tanggal, setTanggal] = useState(hariIni())
  const [modeInput, setModeInput] = useState('panggil-nama')
  const [scanCode, setScanCode] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [riwayatHariIni, setRiwayatHariIni] = useState([])
  const [isCameraActive, setIsCameraActive] = useState(false)
  const [cameraSupported, setCameraSupported] = useState(false)
  const [scannerStatus, setScannerStatus] = useState('Belum aktif')
  const [lastScanSource, setLastScanSource] = useState('-')
  const videoRef = useRef(null)
  const mediaStreamRef = useRef(null)
  const detectorIntervalRef = useRef(null)
  const keyboardBufferRef = useRef('')
  const keyboardTimeoutRef = useRef(null)
  const user = useAuthStore((state) => state.user)
  const { data: daftarSiswa } = useDaftarSiswa({ search, per_page: 100 })

  const rolesUser = useMemo(() => user?.roles || [], [user])
  const punyaAksesRole = useMemo(() => rolesUser.some((role) => ROLE_DIIJINKAN.includes(role)), [rolesUser])

  const siswaRows = useMemo(() => {
    return (daftarSiswa?.data || []).map((row) => {
      const kelas = row?.metadata?.akademik?.kelas || '-'
      return {
        id: row.id,
        nis: row.nis,
        nama: row.full_name,
        kelas,
        class_id: row.class_id,
      }
    })
  }, [daftarSiswa])

  const simpanAbsensi = async ({ siswa, method }) => {
    if (!siswa?.id || !siswa?.class_id) {
      await Swal.fire('Data belum lengkap', 'Siswa belum punya data kelas, tidak dapat diabsen.', 'warning')
      return
    }

    const payload = {
      academic_year_id: '11111111-1111-1111-1111-111111111111',
      semester_id: '11111111-1111-1111-1111-111111111112',
      student_id: siswa.id,
      class_id: siswa.class_id,
      attendance_date: tanggal,
      attendance_method: method,
      status: 'present',
      location: 'Sekolah',
      metadata: {
        input_by: user?.name || 'Operator',
        input_role: rolesUser,
        input_time: new Date().toISOString(),
      },
    }

    setIsSubmitting(true)
    try {
      await attendanceService.checkin(payload)
      setRiwayatHariIni((prev) => [
        {
          id: `${siswa.id}-${Date.now()}`,
          nis: siswa.nis,
          nama: siswa.nama,
          kelas: siswa.kelas,
          method,
          jam: jamSekarang(),
          tanggal,
        },
        ...prev,
      ])
      await Swal.fire('Berhasil', `${siswa.nama} tercatat hadir (${method}).`, 'success')
    } catch (error) {
      const message = error?.response?.data?.message || 'Gagal menyimpan absensi.'
      await Swal.fire('Gagal', message, 'error')
    } finally {
      setIsSubmitting(false)
    }
  }

  const checklistHadir = async (siswa) => {
    await simpanAbsensi({ siswa, method: 'teacher_checklist' })
  }

  const prosesTapKartu = async (source = 'manual') => {
    const kode = scanCode.trim()
    if (!kode) return
    const siswa = siswaRows.find((item) => String(item.nis) === kode)
    if (!siswa) {
      await Swal.fire('Kode tidak ditemukan', 'QR/Barcode siswa tidak cocok dengan data siswa.', 'warning')
      return
    }

    setLastScanSource(source)
    await simpanAbsensi({ siswa, method: 'barcode_tap_device' })
    setScanCode('')
  }

  const stopCamera = () => {
    if (detectorIntervalRef.current) {
      clearInterval(detectorIntervalRef.current)
      detectorIntervalRef.current = null
    }

    if (mediaStreamRef.current) {
      mediaStreamRef.current.getTracks().forEach((track) => track.stop())
      mediaStreamRef.current = null
    }

    if (videoRef.current) {
      videoRef.current.srcObject = null
    }

    setIsCameraActive(false)
    setScannerStatus('Kamera nonaktif')
  }

  const startCamera = async () => {
    if (!navigator?.mediaDevices?.getUserMedia) {
      setScannerStatus('Browser tidak mendukung akses kamera')
      return
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' },
        audio: false,
      })

      mediaStreamRef.current = stream
      if (videoRef.current) {
        videoRef.current.srcObject = stream
        await videoRef.current.play()
      }

      setIsCameraActive(true)
      setScannerStatus('Kamera aktif, arahkan QR ke kamera')

      if ('BarcodeDetector' in window) {
        const detector = new window.BarcodeDetector({
          formats: ['qr_code', 'code_128', 'code_39', 'ean_13', 'ean_8'],
        })

        detectorIntervalRef.current = window.setInterval(async () => {
          if (!videoRef.current) return
          try {
            const barcodes = await detector.detect(videoRef.current)
            if (barcodes?.length) {
              const rawValue = barcodes[0]?.rawValue || ''
              if (rawValue) {
                setScanCode(rawValue)
                setScannerStatus(`QR terdeteksi: ${rawValue}`)
              }
            }
          } catch {
            // silent
          }
        }, 900)
      } else {
        setScannerStatus('BarcodeDetector tidak didukung, gunakan scanner device (keyboard wedge)')
      }
    } catch {
      setScannerStatus('Gagal mengaktifkan kamera')
    }
  }

  useEffect(() => {
    setCameraSupported(Boolean(navigator?.mediaDevices?.getUserMedia))
  }, [])

  useEffect(() => {
    if (modeInput !== 'tap-kartu') return undefined

    const onKeyDown = (event) => {
      if (event.key === 'Enter') {
        const value = keyboardBufferRef.current.trim()
        if (value) {
          setScanCode(value)
          setScannerStatus(`Input scanner device: ${value}`)
          setTimeout(() => {
            prosesTapKartu('device_tap_keyboard')
          }, 0)
        }
        keyboardBufferRef.current = ''
        return
      }

      if (event.key.length === 1) {
        keyboardBufferRef.current += event.key
        setScannerStatus('Mendeteksi input dari device tap/scanner...')
      }

      if (keyboardTimeoutRef.current) {
        clearTimeout(keyboardTimeoutRef.current)
      }

      keyboardTimeoutRef.current = setTimeout(() => {
        keyboardBufferRef.current = ''
      }, 250)
    }

    window.addEventListener('keydown', onKeyDown)
    return () => {
      window.removeEventListener('keydown', onKeyDown)
      if (keyboardTimeoutRef.current) {
        clearTimeout(keyboardTimeoutRef.current)
      }
    }
  }, [modeInput, prosesTapKartu])

  useEffect(() => {
    return () => {
      stopCamera()
    }
  }, [])

  if (!punyaAksesRole) {
    return (
      <section className="panel rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
        <h3 className="text-lg font-semibold">Akses Ditolak</h3>
        <p className="mt-2 text-sm">
          Modul absensi hanya dapat diakses oleh Super Admin, Yayasan, Kepala Sekolah, Tata Usaha, Guru, dan Wali Kelas.
        </p>
      </section>
    )
  }

  return (
    <section className="panel rounded-2xl border border-emerald-100 bg-white/90 p-4 shadow-sm">
      <div className="mb-4">
        <h3 className="text-xl font-bold text-emerald-900">Monitoring Kehadiran Siswa</h3>
        <p className="text-sm text-emerald-700">
          Input absensi melalui checklist saat guru memanggil nama siswa, atau tap kartu siswa (barcode) ke kamera/mesin tap sekolah.
        </p>
      </div>

      <div className="mb-4 grid gap-3 rounded-xl border border-emerald-100 bg-emerald-50/60 p-3 md:grid-cols-3">
        <label className="text-sm">
          <span className="mb-1 block font-medium text-emerald-900">Tanggal Absensi</span>
          <input
            type="date"
            className="w-full rounded-lg border border-emerald-200 bg-white px-3 py-2 outline-none focus:border-emerald-500"
            value={tanggal}
            onChange={(event) => setTanggal(event.target.value)}
          />
        </label>
        <label className="text-sm">
          <span className="mb-1 block font-medium text-emerald-900">Cari Siswa (Nama/NIS)</span>
          <input
            type="text"
            className="w-full rounded-lg border border-emerald-200 bg-white px-3 py-2 outline-none focus:border-emerald-500"
            placeholder="Contoh: Ahmad / 2024001"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
        </label>
        <div className="text-sm md:col-span-1">
          <span className="mb-1 block font-medium text-emerald-900">Mode Input</span>
          <div className="grid gap-2">
            <button
              type="button"
              onClick={() => setModeInput('panggil-nama')}
              className={`rounded-xl border px-3 py-2 text-left transition ${
                modeInput === 'panggil-nama'
                  ? 'border-emerald-600 bg-emerald-600 text-white shadow'
                  : 'border-emerald-200 bg-white text-emerald-900 hover:border-emerald-400'
              }`}
            >
              <p className="text-xs font-semibold uppercase tracking-wide">Checklist Guru</p>
              <p className="text-sm">Panggil Nama + Checklist Hadir</p>
            </button>
            <button
              type="button"
              onClick={() => setModeInput('tap-kartu')}
              className={`rounded-xl border px-3 py-2 text-left transition ${
                modeInput === 'tap-kartu'
                  ? 'border-sky-600 bg-sky-600 text-white shadow'
                  : 'border-sky-200 bg-white text-sky-900 hover:border-sky-400'
              }`}
            >
              <p className="text-xs font-semibold uppercase tracking-wide">Tap / Scan Kartu</p>
              <p className="text-sm">Kamera / Mesin Tap / Scanner</p>
            </button>
          </div>
        </div>
      </div>

      {modeInput === 'tap-kartu' ? (
        <div className="mb-4 rounded-xl border border-sky-100 bg-sky-50 p-3">
          <h4 className="mb-2 text-sm font-semibold text-sky-900">Mode Tap Kartu / QR Code Siswa</h4>
          <p className="mb-2 text-xs text-sky-800">
            Scan QR pada kartu siswa (profil Data Siswa) dengan kamera atau device tap/scanner sekolah.
          </p>

          <div className="mb-3 grid gap-3 md:grid-cols-2">
            <div className="rounded-lg border border-sky-200 bg-white p-2">
              <video ref={videoRef} className="h-48 w-full rounded-md bg-slate-100 object-cover" muted playsInline />
            </div>
            <div className="rounded-lg border border-sky-200 bg-white p-3 text-sm">
              <p><strong>Status Kamera:</strong> {isCameraActive ? 'Aktif' : 'Nonaktif'}</p>
              <p><strong>Dukungan Kamera:</strong> {cameraSupported ? 'Didukung browser' : 'Tidak didukung browser'}</p>
              <p><strong>Status Scanner:</strong> {scannerStatus}</p>
              <p><strong>Sumber Scan Terakhir:</strong> {lastScanSource}</p>
              <div className="mt-3 flex flex-wrap gap-2">
                <button
                  type="button"
                  className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-60"
                  onClick={startCamera}
                  disabled={isSubmitting || isCameraActive}
                >
                  Aktifkan Kamera Scan
                </button>
                <button
                  type="button"
                  className="rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-60"
                  onClick={stopCamera}
                  disabled={isSubmitting || !isCameraActive}
                >
                  Stop Kamera
                </button>
              </div>
            </div>
          </div>

          <div className="flex flex-col gap-2 md:flex-row">
            <input
              type="text"
              className="w-full rounded-lg border border-sky-200 bg-white px-3 py-2 outline-none focus:border-sky-500"
              placeholder="Hasil scan QR/barcode (contoh: NIS siswa)"
              value={scanCode}
              onChange={(event) => setScanCode(event.target.value)}
            />
            <button
              type="button"
              className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
              onClick={() => prosesTapKartu('manual_or_camera')}
              disabled={isSubmitting}
            >
              Proses Hasil Scan
            </button>
          </div>
        </div>
      ) : null}

      <div className="mb-4 overflow-hidden rounded-2xl border border-emerald-100">
        <div className="overflow-x-auto">
          <table className="min-w-[760px] w-full text-sm">
            <thead className="bg-emerald-50 text-emerald-900">
              <tr>
                <th className="px-3 py-2 text-left">NIS</th>
                <th className="px-3 py-2 text-left">Nama Siswa</th>
                <th className="px-3 py-2 text-left">Kelas</th>
                <th className="px-3 py-2 text-left">Aksi Kehadiran</th>
              </tr>
            </thead>
            <tbody>
              {siswaRows.map((siswa) => (
                <tr key={siswa.id} className="border-t border-emerald-50">
                  <td className="px-3 py-2">{siswa.nis || '-'}</td>
                  <td className="px-3 py-2">{siswa.nama}</td>
                  <td className="px-3 py-2">{siswa.kelas}</td>
                  <td className="px-3 py-2">
                    <button
                      type="button"
                      className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60"
                      onClick={() => checklistHadir(siswa)}
                      disabled={isSubmitting}
                    >
                      Checklist Hadir
                    </button>
                  </td>
                </tr>
              ))}
              {siswaRows.length === 0 ? (
                <tr>
                  <td className="px-3 py-4 text-center text-emerald-700" colSpan={4}>
                    Data siswa tidak ditemukan.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </div>

      <div className="rounded-xl border border-amber-100 bg-amber-50 p-3">
        <h4 className="mb-2 text-sm font-semibold text-amber-900">Log Kehadiran Hari Ini</h4>
        <ul className="space-y-1 text-sm text-amber-900">
          {riwayatHariIni.map((item) => (
            <li key={item.id} className="rounded-md bg-white px-2 py-1">
              {item.jam} - {item.nama} ({item.nis}) - {item.kelas} - {item.method}
            </li>
          ))}
          {riwayatHariIni.length === 0 ? <li>Belum ada absensi yang dicatat hari ini.</li> : null}
        </ul>
      </div>
    </section>
  )
}
