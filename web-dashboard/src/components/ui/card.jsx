import * as React from 'react'
import PropTypes from 'prop-types'
import { cn } from '../../lib/utils'

export const Card = React.forwardRef(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn(
      'rounded-[18px] border border-slate-200/80 bg-white text-slate-900 shadow-sm transition-all duration-250 hover:shadow-lg hover:border-[#3FBF75]/30 dark:border-slate-800/80 dark:bg-[#1B2433] dark:text-slate-100',
      className
    )}
    {...props}
  />
))
Card.displayName = 'Card'

export const CardHeader = React.forwardRef(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn('flex flex-col space-y-1.5 p-6 border-b border-slate-100 dark:border-slate-800/80', className)}
    {...props}
  />
))
CardHeader.displayName = 'CardHeader'

export const CardTitle = React.forwardRef(({ className, ...props }, ref) => (
  <h3
    ref={ref}
    className={cn('text-lg font-bold leading-none tracking-tight text-slate-900 dark:text-white', className)}
    {...props}
  />
))
CardTitle.displayName = 'CardTitle'

export const CardDescription = React.forwardRef(({ className, ...props }, ref) => (
  <p
    ref={ref}
    className={cn('text-xs md:text-sm text-slate-500 leading-relaxed dark:text-slate-400', className)}
    {...props}
  />
))
CardDescription.displayName = 'CardDescription'

export const CardContent = React.forwardRef(({ className, ...props }, ref) => (
  <div ref={ref} className={cn('p-6', className)} {...props} />
))
CardContent.displayName = 'CardContent'

export const CardFooter = React.forwardRef(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn('flex items-center justify-between p-6 pt-0 border-t border-slate-100 dark:border-slate-800/80 mt-2', className)}
    {...props}
  />
))
CardFooter.displayName = 'CardFooter'

Card.propTypes = { className: PropTypes.string }
CardHeader.propTypes = { className: PropTypes.string }
CardTitle.propTypes = { className: PropTypes.string }
CardDescription.propTypes = { className: PropTypes.string }
CardContent.propTypes = { className: PropTypes.string }
CardFooter.propTypes = { className: PropTypes.string }
