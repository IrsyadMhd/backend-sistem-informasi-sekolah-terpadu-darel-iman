import { create } from 'zustand'

function bacaUserTersimpan() {
  try {
    const raw = localStorage.getItem('school_erp_user')
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

export const useAuthStore = create((set) => ({
  token: localStorage.getItem('school_erp_token'),
  user: bacaUserTersimpan(),
  setSession: ({ token, user }) => {
    localStorage.setItem('school_erp_token', token)
    localStorage.setItem('school_erp_user', JSON.stringify(user || null))
    set({ token, user })
  },
  clearSession: () => {
    localStorage.removeItem('school_erp_token')
    localStorage.removeItem('school_erp_user')
    set({ token: null, user: null })
  },
}))
