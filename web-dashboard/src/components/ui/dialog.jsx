import * as React from 'react'
import PropTypes from 'prop-types'
import { LuX } from 'react-icons/lu'
import { cn } from '../../lib/utils'

export function Dialog({ isOpen, onClose, children, className }) {
  if (!isOpen) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/80 p-4 backdrop-blur-sm animate-in fade-in duration-200">
      <div
        className={cn(
          'relative w-full max-w-lg rounded-xl border border-slate-800 bg-slate-900 p-6 shadow-2xl transition-all text-slate-100',
          className
        )}
      >
        <button
          onClick={onClose}
          className="absolute right-4 top-4 rounded-md p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white transition-colors"
        >
          <LuX className="h-5 w-5" />
          <span className="sr-only">Close</span>
        </button>
        {children}
      </div>
    </div>
  )
}

export function DialogHeader({ className, ...props }) {
  return <div className={cn('flex flex-col space-y-1.5 text-center sm:text-left mb-4', className)} {...props} />
}

export function DialogTitle({ className, ...props }) {
  return <h2 className={cn('text-lg font-semibold leading-none tracking-tight text-white', className)} {...props} />
}

export function DialogDescription({ className, ...props }) {
  return <p className={cn('text-sm text-slate-400 mt-1', className)} {...props} />
}

export function DialogFooter({ className, ...props }) {
  return <div className={cn('flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2 mt-6 border-t border-slate-800 pt-4', className)} {...props} />
}

Dialog.propTypes = {
  isOpen: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  children: PropTypes.node,
  className: PropTypes.string,
}

DialogHeader.propTypes = { className: PropTypes.string }
DialogTitle.propTypes = { className: PropTypes.string }
DialogDescription.propTypes = { className: PropTypes.string }
DialogFooter.propTypes = { className: PropTypes.string }
