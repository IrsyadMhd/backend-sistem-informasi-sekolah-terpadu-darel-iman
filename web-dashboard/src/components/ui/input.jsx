import * as React from 'react'
import PropTypes from 'prop-types'
import { cn } from '../../lib/utils'

export const Input = React.forwardRef(({ className, type = 'text', ...props }, ref) => {
  return (
    <input
      type={type}
      ref={ref}
      className={cn(
        'flex h-10 w-full rounded-xl border border-slate-200/80 bg-white px-3.5 py-2 text-sm font-medium text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0E5C44]/30 focus:border-[#0E5C44] disabled:cursor-not-allowed disabled:opacity-50 transition-all duration-200 dark:border-slate-800 dark:bg-[#111827] dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-[#3FBF75]/30 dark:focus:border-[#3FBF75]',
        className
      )}
      {...props}
    />
  )
})

Input.displayName = 'Input'

Input.propTypes = {
  className: PropTypes.string,
  type: PropTypes.string,
}
