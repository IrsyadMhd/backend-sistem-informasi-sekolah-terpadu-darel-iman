import React from 'react'
import PropTypes from 'prop-types'
import { Plus } from 'lucide-react'
import { cn } from '../../lib/utils'

export function FAB({ onClick, label = 'Tambah Data', icon = <Plus className="h-4 w-4" /> }) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
      className={cn(
        'fixed bottom-20 right-5 z-40 flex items-center gap-2 rounded-full bg-gradient-to-r from-[#0E5C44] to-[#1E8E5A] px-4 py-3 text-xs font-bold text-white shadow-xl hover:shadow-[0_0_20px_rgba(14,92,68,0.4)] hover:scale-105 active:scale-95 transition-all duration-200 md:hidden cursor-pointer btn-master'
      )}
    >
      <span>{icon}</span>
      <span>{label}</span>
    </button>
  )
}

FAB.propTypes = {
  onClick: PropTypes.func.isRequired,
  label: PropTypes.string,
  icon: PropTypes.node,
}
