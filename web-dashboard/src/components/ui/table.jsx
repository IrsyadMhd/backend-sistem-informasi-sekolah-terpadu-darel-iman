import * as React from 'react'
import PropTypes from 'prop-types'
import { cn } from '../../lib/utils'

export const Table = React.forwardRef(({ className, ...props }, ref) => (
  <div className="relative w-full overflow-x-auto rounded-[18px] border border-slate-200/80 bg-white shadow-sm dark:border-slate-800/80 dark:bg-[#1B2433]">
    <table ref={ref} className={cn('w-full caption-bottom text-sm border-collapse', className)} {...props} />
  </div>
))
Table.displayName = 'Table'

export const TableHeader = React.forwardRef(({ className, ...props }, ref) => (
  <thead ref={ref} className={cn('bg-slate-50/90 sticky top-0 z-10 backdrop-blur-md border-b border-slate-200/80 text-slate-700 font-extrabold dark:bg-slate-800/90 dark:border-slate-800 dark:text-slate-200', className)} {...props} />
))
TableHeader.displayName = 'TableHeader'

export const TableBody = React.forwardRef(({ className, ...props }, ref) => (
  <tbody ref={ref} className={cn('divide-y divide-slate-100 bg-white dark:divide-slate-800/60 dark:bg-[#1B2433]', className)} {...props} />
))
TableBody.displayName = 'TableBody'

export const TableFooter = React.forwardRef(({ className, ...props }, ref) => (
  <tfoot ref={ref} className={cn('border-t border-slate-200/80 bg-slate-50/80 font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300', className)} {...props} />
))
TableFooter.displayName = 'TableFooter'

export const TableRow = React.forwardRef(({ className, ...props }, ref) => (
  <tr
    ref={ref}
    className={cn(
      'transition-colors hover:bg-[#0E5C44]/5 data-[state=selected]:bg-[#0E5C44]/10 dark:hover:bg-[#3FBF75]/10 dark:data-[state=selected]:bg-[#3FBF75]/20',
      className
    )}
    {...props}
  />
))
TableRow.displayName = 'TableRow'

export const TableHead = React.forwardRef(({ className, ...props }, ref) => (
  <th
    ref={ref}
    className={cn(
      'h-11 px-5 text-left align-middle font-semibold text-slate-500 uppercase tracking-wider text-[11px] [&:has([role=checkbox])]:pr-0 dark:text-slate-400',
      className
    )}
    {...props}
  />
))
TableHead.displayName = 'TableHead'

export const TableCell = React.forwardRef(({ className, ...props }, ref) => (
  <td ref={ref} className={cn('px-5 py-4 align-middle text-slate-700 font-normal [&:has([role=checkbox])]:pr-0 dark:text-slate-200', className)} {...props} />
))
TableCell.displayName = 'TableCell'

export const TableCaption = React.forwardRef(({ className, ...props }, ref) => (
  <caption ref={ref} className={cn('mt-4 text-xs text-slate-400 dark:text-slate-500', className)} {...props} />
))
TableCaption.displayName = 'TableCaption'

Table.propTypes = { className: PropTypes.string }
TableHeader.propTypes = { className: PropTypes.string }
TableBody.propTypes = { className: PropTypes.string }
TableFooter.propTypes = { className: PropTypes.string }
TableRow.propTypes = { className: PropTypes.string }
TableHead.propTypes = { className: PropTypes.string }
TableCell.propTypes = { className: PropTypes.string }
TableCaption.propTypes = { className: PropTypes.string }
