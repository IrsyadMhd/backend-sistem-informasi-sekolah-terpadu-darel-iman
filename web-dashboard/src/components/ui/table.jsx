import * as React from 'react'
import PropTypes from 'prop-types'
import { cn } from '../../lib/utils'

export const Table = React.forwardRef(({ className, ...props }, ref) => (
  <div className="relative w-full overflow-x-auto rounded-lg border border-slate-800">
    <table ref={ref} className={cn('w-full caption-bottom text-sm', className)} {...props} />
  </div>
))
Table.displayName = 'Table'

export const TableHeader = React.forwardRef(({ className, ...props }, ref) => (
  <thead ref={ref} className={cn('bg-slate-950/80 border-b border-slate-800 text-slate-300 font-semibold', className)} {...props} />
))
TableHeader.displayName = 'TableHeader'

export const TableBody = React.forwardRef(({ className, ...props }, ref) => (
  <tbody ref={ref} className={cn('divide-y divide-slate-800/60 bg-slate-900/40', className)} {...props} />
))
TableBody.displayName = 'TableBody'

export const TableFooter = React.forwardRef(({ className, ...props }, ref) => (
  <tfoot ref={ref} className={cn('border-t border-slate-800 bg-slate-950/50 font-medium text-slate-300', className)} {...props} />
))
TableFooter.displayName = 'TableFooter'

export const TableRow = React.forwardRef(({ className, ...props }, ref) => (
  <tr
    ref={ref}
    className={cn(
      'transition-colors hover:bg-slate-800/50 data-[state=selected]:bg-slate-800',
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
      'h-12 px-4 text-left align-middle font-medium text-slate-300 [&:has([role=checkbox])]:pr-0 uppercase tracking-wider text-xs',
      className
    )}
    {...props}
  />
))
TableHead.displayName = 'TableHead'

export const TableCell = React.forwardRef(({ className, ...props }, ref) => (
  <td ref={ref} className={cn('p-4 align-middle text-slate-200 [&:has([role=checkbox])]:pr-0', className)} {...props} />
))
TableCell.displayName = 'TableCell'

export const TableCaption = React.forwardRef(({ className, ...props }, ref) => (
  <caption ref={ref} className={cn('mt-4 text-sm text-slate-400', className)} {...props} />
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
