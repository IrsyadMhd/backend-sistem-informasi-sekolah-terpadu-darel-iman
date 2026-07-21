import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  build: {
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) {
            return undefined
          }

          if (id.includes('recharts')) {
            return 'vendor-chart'
          }

          if (id.includes('@tanstack') || id.includes('axios')) {
            return 'vendor-query'
          }

          if (id.includes('react-hook-form') || id.includes('sweetalert2') || id.includes('zustand')) {
            return 'vendor-form'
          }

          if (id.includes('react-router-dom') || id.includes('react-dom') || id.includes('/react/')) {
            return 'vendor-react'
          }

          return 'vendor-misc'
        },
      },
    },
  },
})
