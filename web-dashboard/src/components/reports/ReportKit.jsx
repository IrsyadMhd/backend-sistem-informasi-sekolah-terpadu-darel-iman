const angka = (nilai) => new Intl.NumberFormat('id-ID').format(Number(nilai || 0))

export function ReportHeader({ title, description, onRefresh, onExport }) {
  return (
    <div className="panel-title-row laporan-header">
      <div>
        <h3>{title}</h3>
        <p className="modul-lead">{description}</p>
      </div>
      <div className="panel-aksi-laporan">
        <button type="button" className="topbar-action" onClick={onRefresh}>Muat Ulang</button>
        <button type="button" className="topbar-action" onClick={() => window.print()}>Cetak / PDF</button>
        {onExport && <button type="button" className="topbar-action" onClick={onExport}>Export CSV</button>}
      </div>
    </div>
  )
}

export function ReportFilters({ children }) {
  return <div className="laporan-filter">{children}</div>
}

export function ReportStats({ items }) {
  return (
    <div className="stats-grid">
      {items.map((item) => (
        <div className="stat-card" key={item.label}>
          <h4>{item.label}</h4>
          <strong>{angka(item.value)}</strong>
          <p>{item.note}</p>
        </div>
      ))}
    </div>
  )
}

export function ReportTable({ columns, rows, empty = 'Belum ada data pada filter ini.' }) {
  return (
    <div className="laporan-table-wrap">
      <table className="laporan-table">
        <thead>
          <tr>{columns.map((column) => <th key={column.key}>{column.label}</th>)}</tr>
        </thead>
        <tbody>
          {rows.length ? rows.map((row, index) => (
            <tr key={row.id || index}>
              {columns.map((column) => (
                <td key={column.key}>{column.render ? column.render(row) : (row[column.key] ?? '-')}</td>
              ))}
            </tr>
          )) : (
            <tr><td colSpan={columns.length} className="laporan-empty">{empty}</td></tr>
          )}
        </tbody>
      </table>
    </div>
  )
}

export function ReportState({ loading, error, children }) {
  if (loading) return <div className="laporan-state">Memuat data laporan...</div>
  if (error) return <div className="laporan-state laporan-state--error">{error}</div>
  return children
}

export function exportCsv(filename, columns, rows) {
  const escape = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`
  const header = columns.map((column) => escape(column.label)).join(',')
  const body = rows.map((row) => columns.map((column) => (
    escape(column.export ? column.export(row) : row[column.key])
  )).join(','))
  const blob = new Blob([`\uFEFF${[header, ...body].join('\n')}`], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

