import React, { useState } from 'react'
import PropTypes from 'prop-types'
import { cn } from '../../lib/utils'

export function Tooltip({ text, children, position = 'top' }) {
  const [visible, setVisible] = useState(false)

  const posClasses = {
    top: 'bottom-full mb-2 left-1/2 -translate-x-1/2',
    bottom: 'top-full mt-2 left-1/2 -translate-x-1/2',
    left: 'right-full mr-2 top-1/2 -translate-y-1/2',
    right: 'left-full ml-2 top-1/2 -translate-y-1/2',
  }

  return (
    <div
      className="relative inline-block"
      onMouseEnter={() => setVisible(true)}
      onMouseLeave={() => setVisible(false)}
    >
      {children}
      {visible && text && (
        <div
          className={cn(
            'absolute z-50 whitespace-nowrap rounded-lg bg-slate-900 px-2.5 py-1 text-[11px] font-medium text-white shadow-md pointer-events-none transition-all duration-150 dark:bg-emerald-950 dark:text-emerald-100 dark:border dark:border-emerald-800',
            posClasses[position] || posClasses.top
          )}
        >
          {text}
        </div>
      )}
    </div>
  )
}

Tooltip.propTypes = {
  text: PropTypes.node,
  children: PropTypes.node.isRequired,
  position: PropTypes.oneOf(['top', 'bottom', 'left', 'right']),
}
