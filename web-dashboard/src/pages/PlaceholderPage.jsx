import PropTypes from 'prop-types'

export default function PlaceholderPage({ title }) {
  return (
    <section className="panel">
      <h3>{title}</h3>
      <p>Modul sedang disiapkan pada tahap berikutnya.</p>
    </section>
  )
}

PlaceholderPage.propTypes = {
  title: PropTypes.string.isRequired,
}
