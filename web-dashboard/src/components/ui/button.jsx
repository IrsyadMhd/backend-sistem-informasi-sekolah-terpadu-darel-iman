import * as React from 'react'
import PropTypes from 'prop-types'
import { cn } from '../../lib/utils'

const buttonVariants = {
  default: 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm',
  destructive: 'bg-red-600 text-white hover:bg-red-700 shadow-sm',
  outline: 'border border-slate-700 bg-slate-800/60 text-slate-200 hover:bg-slate-700 hover:text-white',
  secondary: 'bg-slate-700 text-slate-100 hover:bg-slate-600',
  ghost: 'text-slate-300 hover:bg-slate-800 hover:text-white',
  link: 'text-emerald-400 underline-offset-4 hover:underline',
}

const buttonSizes = {
  default: 'h-10 px-4 py-2 text-sm',
  sm: 'h-8 px-3 text-xs rounded-md',
  lg: 'h-12 px-6 text-base rounded-lg',
  icon: 'h-9 w-9 p-0 flex items-center justify-center rounded-md',
}

export const Button = React.forwardRef(
  ({ className, variant = 'default', size = 'default', disabled, ...props }, ref) => {
    return (
      <button
        ref={ref}
        disabled={disabled}
        className={cn(
          'inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500/50 disabled:opacity-50 disabled:pointer-events-none cursor-pointer',
          buttonVariants[variant] || buttonVariants.default,
          buttonSizes[size] || buttonSizes.default,
          className
        )}
        {...props}
      />
    )
  }
)

Button.displayName = 'Button'

Button.propTypes = {
  className: PropTypes.string,
  variant: PropTypes.oneOf(['default', 'destructive', 'outline', 'secondary', 'ghost', 'link']),
  size: PropTypes.oneOf(['default', 'sm', 'lg', 'icon']),
  disabled: PropTypes.bool,
}
