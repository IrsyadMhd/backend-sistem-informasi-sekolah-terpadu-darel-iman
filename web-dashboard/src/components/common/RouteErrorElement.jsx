import React from 'react'
import { useRouteError, useNavigate } from 'react-router-dom'
import { FaExclamationTriangle, FaRedo, FaHome } from 'react-icons/fa'

export function RouteErrorElement() {
  const error = useRouteError()
  const navigate = useNavigate()

  console.error('Route Error caught by ErrorBoundary:', error)

  const errorMessage =
    error?.statusText ||
    error?.message ||
    (typeof error === 'string' ? error : 'Terjadi kesalahan tidak terduga pada halaman ini.')

  return (
    <div className="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
      <div className="max-w-md w-full bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl text-center space-y-6">
        <div className="w-16 h-16 bg-red-950/80 border border-red-800/60 rounded-full flex items-center justify-center mx-auto text-red-400">
          <FaExclamationTriangle className="h-8 w-8" />
        </div>

        <div>
          <h2 className="text-xl font-bold text-white mb-2">Terjadi Kesalahan Aplikasi</h2>
          <p className="text-xs text-slate-400 bg-slate-950 p-3 rounded-lg border border-slate-800 font-mono text-left overflow-x-auto max-h-32">
            {errorMessage}
          </p>
        </div>

        <div className="flex items-center justify-center space-x-3 pt-2">
          <button
            onClick={() => window.location.reload()}
            className="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors cursor-pointer"
          >
            <FaRedo className="mr-2 h-3.5 w-3.5" /> Muat Ulang Halaman
          </button>
          <button
            onClick={() => navigate('/dashboard')}
            className="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 transition-colors cursor-pointer"
          >
            <FaHome className="mr-2 h-3.5 w-3.5" /> Beranda
          </button>
        </div>
      </div>
    </div>
  )
}

export default RouteErrorElement
