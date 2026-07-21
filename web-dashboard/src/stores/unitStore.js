import { create } from 'zustand'

const unitOptions = ['TK', 'SD', 'SMP', 'SMA']

function bacaUnitTersimpan() {
  const unit = localStorage.getItem('school_erp_unit')
  return unitOptions.includes(unit) ? unit : 'SD'
}

export const useUnitStore = create((set) => ({
  activeUnit: bacaUnitTersimpan(),
  setActiveUnit: (activeUnit) => {
    localStorage.setItem('school_erp_unit', activeUnit)
    set({ activeUnit })
  },
}))

export const dashboardUnits = unitOptions