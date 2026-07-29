import React from 'react'
import {
  AlertTriangle,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  Database,
  Eye,
  FileInput,
  FileSpreadsheet,
  Filter,
  LoaderCircle,
  Pencil,
  Plus,
  Search,
  Trash2,
  X,
} from 'lucide-react'

export const masterStyles = {
  card: 'rounded-[18px] border border-slate-200/80 bg-white shadow-[var(--shadow-soft-xl)]',
  control:
    'h-12 rounded-[14px] border border-slate-200 bg-white text-xs font-semibold text-slate-700 outline-none transition focus:border-emerald-700 focus:ring-3 focus:ring-emerald-700/15 disabled:cursor-not-allowed disabled:opacity-50',
  label: 'mb-1.5 block text-sm font-semibold text-slate-800',
  error: 'mt-1.5 text-xs font-medium text-rose-600',
}

export function MasterDataPage({ children, className = '' }) {
  return <div className={`master-data-page space-y-6 pb-12 ${className}`}>{children}</div>
}

export function MasterPageHeader({ title, description, actions }) {
  return (
    <header className={`${masterStyles.card} master-page-header ui-enter p-6 md:p-8`}>
      <div className="flex flex-col justify-between gap-5 md:flex-row md:items-center">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-slate-900 md:text-3xl">{title}</h1>
          {description && <p className="mt-1.5 max-w-2xl text-xs text-slate-500">{description}</p>}
        </div>
        {actions && <MasterHeaderActions>{actions}</MasterHeaderActions>}
      </div>
    </header>
  )
}

export function MasterHeaderActions({ children }) {
  return <div className="grid grid-cols-1 gap-2.5 sm:flex sm:flex-wrap sm:items-center">{children}</div>
}

