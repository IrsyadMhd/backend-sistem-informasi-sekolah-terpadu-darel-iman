import React from 'react'
import PropTypes from 'prop-types'
import { cn } from '../../lib/utils'

export function Skeleton({ className, ...props }) {
  return (
    <div
      className={cn('animate-pulse rounded-xl bg-slate-200/80 dark:bg-slate-800', className)}
      {...props}
    />
  )
}

Skeleton.propTypes = {
  className: PropTypes.string,
}

export function SkeletonCard() {
  return (
    <div className="rounded-2xl border border-[#E5E7EB] bg-white p-5 space-y-3 dark:bg-slate-900 dark:border-slate-800">
      <Skeleton className="h-5 w-1/3" />
      <Skeleton className="h-10 w-full" />
      <Skeleton className="h-4 w-2/3" />
    </div>
  )
}
