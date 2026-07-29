import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.jsx'

// Terapkan tema tersimpan sebelum React dirender agar tidak terjadi kilatan tema
// terang ketika pengguna sebelumnya memilih night mode.
const savedTheme = localStorage.getItem('theme')
document.documentElement.classList.toggle('dark', savedTheme === 'dark')
document.body.classList.toggle('dark', savedTheme === 'dark')
document.documentElement.style.colorScheme = savedTheme === 'dark' ? 'dark' : 'light'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
