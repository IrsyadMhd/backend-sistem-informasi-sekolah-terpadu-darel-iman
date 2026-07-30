import { useMemo, useState } from 'react'
import {
  BarChart3, CalendarDays, ChevronLeft, ChevronRight, Download,
  Eye, FileDown, FileSpreadsheet, MoreVertical, Printer, RotateCcw,
} from 'lucide-react'
import {
  CartesianGrid, Cell, Legend, Line, LineChart, Pie, PieChart,
  ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts'
import { exportCsv } from './ReportKit'

const formatNumber = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(Number(value || 0))

export default function ReportDashboard({
  title,
  breadcrumb,
  description,
  loading,
  error,
  kpis,
  trendData,
  trendLines,
  distribution,
  columns,
  rows,
  filters,
  onReset,
  onApply,
  onRefresh,
  exportName,
}) {
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(10)
  const pages = Math.max(1, Math.ceil(rows.length / perPage))
  const safePage = Math.min(page, pages)
  const visibleRows = useMemo(
    () => rows.slice((safePage - 1) * perPage, safePage * perPage),
    [rows, perPage, safePage],
  )
  const totalDistribution = distribution.reduce((sum, item) => sum + Number(item.value || 0), 0)

  const exportColumns = columns.map((column) => ({
    ...column,
    export: column.export || column.value,
  }))

  return (
    <section className="report-page">
      <header className="report-page__header">
        <div>
          <h1>{title}</h1>
          <div className="report-breadcrumb">
            <span>Dashboard</span><ChevronRight size={13} /><span>Laporan</span><ChevronRight size={13} />
            <strong>{breadcrumb || title}</strong>
          </div>
          {description && <p>{description}</p>}
        </div>
        <div className="report-header-actions">
          <button type="button" onClick={() => exportCsv(`${exportName}.csv`, exportColumns, rows)}><FileSpreadsheet size={17} /> Export Excel</button>
          <button type="button" onClick={() => window.print()}><FileDown size={17} /> Export PDF</button>
          <button type="button" className="report-primary-button" onClick={() => window.print()}><Printer size={17} /> Cetak Laporan</button>
        </div>
      </header>

      {error ? <div className="report-feedback report-feedback--error">{error}</div> : null}
      {loading ? <div className="report-feedback">Memuat data laporan...</div> : (
        <div className="report-layout">
          <main className="report-main">
            <div className="report-kpi-grid">
              {kpis.map((item, index) => {
                const Icon = item.icon || BarChart3
                return (
                  <article className={`report-kpi report-kpi--${item.tone || ['green', 'blue', 'orange', 'red'][index % 4]}`} key={item.label}>
                    <div className="report-kpi__icon"><Icon size={23} /></div>
                    <div>
                      <span>{item.label}</span>
                      <div className="report-kpi__value">
                        <strong>{formatNumber(item.value)}</strong>
                        {item.percent !== undefined && <em>{formatNumber(item.percent)}%</em>}
                      </div>
                      <small>{item.note}</small>
                    </div>
                  </article>
                )
              })}
            </div>

            <div className="report-chart-grid">
              <article className="report-card">
                <div className="report-card__title"><h2>Grafik {breadcrumb || title}</h2><span>Data terkini</span></div>
                <div className="report-chart">
                  {trendData.length ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <LineChart data={trendData} margin={{ top: 15, right: 20, left: -16, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#edf1f5" />
                        <XAxis dataKey="name" tick={{ fontSize: 11, fill: '#64748b' }} axisLine={false} tickLine={false} />
                        <YAxis tick={{ fontSize: 11, fill: '#64748b' }} axisLine={false} tickLine={false} />
                        <Tooltip contentStyle={{ borderRadius: 12, border: '1px solid #e2e8f0' }} />
                        <Legend iconType="circle" iconSize={8} wrapperStyle={{ fontSize: 12 }} />
                        {trendLines.map((line) => <Line key={line.key} type="monotone" dataKey={line.key} name={line.label} stroke={line.color} strokeWidth={2.5} dot={{ r: 3 }} activeDot={{ r: 5 }} />)}
                      </LineChart>
                    </ResponsiveContainer>
                  ) : <div className="report-chart-empty"><BarChart3 size={34} />Belum cukup data untuk grafik.</div>}
                </div>
              </article>

              <article className="report-card">
                <div className="report-card__title"><h2>Distribusi Data</h2><span>Total {formatNumber(totalDistribution)}</span></div>
                <div className="report-donut-layout">
                  <div className="report-donut">
                    <ResponsiveContainer width="100%" height="100%">
                      <PieChart>
                        <Pie data={distribution} dataKey="value" nameKey="label" innerRadius={62} outerRadius={88} paddingAngle={1}>
                          {distribution.map((item) => <Cell key={item.label} fill={item.color} />)}
                        </Pie>
                        <Tooltip formatter={(value) => formatNumber(value)} />
                      </PieChart>
                    </ResponsiveContainer>
                    <div className="report-donut__center"><strong>{formatNumber(totalDistribution)}</strong><span>Total Data</span></div>
                  </div>
                  <div className="report-legend">
                    {distribution.map((item) => (
                      <div key={item.label}><i style={{ background: item.color }} /><span>{item.label}</span><strong>{formatNumber(item.value)}</strong></div>
                    ))}
                  </div>
                </div>
              </article>
            </div>

            <article className="report-card report-table-card">
              <div className="report-card__title"><h2>Rincian {breadcrumb || title}</h2><span>{rows.length} data</span></div>
              <div className="report-table-scroll">
                <table>
                  <thead><tr><th>No</th>{columns.map((column) => <th key={column.key}>{column.label}</th>)}<th>Aksi</th></tr></thead>
                  <tbody>
                    {visibleRows.length ? visibleRows.map((row, index) => (
                      <tr key={row.id || index}>
                        <td>{(safePage - 1) * perPage + index + 1}</td>
                        {columns.map((column) => <td key={column.key}>{column.render ? column.render(row) : (row[column.key] ?? '-')}</td>)}
                        <td><div className="report-row-actions"><button type="button" aria-label="Lihat"><Eye size={15} /></button><button type="button" aria-label="Menu"><MoreVertical size={15} /></button></div></td>
                      </tr>
                    )) : <tr><td colSpan={columns.length + 2} className="report-empty">Belum ada data pada filter ini.</td></tr>}
                  </tbody>
                </table>
              </div>
              <footer className="report-pagination">
                <div>Menampilkan {rows.length ? (safePage - 1) * perPage + 1 : 0}–{Math.min(safePage * perPage, rows.length)} dari {rows.length} data</div>
                <select value={perPage} onChange={(event) => { setPerPage(Number(event.target.value)); setPage(1) }}><option>5</option><option>10</option><option>25</option></select>
                <div className="report-pagination__buttons">
                  <button type="button" disabled={safePage === 1} onClick={() => setPage((value) => Math.max(1, value - 1))}><ChevronLeft size={16} /></button>
                  <span>{safePage}</span>
                  <button type="button" disabled={safePage === pages} onClick={() => setPage((value) => Math.min(pages, value + 1))}><ChevronRight size={16} /></button>
                </div>
              </footer>
            </article>
          </main>

          <aside className="report-sidebar">
            <article className="report-card report-filter-card">
              <div className="report-card__title"><h2>Filter Laporan</h2></div>
              <div className="report-filter-fields">{filters}</div>
              <div className="report-filter-actions">
                <button type="button" onClick={onReset}><RotateCcw size={15} /> Reset</button>
                <button type="button" className="report-primary-button" onClick={onApply}>Terapkan</button>
              </div>
            </article>
            <article className="report-card report-quick-card">
              <div className="report-card__title"><h2>Aksi Cepat</h2></div>
              <button type="button" onClick={() => exportCsv(`${exportName}.csv`, exportColumns, rows)}><FileSpreadsheet size={19} /><span><strong>Export Excel</strong><small>Unduh data dalam format spreadsheet</small></span></button>
              <button type="button" onClick={() => window.print()}><FileDown size={19} /><span><strong>Export PDF</strong><small>Simpan laporan sebagai PDF</small></span></button>
              <button type="button" onClick={() => window.print()}><Printer size={19} /><span><strong>Cetak Laporan</strong><small>Cetak tampilan laporan</small></span></button>
              <button type="button" onClick={onRefresh}><CalendarDays size={19} /><span><strong>Perbarui Data</strong><small>Muat data terbaru dari sistem</small></span></button>
            </article>
          </aside>
        </div>
      )}
    </section>
  )
}