const actionVariants = {
  export: { Icon: FileSpreadsheet, iconClass: 'text-emerald-700', buttonClass: 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' },
  import: { Icon: FileInput, iconClass: 'text-slate-500', buttonClass: 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' },
  primary: { Icon: Plus, iconClass: '', buttonClass: 'border-emerald-800 bg-emerald-800 text-white shadow-lg shadow-emerald-800/20 hover:bg-emerald-900' },
}

export function MasterActionButton({ variant = 'primary', icon: CustomIcon, children, className = '', ...props }) {
  const config = actionVariants[variant] || actionVariants.primary
  const Icon = CustomIcon || config.Icon
  return (
    <button
      type="button"
      className={`ui-button inline-flex h-12 items-center justify-center gap-2.5 rounded-[14px] border px-4 text-xs font-semibold transition-all hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-emerald-700/20 disabled:pointer-events-none disabled:opacity-50 ${config.buttonClass} ${className}`}
      {...props}
    >
      <Icon className={`h-4 w-4 ${config.iconClass}`} />
      {children}
    </button>
  )
}

export function MasterStatsGrid({ children }) {
  return <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">{children}</section>
}

const statVariants = {
  success: 'border-emerald-100 bg-emerald-50 text-emerald-600',
  warning: 'border-amber-100 bg-amber-50 text-amber-600',
  info: 'border-sky-100 bg-sky-50 text-sky-600',
  danger: 'border-rose-100 bg-rose-50 text-rose-600',
  neutral: 'border-slate-200 bg-slate-50 text-slate-600',
}

export function MasterStatCard({ icon: Icon, label, value, description, variant = 'success', delay = 0 }) {
  return (
    <article className={`${masterStyles.card} ui-card ui-enter flex min-h-29 items-center gap-4 p-5`} style={{ animationDelay: `${delay}ms` }}>
      <div className={`shrink-0 rounded-xl border p-3.5 ${statVariants[variant] || statVariants.success}`}>
        {Icon && <Icon className="h-6 w-6" />}
      </div>
      <div className="min-w-0">
        <p className="truncate text-xs font-bold uppercase tracking-wider text-slate-400">{label}</p>
        <p className="mt-0.5 text-2xl font-black text-slate-900">{value ?? 0}</p>
        {description && <p className={`mt-0.5 text-xs font-medium ${statVariants[variant]?.split(' ').at(-1) || 'text-emerald-600'}`}>{description}</p>}
      </div>
    </article>
  )
}

export function MasterFilterBar({ search, filters, children }) {
  return (
    <section className={`${masterStyles.card} ui-enter flex flex-col gap-4 p-4 md:flex-row md:items-center`}>
      {search}
      {(filters || children) && (
        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
          <span className="inline-flex items-center gap-1.5 text-sm font-bold text-slate-600">
            <Filter className="h-4 w-4 text-emerald-600" /> Filter:
          </span>
          {filters || children}
        </div>
      )}
    </section>
  )
}

export function MasterSearchInput({ className = '', ...props }) {
  return (
    <div className={`relative min-w-0 flex-1 ${className}`}>
      <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
      <input type="search" className={`${masterStyles.control} w-full rounded-full! pl-10 pr-4`} {...props} />
    </div>
  )
}

export function MasterFilterSelect({ className = '', children, ...props }) {
  return (
    <div className={`relative min-w-38 ${className}`}>
      <select className={`${masterStyles.control} w-full appearance-none px-3.5 pr-9`} {...props}>{children}</select>
      <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
    </div>
  )
}

export function MasterDataTable({ children, className = '' }) {
  return <section className={`${masterStyles.card} master-table ui-enter overflow-hidden ${className}`}><div className="overflow-x-auto">{children}</div></section>
}

const badgeVariants = {
  success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  danger: 'border-rose-200 bg-rose-50 text-rose-700',
  warning: 'border-amber-200 bg-amber-50 text-amber-700',
  info: 'border-sky-200 bg-sky-50 text-sky-700',
  neutral: 'border-slate-200 bg-slate-100 text-slate-600',
}

export function MasterBadge({ variant = 'neutral', children, color, className = '' }) {
  return <span style={color ? { borderColor: color, color } : undefined} className={`inline-flex min-h-6 items-center gap-2 rounded-full border px-2.5 py-0.5 text-xs font-semibold ${badgeVariants[variant] || badgeVariants.neutral} ${className}`}>{children}</span>
}

export function MasterStatusBadge({ active, activeLabel = 'Aktif', inactiveLabel = 'Tidak Aktif' }) {
  return <MasterBadge variant={active ? 'success' : 'danger'}><span className={`h-1.5 w-1.5 rounded-full ${active ? 'bg-emerald-500' : 'bg-rose-500'}`} />{active ? activeLabel : inactiveLabel}</MasterBadge>
}

export function MasterActionGroup({ children }) {
  return <div className="inline-flex items-center gap-2">{children}</div>
}

const iconButtonVariants = {
  view: { Icon: Eye, label: 'Lihat Detail', className: 'border-sky-200 bg-sky-50 text-sky-600 hover:bg-sky-100' },
  edit: { Icon: Pencil, label: 'Edit Data', className: 'border-amber-200 bg-amber-50 text-amber-600 hover:bg-amber-100' },
  delete: { Icon: Trash2, label: 'Hapus Data', className: 'border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100' },
}

export function MasterActionIconButton({ variant = 'view', icon: CustomIcon, label, loading = false, ...props }) {
  const config = iconButtonVariants[variant] || iconButtonVariants.view
  const Icon = loading ? LoaderCircle : (CustomIcon || config.Icon)
  const title = label || config.label
  return <button type="button" title={title} aria-label={title} className={`inline-flex h-10 w-10 items-center justify-center rounded-xl border transition hover:scale-105 focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-emerald-700/20 disabled:pointer-events-none disabled:opacity-50 ${config.className}`} {...props}><Icon className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} /></button>
}

export function MasterPagination({ meta = {}, page = 1, onPageChange, label = 'data' }) {
  if (!(meta.total > 0)) return null
  const lastPage = meta.last_page || 1
  return (
    <footer className={`${masterStyles.card} flex flex-col justify-between gap-4 p-4 text-xs font-medium text-slate-600 sm:flex-row sm:items-center`}>
      <p>Menampilkan <strong>{meta.from || 0}</strong>–<strong>{meta.to || 0}</strong> dari <strong>{meta.total || 0}</strong> {label}</p>
      <div className="flex items-center justify-between gap-2">
        <button type="button" onClick={() => onPageChange(Math.max(page - 1, 1))} disabled={page <= 1} className="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 transition hover:bg-emerald-50 disabled:opacity-40"><ChevronLeft className="h-3.5 w-3.5" /> Prev</button>
        <span className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 font-bold text-emerald-800">{meta.current_page || page} / {lastPage}</span>
        <button type="button" onClick={() => onPageChange(Math.min(page + 1, lastPage))} disabled={page >= lastPage} className="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 transition hover:bg-emerald-50 disabled:opacity-40">Next <ChevronRight className="h-3.5 w-3.5" /></button>
      </div>
    </footer>
  )
}

function ModalShell({ isOpen, onClose, icon: Icon = Database, title, description, children, footer, maxWidth = 'max-w-2xl', tone = 'emerald' }) {
  if (!isOpen) return null
  return (
    <div className="ui-backdrop fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 backdrop-blur-sm sm:p-4" role="dialog" aria-modal="true" aria-label={title}>
      <div className={`ui-modal my-auto flex max-h-[calc(100vh-1.5rem)] w-full flex-col overflow-hidden rounded-[18px] border border-slate-200/80 bg-white shadow-2xl sm:max-h-[calc(100vh-2rem)] ${maxWidth}`}>
        <div className="flex shrink-0 items-center justify-between border-b border-slate-100 p-5">
          <div className="flex min-w-0 items-center gap-3">
            <div className={`rounded-xl p-2.5 ${tone === 'rose' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'}`}><Icon className="h-5 w-5" /></div>
            <div className="min-w-0"><h2 className="truncate text-lg font-bold text-slate-800">{title}</h2>{description && <p className="text-xs text-slate-500">{description}</p>}</div>
          </div>
          <button type="button" onClick={onClose} aria-label="Tutup modal" className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"><X className="h-5 w-5" /></button>
        </div>
        <div className="min-h-0 overflow-y-auto">{children}</div>
        {footer && <div className="shrink-0 border-t border-slate-100 bg-white p-4">{footer}</div>}
      </div>
    </div>
  )
}

export function MasterFormModal(props) { return <ModalShell {...props} /> }
export function MasterDetailModal(props) { return <ModalShell maxWidth="max-w-xl" {...props} /> }

export function MasterDeleteDialog({ isOpen, onClose, onConfirm, title = 'Hapus data?', description, isLoading = false }) {
  return <ModalShell isOpen={isOpen} onClose={onClose} icon={Trash2} tone="rose" title={title} description={description} maxWidth="max-w-md" footer={<div className="flex justify-end gap-2"><button type="button" onClick={onClose} className="h-11 rounded-xl border border-slate-200 px-4 text-xs font-semibold text-slate-700">Batal</button><button type="button" onClick={onConfirm} disabled={isLoading} className="inline-flex h-11 items-center gap-2 rounded-xl bg-rose-600 px-4 text-xs font-semibold text-white disabled:opacity-50">{isLoading && <LoaderCircle className="h-4 w-4 animate-spin" />}Hapus</button></div>}><div className="p-6 text-sm text-slate-600"><div className="rounded-xl border border-rose-100 bg-rose-50 p-4"><AlertTriangle className="mb-2 h-5 w-5 text-rose-600" />Tindakan ini tidak dapat dibatalkan.</div></div></ModalShell>
}

export function MasterLoadingState({ label = 'Memuat data...' }) {
  return <div className={`${masterStyles.card} p-12 text-center`}><LoaderCircle className="mx-auto h-8 w-8 animate-spin text-emerald-600" /><p className="mt-3 text-sm font-medium text-emerald-700">{label}</p></div>
}

export function MasterEmptyState({ title = 'Data Tidak Ditemukan', description = 'Belum ada data yang sesuai dengan kriteria.', action }) {
  return <div className={`${masterStyles.card} p-12 text-center`}><div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><Database className="h-8 w-8" /></div><h3 className="font-semibold text-slate-800">{title}</h3><p className="mt-1 text-sm text-slate-500">{description}</p>{action && <div className="mt-5">{action}</div>}</div>
}

export function MasterErrorState({ title = 'Data gagal dimuat', description = 'Terjadi kesalahan saat mengambil data.', onRetry }) {
  return <div className={`${masterStyles.card} border-rose-200 p-8 text-center`}><AlertTriangle className="mx-auto h-8 w-8 text-rose-500" /><h3 className="mt-3 font-semibold text-slate-800">{title}</h3><p className="mt-1 text-sm text-slate-500">{description}</p>{onRetry && <button type="button" onClick={onRetry} className="mt-4 h-10 rounded-xl bg-emerald-800 px-4 text-xs font-semibold text-white">Coba Lagi</button>}</div>
}
