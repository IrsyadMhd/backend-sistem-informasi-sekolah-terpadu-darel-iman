import * as React from 'react'
import PropTypes from 'prop-types'
import { cn } from '../../lib/utils'

const badgeVariants = {
  primary: 'bg-[#0E5C44]/10 text-[#0E5C44] border-[#0E5C44]/20 font-bold dark:bg-[#3FBF75]/20 dark:text-[#3FBF75] dark:border-[#3FBF75]/30',
  default: 'bg-slate-100 text-slate-700 border-slate-200/80 font-semibold dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700',
  success: 'bg-[#0E5C44]/10 text-[#0E5C44] border-[#0E5C44]/20 font-bold dark:bg-[#3FBF75]/20 dark:text-[#3FBF75] dark:border-[#3FBF75]/30',
  warning: 'bg-amber-50 text-amber-700 border-amber-200/80 font-bold dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/80',
  danger: 'bg-rose-50 text-rose-700 border-rose-200/80 font-bold dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800/80',
  info: 'bg-sky-50 text-sky-700 border-sky-200/80 font-bold dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800/80',
  outline: 'bg-transparent text-slate-600 border-slate-300/80 font-semibold dark:text-slate-300 dark:border-slate-700',
}

export function Badge({ className, variant = 'default', ...props }) {
  return (
    <div
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500/20',
        badgeVariants[variant] || badgeVariants.default,
        className
      )}
      {...props}
    />
  )
}

Badge.propTypes = {
  className: PropTypes.string,
  variant: PropTypes.oneOf(['primary', 'default', 'success', 'warning', 'danger', 'info', 'outline']),
}
