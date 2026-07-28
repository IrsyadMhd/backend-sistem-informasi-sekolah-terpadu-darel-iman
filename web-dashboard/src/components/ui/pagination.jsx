import React from 'react'
import PropTypes from 'prop-types'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from './button'

export function Pagination({ currentPage = 1, totalPages = 1, onPageChange, totalItems = 0, itemsPerPage = 10 }) {
  const startItem = (currentPage - 1) * itemsPerPage + 1
  const endItem = Math.min(currentPage * itemsPerPage, totalItems)

  return (
    <div className="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100 dark:border-slate-800">
      <p className="text-xs text-slate-500 font-medium">
        Menampilkan <span className="font-bold text-slate-800 dark:text-white">{totalItems > 0 ? startItem : 0}</span> -{' '}
        <span className="font-bold text-slate-800 dark:text-white">{endItem}</span> dari{' '}
        <span className="font-bold text-slate-800 dark:text-white">{totalItems}</span> data
      </p>

      <div className="flex items-center gap-1.5">
        <Button
          variant="secondary"
          size="sm"
          disabled={currentPage <= 1}
          onClick={() => onPageChange(currentPage - 1)}
          className="gap-1 text-xs font-bold"
        >
          <ChevronLeft className="h-3.5 w-3.5" />
          <span>Sebelumnya</span>
        </Button>

        <div className="flex items-center gap-1">
          {Array.from({ length: totalPages }, (_, i) => i + 1)
            .filter((p) => p === 1 || p === totalPages || Math.abs(p - currentPage) <= 1)
            .map((page) => (
              <button
                key={page}
                type="button"
                onClick={() => onPageChange(page)}
                className={`h-8 w-8 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                  page === currentPage
                    ? 'bg-[#0E5C44] text-white shadow-md font-extrabold dark:bg-[#3FBF75] dark:text-slate-900'
                    : 'text-slate-600 hover:bg-[#0E5C44]/10 hover:text-[#0E5C44] dark:text-slate-300 dark:hover:bg-slate-800'
                }`}
              >
                {page}
              </button>
            ))}
        </div>

        <Button
          variant="secondary"
          size="sm"
          disabled={currentPage >= totalPages}
          onClick={() => onPageChange(currentPage + 1)}
          className="gap-1 text-xs font-bold"
        >
          <span>Selanjutnya</span>
          <ChevronRight className="h-3.5 w-3.5" />
        </Button>
      </div>
    </div>
  )
}

Pagination.propTypes = {
  currentPage: PropTypes.number,
  totalPages: PropTypes.number,
  onPageChange: PropTypes.func.isRequired,
  totalItems: PropTypes.number,
  itemsPerPage: PropTypes.number,
}
