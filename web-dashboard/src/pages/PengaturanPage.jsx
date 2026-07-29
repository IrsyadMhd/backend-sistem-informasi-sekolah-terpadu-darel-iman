import { useEffect, useMemo, useState } from 'react'
import { AppWindow, Image, LayoutPanelLeft, Palette, RotateCcw, Save, Upload } from 'lucide-react'
import Swal from 'sweetalert2'
import { defaultPengaturan, usePengaturanStore } from '../stores/pengaturanStore'

const tabs = [
  { id: 'identitas', label: 'Identitas Situs', icon: Image },
  { id: 'layout', label: 'Header & Sidebar', icon: LayoutPanelLeft },
  { id: 'tema', label: 'Template & Warna', icon: Palette },
]

const colorFields = [
  ['sidebar_color', 'Warna Sidebar'],
  ['sidebar_accent_color', 'Warna Aksen'],
  ['header_color', 'Warna Header'],
  ['body_color', 'Warna Body'],
]

export default function PengaturanPage() {
  const settings = usePengaturanStore((state) => state.pengaturan)
  const saveSettings = usePengaturanStore((state) => state.simpanPengaturan)
  const loadSettings = usePengaturanStore((state) => state.muatPengaturan)
  const [activeTab, setActiveTab] = useState('identitas')
  const [form, setForm] = useState(settings)
  const [files, setFiles] = useState({})
  const [previews, setPreviews] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => { loadSettings() }, [loadSettings])
  useEffect(() => { setForm(settings) }, [settings])

  const update = (key, value) => setForm((current) => ({ ...current, [key]: value }))
  const previewLogo = previews.logo || form.logo_url
  const previewFavicon = previews.favicon || form.favicon_url

  const previewStyle = useMemo(() => ({
    '--preview-sidebar': form.sidebar_color,
    '--preview-accent': form.sidebar_accent_color,
    '--preview-header': form.header_color,
    '--preview-body': form.body_color,
  }), [form])

  const chooseFile = (type, event) => {
    const file = event.target.files?.[0]
    if (!file) return
    setFiles((current) => ({ ...current, [type]: file }))
    setPreviews((current) => ({ ...current, [type]: URL.createObjectURL(file) }))
    update(`remove_${type}`, false)
  }

  const removeAsset = (type) => {
    setFiles((current) => ({ ...current, [type]: null }))
    setPreviews((current) => ({ ...current, [type]: '' }))
    update(`${type}_url`, '')
    update(`remove_${type}`, true)
  }

  const reset = () => {
    setForm({ ...defaultPengaturan, remove_logo: true, remove_favicon: true })
    setFiles({})
    setPreviews({})
  }

  const submit = async (event) => {
    event.preventDefault()
    setSaving(true)
    try {
      await saveSettings(form, files)
      setFiles({})
      setPreviews({})
      await Swal.fire('Berhasil', 'Pengaturan situs berhasil diterapkan.', 'success')
    } catch (error) {
      const errors = error?.response?.data?.errors
      const message = errors ? Object.values(errors).flat()[0] : 'Pengaturan belum dapat disimpan.'
      await Swal.fire('Gagal', message, 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <form onSubmit={submit} className="space-y-6">
      <div className="flex flex-col gap-4 rounded-[18px] border border-slate-200 bg-white p-5 shadow-sm md:flex-row md:items-center md:justify-between dark:border-slate-800 dark:bg-[#1B2433]">
        <div>
          <div className="flex items-center gap-2">
            <AppWindow className="h-5 w-5 text-emerald-600" />
            <h2 className="text-lg font-black text-slate-900 dark:text-white">Pengaturan Situs</h2>
          </div>
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">Kelola identitas, tata letak, template, dan warna aplikasi.</p>
        </div>
        <div className="flex gap-2">
          <button type="button" onClick={reset} className="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">
            <RotateCcw className="h-4 w-4" /> Reset
          </button>
          <button disabled={saving} className="flex items-center gap-2 rounded-xl bg-[#0E5C44] px-4 py-2 text-xs font-bold text-white shadow-md transition hover:-translate-y-0.5 disabled:opacity-60">
            <Save className="h-4 w-4" /> {saving ? 'Menyimpan...' : 'Simpan Pengaturan'}
          </button>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
        <section className="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-[#1B2433]">
          <div className="flex overflow-x-auto border-b border-slate-200 px-3 dark:border-slate-800">
            {tabs.map(({ id, label, icon: Icon }) => (
              <button key={id} type="button" onClick={() => setActiveTab(id)} className={`flex shrink-0 items-center gap-2 border-b-2 px-4 py-4 text-xs font-bold transition ${activeTab === id ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-white'}`}>
                <Icon className="h-4 w-4" /> {label}
              </button>
            ))}
          </div>

          <div className="p-6">
            {activeTab === 'identitas' && (
              <div className="grid gap-5 md:grid-cols-2">
                <Field label="Nama Aplikasi" value={form.application_name} onChange={(v) => update('application_name', v)} />
                <Field label="Nama Sekolah / Yayasan" value={form.school_name} onChange={(v) => update('school_name', v)} />
                <Field label="Teks Logo (fallback)" value={form.logo_text} maxLength={20} onChange={(v) => update('logo_text', v)} />
                <Field label="Teks Footer Sidebar" value={form.footer_text} onChange={(v) => update('footer_text', v)} />
                <AssetUpload label="Logo Aplikasi" accept="image/png,image/jpeg,image/webp,image/svg+xml" preview={previewLogo} fallback={form.logo_text} onChange={(e) => chooseFile('logo', e)} onRemove={() => removeAsset('logo')} />
                <AssetUpload label="Favicon" accept=".ico,image/png,image/jpeg,image/webp,image/svg+xml" preview={previewFavicon} fallback="ICO" onChange={(e) => chooseFile('favicon', e)} onRemove={() => removeAsset('favicon')} />
              </div>
            )}

            {activeTab === 'layout' && (
              <div className="grid gap-5 md:grid-cols-2">
                <Select label="Gaya Header" value={form.header_style} onChange={(v) => update('header_style', v)} options={[['light', 'Terang'], ['solid', 'Warna Solid'], ['transparent', 'Transparan']]}/>
                <Select label="Gaya Sidebar" value={form.sidebar_style} onChange={(v) => update('sidebar_style', v)} options={[['gradient', 'Gradasi'], ['solid', 'Warna Solid'], ['light', 'Terang']]}/>
                <Select label="Posisi Sidebar" value={form.sidebar_position} onChange={(v) => update('sidebar_position', v)} options={[['left', 'Kiri'], ['right', 'Kanan']]}/>
                <div className="space-y-3">
                  <Toggle label="Header tetap di atas" checked={form.header_sticky} onChange={(v) => update('header_sticky', v)} />
                  <Toggle label="Sidebar mengecil secara default" checked={form.sidebar_collapsed} onChange={(v) => update('sidebar_collapsed', v)} />
                </div>
              </div>
            )}

            {activeTab === 'tema' && (
              <div className="space-y-6">
                <Select label="Kerapatan Template" value={form.template} onChange={(v) => update('template', v)} options={[['modern', 'Modern (Standar)'], ['compact', 'Ringkas'], ['comfortable', 'Lapang']]}/>
                <div className="grid gap-4 sm:grid-cols-2">
                  {colorFields.map(([key, label]) => (
                    <label key={key} className="space-y-2 text-xs font-bold text-slate-700 dark:text-slate-200">
                      <span>{label}</span>
                      <div className="flex items-center gap-2 rounded-xl border border-slate-200 p-2 dark:border-slate-700">
                        <input type="color" value={form[key]} onChange={(e) => update(key, e.target.value.toUpperCase())} className="h-9 w-12 cursor-pointer rounded-lg border-0 bg-transparent" />
                        <input value={form[key]} onChange={(e) => update(key, e.target.value)} pattern="^#[0-9A-Fa-f]{6}$" className="w-full bg-transparent font-mono text-xs outline-none" />
                      </div>
                    </label>
                  ))}
                </div>
              </div>
            )}
          </div>
        </section>

        <aside className="h-fit rounded-[18px] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-[#1B2433]">
          <h3 className="text-sm font-black text-slate-900 dark:text-white">Preview Tampilan</h3>
          <p className="mb-4 mt-1 text-[11px] text-slate-500">Pratinjau otomatis mengikuti pilihan Anda.</p>
          <div style={previewStyle} className="overflow-hidden rounded-2xl border border-slate-200 bg-[var(--preview-body)] shadow-inner">
            <div className="flex h-52">
              <div className="w-24 p-2 text-white" style={{ background: form.sidebar_style === 'gradient' ? `linear-gradient(180deg, ${form.sidebar_color}, ${form.sidebar_color}CC)` : form.sidebar_style === 'light' ? '#FFFFFF' : form.sidebar_color, color: form.sidebar_style === 'light' ? '#334155' : '#fff' }}>
                <div className="mb-4 flex items-center gap-1.5">
                  {previewLogo ? <img src={previewLogo} className="h-6 w-6 rounded object-contain" alt="" /> : <span className="flex h-6 w-6 items-center justify-center rounded bg-[var(--preview-accent)] text-[7px] font-black">{form.logo_text}</span>}
                  <span className="truncate text-[6px] font-bold">{form.school_name}</span>
                </div>
                {[1, 2, 3, 4].map((item) => <div key={item} className={`mb-2 h-4 rounded ${item === 1 ? 'bg-[var(--preview-accent)]' : 'bg-current opacity-10'}`} />)}
              </div>
              <div className="flex-1">
                <div className="h-10 border-b border-black/5" style={{ backgroundColor: form.header_style === 'transparent' ? 'transparent' : form.header_color }} />
                <div className="p-3">
                  <div className="mb-3 h-8 rounded-lg bg-white/80" />
                  <div className="grid grid-cols-2 gap-2"><div className="h-14 rounded-lg bg-white/90" /><div className="h-14 rounded-lg bg-white/90" /></div>
                </div>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </form>
  )
}

function Field({ label, value = '', onChange, ...props }) {
  return <label className="space-y-2 text-xs font-bold text-slate-700 dark:text-slate-200"><span>{label}</span><input {...props} required value={value} onChange={(e) => onChange(e.target.value)} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-medium outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-900" /></label>
}

function Select({ label, value, onChange, options }) {
  return <label className="space-y-2 text-xs font-bold text-slate-700 dark:text-slate-200"><span>{label}</span><select value={value} onChange={(e) => onChange(e.target.value)} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs outline-none focus:border-emerald-600 dark:border-slate-700 dark:bg-slate-900">{options.map(([key, text]) => <option key={key} value={key}>{text}</option>)}</select></label>
}

function Toggle({ label, checked, onChange }) {
  return <label className="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 p-3 text-xs font-bold dark:border-slate-700"><span>{label}</span><input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="h-4 w-4 accent-emerald-600" /></label>
}

function AssetUpload({ label, accept, preview, fallback, onChange, onRemove }) {
  return <div className="space-y-2"><span className="text-xs font-bold text-slate-700 dark:text-slate-200">{label}</span><div className="flex items-center gap-3 rounded-xl border border-dashed border-slate-300 p-3 dark:border-slate-700">{preview ? <img src={preview} alt={label} className="h-14 w-14 rounded-xl border bg-white object-contain p-1" /> : <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-emerald-100 text-xs font-black text-emerald-700">{fallback}</div>}<div className="space-y-1"><label className="flex cursor-pointer items-center gap-1.5 text-xs font-bold text-emerald-700"><Upload className="h-3.5 w-3.5" /> Pilih File<input type="file" accept={accept} onChange={onChange} className="hidden" /></label>{preview && <button type="button" onClick={onRemove} className="block text-[10px] font-semibold text-rose-600">Hapus gambar</button>}<p className="text-[9px] text-slate-400">Maks. 2 MB</p></div></div></div>
}
