import * as React from 'react'
import PropTypes from 'prop-types'
import { cn } from '../../lib/utils'

const badgeVariants = {
  default: 'bg-slate-800 text-slate-200 border-slate-700',
  success: 'bg-emerald-950/80 text-emerald-300 border-emerald-800/60',
  warning: 'bg-amber-950/80 text-amber-300 border-amber-800/60',
  danger: 'bg-red-950/80 text-red-300 border-red-800/60',
  info: 'bg-blue-950/80 text-blue-300 border-blue-800/60',
  outline: 'bg-transparent text-slate-300 border-slate-700',
}

export function Badge({ className, variant = 'default', ...props }) {
  return (
    <div
      className={cn(
        'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
        badgeVariants[variant] || badgeVariants.default,
        className
      )}
      {...props}
    />
  )
}

Badge.propTypes = {
  className: PropTypes.string,
  variant: PropTypes.oneOf(['default', 'success', 'warning', 'danger', 'info', 'outline']),
}
