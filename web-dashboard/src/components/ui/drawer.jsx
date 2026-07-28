import React from 'react'
import PropTypes from 'prop-types'
import { X } from 'lucide-react'
import { cn } from '../../lib/utils'

export function Drawer({ isOpen, onClose, title, children, position = 'right' }) {
  if (!isOpen) return null

  const posClasses = {
    right: 'right-0 inset-y-0 w-full max-w-md animate-[masterNotificationSlide_0.3s_ease-out]',
    left: 'left-0 inset-y-0 w-full max-w-md animate-in slide-in-from-left duration-300',
    bottom: 'bottom-0 inset-x-0 max-h-[85vh] rounded-t-[18px] animate-in slide-in-from-bottom duration-300',
  }

  return (
    <div className="fixed inset-0 z-50 overflow-hidden">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"
        onClick={onClose}
      />

      {/* Drawer Panel */}
      <div
        className={cn(
          'fixed z-50 flex flex-col bg-white shadow-2xl border-l border-slate-200/80 dark:bg-[#1B2433] dark:border-slate-800',
          posClasses[position] || posClasses.right
        )}
      >
        {/* Header */}
        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
          <h3 className="text-base font-extrabold text-slate-900 dark:text-white">{title}</h3>
          <button
            type="button"
            onClick={onClose}
            className="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition dark:hover:bg-slate-800 dark:hover:text-slate-200 btn-master"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-5">{children}</div>
      </div>
    </div>
  )
}

Drawer.propTypes = {
  isOpen: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  title: PropTypes.node,
  children: PropTypes.node,
  position: PropTypes.oneOf(['right', 'left', 'bottom']),
}
