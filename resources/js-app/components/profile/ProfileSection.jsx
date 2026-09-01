/** Breeze's section: a card holding a heading, a description and a form. */
export default function ProfileSection({ title, description, children, maxWidth = 576 }) {
    return (
        <div style={{
            background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
            padding: '2rem', marginBottom: 24,
        }}>
            <div style={{ maxWidth }}>
                <h2 style={{ fontSize: 18, fontWeight: 500, color: '#111827' }}>{title}</h2>
                <p style={{ marginTop: 4, fontSize: 14, color: '#4b5563' }}>{description}</p>
                <div style={{ marginTop: 24 }}>{children}</div>
            </div>
        </div>
    );
}
