import * as React from 'react'
import PropTypes from 'prop-types'
import { cn } from '../../lib/utils'

const buttonVariants = {
  primary: 'bg-[#0E5C44] text-white hover:bg-[#1E8E5A] shadow-md hover:-translate-y-0.5 hover:scale-[1.03] active:scale-[0.97] active:translate-y-0 hover:shadow-[0_0_20px_rgba(14,92,68,0.35)] dark:bg-[#0E5C44] dark:hover:bg-[#1E8E5A]',
  default: 'bg-[#0E5C44] text-white hover:bg-[#1E8E5A] shadow-md hover:-translate-y-0.5 hover:scale-[1.03] active:scale-[0.97] active:translate-y-0 hover:shadow-[0_0_20px_rgba(14,92,68,0.35)] dark:bg-[#0E5C44] dark:hover:bg-[#1E8E5A]',
  secondary: 'bg-white border border-slate-200/80 text-slate-700 hover:bg-slate-50 hover:border-[#0E5C44]/30 hover:-translate-y-0.5 hover:scale-[1.03] active:scale-[0.97] active:translate-y-0 shadow-xs dark:bg-[#111827] dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-800',
  destructive: 'bg-rose-600 text-white hover:bg-rose-700 shadow-md hover:-translate-y-0.5 hover:scale-[1.03] active:scale-[0.97] active:translate-y-0',
  outline: 'border border-slate-200/80 bg-transparent text-slate-700 hover:bg-slate-50 hover:border-[#0E5C44]/40 hover:-translate-y-0.5 hover:scale-[1.03] active:scale-[0.97] active:translate-y-0 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-800',
  ghost: 'text-slate-600 hover:bg-[#0E5C44]/10 hover:text-[#0E5C44] active:scale-[0.97] dark:text-slate-400 dark:hover:bg-[#3FBF75]/20 dark:hover:text-[#3FBF75]',
  icon: 'text-slate-600 border border-slate-200/80 bg-white hover:bg-slate-50 hover:text-[#0E5C44] hover:border-[#0E5C44]/30 hover:scale-[1.03] active:scale-[0.96] p-2 rounded-xl dark:border-slate-800 dark:bg-[#111827] dark:text-slate-300 dark:hover:bg-slate-800',
  link: 'text-[#0E5C44] underline-offset-4 hover:underline font-bold dark:text-[#3FBF75]',
}

const buttonSizes = {
  default: 'h-10 px-4 py-2 text-xs md:text-sm font-bold rounded-xl',
  sm: 'h-8 px-3 text-xs font-bold rounded-lg',
  lg: 'h-12 px-6 text-base font-black rounded-2xl',
  icon: 'h-9 w-9 p-0 flex items-center justify-center rounded-xl',
}

export const Button = React.forwardRef(
  ({ className, variant = 'primary', size = 'default', disabled, children, ...props }, ref) => {
    return (
      <button
        ref={ref}
        disabled={disabled}
        className={cn(
          'inline-flex items-center justify-center gap-2 font-bold transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/30 disabled:opacity-50 disabled:pointer-events-none cursor-pointer',
          buttonVariants[variant] || buttonVariants.primary,
          buttonSizes[size] || buttonSizes.default,
          className
        )}
        {...props}
      >
        {children}
      </button>
    )
  }
)

Button.displayName = 'Button'

Button.propTypes = {
  className: PropTypes.string,
  variant: PropTypes.oneOf(['primary', 'default', 'secondary', 'destructive', 'outline', 'ghost', 'icon', 'link']),
  size: PropTypes.oneOf(['default', 'sm', 'lg', 'icon']),
  disabled: PropTypes.bool,
  children: PropTypes.node,
}
