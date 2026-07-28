import React from 'react'
import PropTypes from 'prop-types'
import { FaInbox } from 'react-icons/fa'
import { Button } from './button'

export function EmptyState({
  icon = <FaInbox className="text-4xl text-emerald-600/70" />,
  title = 'Belum Ada Data',
  description = 'Data yang Anda cari saat ini belum tersedia atau belum diinputkan ke sistem.',
  actionLabel,
  onAction,
}) {
  return (
    <div className="flex flex-col items-center justify-center p-8 text-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 my-4 dark:border-slate-800 dark:bg-slate-900/40">
      <div className="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700 shadow-xs dark:bg-emerald-950/60 dark:text-emerald-300">
        {icon}
      </div>
      <h4 className="text-base font-bold text-slate-800 dark:text-white leading-snug">{title}</h4>
      <p className="mt-1 text-xs text-slate-500 max-w-sm dark:text-slate-400">{description}</p>
      {actionLabel && onAction && (
        <Button variant="primary" size="sm" onClick={onAction} className="mt-4">
          {actionLabel}
        </Button>
      )}
    </div>
  )
}

EmptyState.propTypes = {
  icon: PropTypes.node,
  title: PropTypes.node,
  description: PropTypes.node,
  actionLabel: PropTypes.node,
  onAction: PropTypes.func,
}
