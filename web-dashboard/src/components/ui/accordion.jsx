import React, { useState } from 'react'
import PropTypes from 'prop-types'
import { FaChevronDown } from 'react-icons/fa'
import { cn } from '../../lib/utils'

export function AccordionItem({ title, subtitle, icon, children, defaultOpen = false }) {
  const [isOpen, setIsOpen] = useState(defaultOpen)

  return (
    <div className="rounded-2xl border border-[#E5E7EB] bg-white overflow-hidden transition-all duration-200 dark:bg-slate-900 dark:border-slate-800">
      <button
        type="button"
        onClick={() => setIsOpen((prev) => !prev)}
        className="flex w-full items-center justify-between p-4 text-left hover:bg-slate-50 transition cursor-pointer dark:hover:bg-slate-800/50"
      >
        <div className="flex items-center gap-3">
          {icon && <div className="text-emerald-700 text-lg dark:text-emerald-400">{icon}</div>}
          <div>
            <h4 className="text-xs md:text-sm font-bold text-slate-800 dark:text-white">{title}</h4>
            {subtitle && <p className="text-[11px] text-slate-400 mt-0.5">{subtitle}</p>}
          </div>
        </div>
        <FaChevronDown
          className={cn(
            'text-xs text-slate-400 transition-transform duration-200',
            isOpen && 'rotate-180 text-emerald-600'
          )}
        />
      </button>

      {isOpen && (
        <div className="border-t border-slate-100 p-4 text-xs text-slate-600 dark:border-slate-800 dark:text-slate-300 animate-in fade-in duration-200">
          {children}
        </div>
      )}
    </div>
  )
}

AccordionItem.propTypes = {
  title: PropTypes.node.isRequired,
  subtitle: PropTypes.node,
  icon: PropTypes.node,
  children: PropTypes.node,
  defaultOpen: PropTypes.bool,
}

export function Accordion({ children, className }) {
  return <div className={cn('space-y-3', className)}>{children}</div>
}

Accordion.propTypes = {
  children: PropTypes.node,
  className: PropTypes.string,
}
