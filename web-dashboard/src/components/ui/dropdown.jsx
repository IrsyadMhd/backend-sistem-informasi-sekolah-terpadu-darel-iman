import React, { useState, useRef, useEffect } from 'react'
import PropTypes from 'prop-types'
import { cn } from '../../lib/utils'

export function Dropdown({ trigger, items, align = 'right', className }) {
  const [isOpen, setIsOpen] = useState(false)
  const dropdownRef = useRef(null)

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setIsOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  const alignClasses = {
    right: 'right-0',
    left: 'left-0',
    center: 'left-1/2 -translate-x-1/2',
  }

  return (
    <div className="relative inline-block text-left" ref={dropdownRef}>
      <div onClick={() => setIsOpen((prev) => !prev)}>{trigger}</div>

      {isOpen && (
        <div
          className={cn(
            'absolute top-full mt-2 z-50 min-w-[200px] rounded-2xl bg-white p-1.5 shadow-xl border border-[#E5E7EB] animate-in fade-in zoom-in-95 duration-150 dark:bg-slate-900 dark:border-slate-800',
            alignClasses[align] || alignClasses.right,
            className
          )}
        >
          {items.map((item, idx) => {
            if (item.divider) {
              return <div key={idx} className="my-1 border-t border-slate-100 dark:border-slate-800" />
            }
            return (
              <button
                key={idx}
                type="button"
                onClick={() => {
                  if (item.onClick) item.onClick()
                  setIsOpen(false)
                }}
                disabled={item.disabled}
                className={cn(
                  'flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-medium transition-colors text-left',
                  item.danger
                    ? 'text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40'
                    : 'text-slate-700 hover:bg-emerald-50 hover:text-emerald-800 dark:text-slate-200 dark:hover:bg-slate-800',
                  item.disabled && 'opacity-50 pointer-events-none'
                )}
              >
                {item.icon && <span className="text-sm">{item.icon}</span>}
                <span>{item.label}</span>
              </button>
            )
          })}
        </div>
      )}
    </div>
  )
}

Dropdown.propTypes = {
  trigger: PropTypes.node.isRequired,
  items: PropTypes.arrayOf(
    PropTypes.shape({
      label: PropTypes.node,
      icon: PropTypes.node,
      onClick: PropTypes.func,
      danger: PropTypes.bool,
      disabled: PropTypes.bool,
      divider: PropTypes.bool,
    })
  ).isRequired,
  align: PropTypes.oneOf(['right', 'left', 'center']),
  className: PropTypes.string,
}
