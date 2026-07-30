import { useEffect, useState } from 'react'
import { Fingerprint, ListChecks, QrCode, ScanBarcode, ScanFace, Wifi, WifiOff } from 'lucide-react'
import { lmsPresensiService } from '../../services/lmsPresensiService'

const methods = [
  ['manual', 'Checklist Guru', ListChecks],
  ['qr', 'Scan QR Code', QrCode],
  ['barcode', 'Scan Barcode', ScanBarcode],
  ['face', 'Face Recognition', ScanFace],
  ['fingerprint', 'Fingerprint', Fingerprint],
]

export function AttendanceMethodSelector({ value, onChange }) {
  return <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-5">{methods.map(([id, label, Icon]) =>
    <button key={id} type="button" onClick={() => onChange(id)} className={`flex items-center gap-2 rounded-xl border p-3 text-left text-sm font-bold transition ${value === id ? 'border-[#0E5C44] bg-emerald-50 text-[#0E5C44]' : 'border-slate-200 bg-white text-slate-600 dark:border-slate-700 dark:bg-[#1B2433]'}`}><Icon size={19} />{label}</button>
  )}</div>
}

export function AttendanceCapturePanel({ method, session, onRecorded }) {
  const [identifier, setIdentifier] = useState('')
  const [confidence, setConfidence] = useState(90)
  const [active, setActive] = useState(Boolean(session?.session_started_at && !session?.session_closed_at))
  const [result, setResult] = useState(null)
  const [logs, setLogs] = useState([])
  const [busy, setBusy] = useState(false)

  const loadLogs = async () => {
    if (!session?.id) return
    const response = await lmsPresensiService.getScanLogs(session.id)
    setLogs(response?.data?.data || [])
  }
  useEffect(() => { loadLogs().catch(() => {}) }, [session?.id])

  if (method === 'manual') return <div className="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">Gunakan tabel siswa di bawah untuk checklist, status, jam hadir, menit terlambat, dan catatan. Input manual tetap dapat digunakan bila perangkat gagal.</div>

  const start = async () => {
    setBusy(true)
    try { const response = await lmsPresensiService.startCaptureSession(session.id); setActive(true); setResult({ scan_status: 'success', message: `Sesi aktif sampai ${new Date(response.data.session.session_expires_at).toLocaleTimeString('id-ID')}` }) }
    finally { setBusy(false) }
  }
  const close = async () => {
    setBusy(true)
    try { await lmsPresensiService.closeCaptureSession(session.id); setActive(false); setResult({ scan_status: 'closed', message: 'Sesi pemindaian ditutup.' }) }
    finally { setBusy(false) }
  }
  const scan = async (event) => {
    event.preventDefault()
    setBusy(true)
    try {
      const payload = method === 'face' ? { template_reference: identifier, confidence_score: Number(confidence) } : { identifier }
      const response = await lmsPresensiService.scanAttendance(session.id, method, payload)
      setResult(response.data)
      if (response.data.scan_status === 'success') onRecorded?.(response.data)
      setIdentifier('')
      await loadLogs()
    } catch (error) {
      setResult({ scan_status: 'rejected', message: error.response?.data?.message || 'Pemindaian ditolak.' })
    } finally { setBusy(false) }
  }

  if (!session?.id) return <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Simpan Draft terlebih dahulu untuk membuat sesi pemindaian. Checklist manual tetap tersedia.</div>
  if (method === 'fingerprint') return <div className="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700"><div className="flex items-center gap-3"><WifiOff className="text-amber-600" /><div><b className="dark:text-white">Menunggu perangkat fingerprint</b><p className="text-xs text-slate-500">Perangkat mengirim event melalui REST bridge yang diautentikasi API key. Browser tidak membaca template sidik jari.</p></div></div><button type="button" disabled={busy} onClick={active ? close : start} className="rounded-xl bg-[#0E5C44] px-4 py-2 text-sm font-bold text-white">{active ? 'Tutup Sesi' : 'Mulai Sesi'}</button></div>

  return <div className="space-y-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
    <div className="flex flex-wrap items-center justify-between gap-3"><div className="flex items-center gap-2">{active ? <Wifi className="text-emerald-600" /> : <WifiOff className="text-slate-400" />}<div><b className="dark:text-white">{active ? 'Scanner siap' : 'Sesi belum aktif'}</b><p className="text-xs text-slate-500">{method === 'face' ? 'Adapter provider memvalidasi template reference dan confidence.' : 'Masukkan hasil scanner kamera/kartu pada kolom di bawah.'}</p></div></div><button type="button" disabled={busy} onClick={active ? close : start} className="rounded-xl bg-[#0E5C44] px-4 py-2 text-sm font-bold text-white">{active ? 'Tutup Sesi' : 'Mulai Sesi'}</button></div>
    <form onSubmit={scan} className="flex flex-col gap-3 md:flex-row">
      <input required disabled={!active} autoFocus value={identifier} onChange={(e) => setIdentifier(e.target.value)} placeholder={method === 'face' ? 'Template reference dari provider' : method === 'qr' ? 'Payload QR terenkripsi' : 'NIS/NISN dari barcode'} className="min-w-0 flex-1 rounded-xl border border-slate-200 bg-transparent p-3" />
      {method === 'face' && <input type="number" min="0" max="100" value={confidence} onChange={(e) => setConfidence(e.target.value)} className="w-32 rounded-xl border border-slate-200 p-3" title="Confidence" />}
      <button disabled={!active || busy} className="rounded-xl bg-[#1E8E5A] px-5 py-3 font-bold text-white disabled:opacity-50">Proses Scan</button>
    </form>
    {result && <div className={`rounded-xl p-3 text-sm ${result.scan_status === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'}`}><b>{result.student?.full_name || result.scan_status}</b><p>{result.message}</p></div>}
    <div className="overflow-x-auto"><table className="w-full text-left text-xs"><thead><tr className="text-slate-500"><th className="py-2">Waktu</th><th>Siswa</th><th>Metode</th><th>Hasil</th><th>Pesan</th></tr></thead><tbody>{logs.slice(0, 8).map((log) => <tr key={log.id} className="border-t border-slate-100"><td className="py-2">{new Date(log.scanned_at).toLocaleTimeString('id-ID')}</td><td>{log.student?.full_name || '-'}</td><td>{log.scan_method}</td><td>{log.result_status}</td><td>{log.failure_reason || 'Berhasil'}</td></tr>)}</tbody></table></div>
  </div>
}
