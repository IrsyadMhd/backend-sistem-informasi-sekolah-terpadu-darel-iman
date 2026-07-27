import React, { useState } from 'react'
import PropTypes from 'prop-types'
import {
  useReactTable,
  getCoreRowModel,
  getSortedRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  flexRender,
} from '@tanstack/react-table'
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
} from '../ui/table'
import { Button } from '../ui/button'
import { Input } from '../ui/input'
import {
  LuSearch,
  LuChevronLeft,
  LuChevronRight,
  LuChevronsLeft,
  LuChevronsRight,
  LuArrowUpDown,
  LuArrowUp,
  LuArrowDown,
  LuInbox,
} from 'react-icons/lu'

export function DataTable({
  columns,
  data,
  isLoading = false,
  searchPlaceholder = 'Cari data...',
  onSearchChange,
  searchValue = '',
  filterComponent = null,
}) {
  const [sorting, setSorting] = useState([])
  const [globalFilter, setGlobalFilter] = useState('')

  const table = useReactTable({
    data,
    columns,
    state: {
      sorting,
      globalFilter: onSearchChange ? searchValue : globalFilter,
    },
    onSortingChange: setSorting,
    onGlobalFilterChange: onSearchChange ? onSearchChange : setGlobalFilter,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
  })

  return (
    <div className="space-y-4">
      {/* Header Toolbar: Search & Dynamic Filters */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between bg-slate-900/60 p-4 rounded-xl border border-slate-800">
        <div className="relative flex-1 max-w-sm">
          <LuSearch className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
          <Input
            placeholder={searchPlaceholder}
            value={onSearchChange ? searchValue : globalFilter ?? ''}
            onChange={(e) => {
              if (onSearchChange) {
                onSearchChange(e.target.value)
              } else {
                setGlobalFilter(e.target.value)
              }
            }}
            className="pl-9 bg-slate-950/60 border-slate-800"
          />
        </div>

        {filterComponent && (
          <div className="flex items-center gap-2">
            {filterComponent}
          </div>
        )}
      </div>

      {/* TanStack Table Grid */}
      <Table>
        <TableHeader>
          {table.getHeaderGroups().map((headerGroup) => (
            <TableRow key={headerGroup.id} className="border-slate-800 hover:bg-transparent">
              {headerGroup.headers.map((header) => (
                <TableHead key={header.id} style={{ width: header.getSize() !== 150 ? header.getSize() : undefined }}>
                  {header.isPlaceholder ? null : (
                    <div
                      className={
                        header.column.getCanSort()
                          ? 'flex items-center space-x-2 cursor-pointer select-none font-semibold text-slate-300 hover:text-emerald-400'
                          : 'font-semibold text-slate-300'
                      }
                      onClick={header.column.getToggleSortingHandler()}
                    >
                      {flexRender(header.column.columnDef.header, header.getContext())}
                      {header.column.getCanSort() && (
                        <span>
                          {header.column.getIsSorted() === 'asc' ? (
                            <LuArrowUp className="h-4 w-4 text-emerald-400" />
                          ) : header.column.getIsSorted() === 'desc' ? (
                            <LuArrowDown className="h-4 w-4 text-emerald-400" />
                          ) : (
                            <LuArrowUpDown className="h-3.5 w-3.5 text-slate-500 opacity-60" />
                          )}
                        </span>
                      )}
                    </div>
                  )}
                </TableHead>
              ))}
            </TableRow>
          ))}
        </TableHeader>

        <TableBody>
          {isLoading ? (
            Array.from({ length: 5 }).map((_, index) => (
              <TableRow key={index} className="animate-pulse">
                {columns.map((col, cIdx) => (
                  <TableCell key={cIdx}>
                    <div className="h-4 bg-slate-800 rounded w-3/4"></div>
                  </TableCell>
                ))}
              </TableRow>
            ))
          ) : table.getRowModel().rows?.length ? (
            table.getRowModel().rows.map((row) => (
              <TableRow key={row.id} data-state={row.getIsSelected() && 'selected'}>
                {row.getVisibleCells().map((cell) => (
                  <TableCell key={cell.id}>
                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                  </TableCell>
                ))}
              </TableRow>
            ))
          ) : (
            <TableRow>
              <TableCell colSpan={columns.length} className="h-32 text-center text-slate-400">
                <div className="flex flex-col items-center justify-center space-y-2">
                  <LuInbox className="h-8 w-8 text-slate-600" />
                  <p className="text-sm font-medium">Tidak ada data ditemukan</p>
                </div>
              </TableCell>
            </TableRow>
          )}
        </TableBody>
      </Table>

      {/* Pagination Controls */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-2 py-1 text-sm text-slate-400">
        <div className="flex items-center space-x-2">
          <span>Menampilkan</span>
          <select
            value={table.getState().pagination.pageSize}
            onChange={(e) => table.setPageSize(Number(e.target.value))}
            className="h-8 rounded-md border border-slate-700 bg-slate-900 px-2 py-1 text-xs text-slate-200 focus:outline-none"
          >
            {[5, 10, 20, 50].map((pageSize) => (
              <option key={pageSize} value={pageSize}>
                {pageSize} per halaman
              </option>
            ))}
          </select>
          <span>
            dari <strong className="text-slate-200">{table.getFilteredRowModel().rows.length}</strong> data
          </span>
        </div>

        <div className="flex items-center space-x-2 justify-end">
          <span className="text-xs mr-2">
            Halaman {table.getState().pagination.pageIndex + 1} dari {table.getPageCount() || 1}
          </span>
          <Button
            variant="outline"
            size="icon"
            onClick={() => table.setPageIndex(0)}
            disabled={!table.getCanPreviousPage()}
          >
            <LuChevronsLeft className="h-4 w-4" />
          </Button>
          <Button
            variant="outline"
            size="icon"
            onClick={() => table.previousPage()}
            disabled={!table.getCanPreviousPage()}
          >
            <LuChevronLeft className="h-4 w-4" />
          </Button>
          <Button
            variant="outline"
            size="icon"
            onClick={() => table.nextPage()}
            disabled={!table.getCanNextPage()}
          >
            <LuChevronRight className="h-4 w-4" />
          </Button>
          <Button
            variant="outline"
            size="icon"
            onClick={() => table.setPageIndex(table.getPageCount() - 1)}
            disabled={!table.getCanNextPage()}
          >
            <LuChevronsRight className="h-4 w-4" />
          </Button>
        </div>
      </div>
    </div>
  )
}

DataTable.propTypes = {
  columns: PropTypes.array.isRequired,
  data: PropTypes.array.isRequired,
  isLoading: PropTypes.bool,
  searchPlaceholder: PropTypes.string,
  onSearchChange: PropTypes.func,
  searchValue: PropTypes.string,
  filterComponent: PropTypes.node,
}
