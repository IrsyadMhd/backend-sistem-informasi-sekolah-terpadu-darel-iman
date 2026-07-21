import PropTypes from 'prop-types'
import { FaBullseye, FaCheckCircle, FaGraduationCap, FaHeartbeat, FaMedal } from 'react-icons/fa'

const ikonStat = {
  'Total Siswa': FaGraduationCap,
  'Kehadiran Hari Ini': FaCheckCircle,
  'Target Tahfizh Sekolah': FaBullseye,
  'Prestasi Siswa': FaMedal,
  'Mutabaah Yaumiyah': FaHeartbeat,
}

export default function StatCard({ title, value, subtitle }) {
  const Ikon = ikonStat[title] || FaBullseye

  return (
    <section className="stat-card">
      <div className="stat-head">
        <span className="stat-icon-wrap"><Ikon /></span>
        <p className="stat-title">{title}</p>
      </div>
      <h3 className="stat-value">{value}</h3>
      <p className="stat-subtitle">{subtitle}</p>
    </section>
  )
}

StatCard.propTypes = {
  title: PropTypes.string.isRequired,
  value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
  subtitle: PropTypes.string,
}

StatCard.defaultProps = {
  subtitle: '',
}
