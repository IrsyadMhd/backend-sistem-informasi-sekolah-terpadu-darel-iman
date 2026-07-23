import { Outlet } from 'react-router-dom'

export default function StudentDataPage() {
  return (
    <section className="mt-3">
      <div className="min-w-0">
        <Outlet />
      </div>
    </section>
  )
}
