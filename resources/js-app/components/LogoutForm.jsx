// Plain POST form to Laravel's logout route — mirrors the mechanism used by
// resources/views/layouts/app.blade.php's sidebar footer (form POST + @csrf),
// since logout is a full page navigation/session action, not a client route.
//
// `children` lets a caller supply its own trigger content (the sidebar footer
// passes an icon, matching the Blade button); callers that pass nothing keep
// the plain "Logout" label.
export default function LogoutForm({ logoutUrl, csrfToken, style, children }) {
    return (
        <form method="POST" action={logoutUrl || '/logout'} style={style}>
            <input type="hidden" name="_token" value={csrfToken || ''} />
            <button
                type="submit"
                title="Sign out"
                aria-label="Sign out"
                style={{
                    background: 'none', border: 'none', cursor: 'pointer',
                    padding: children ? 4 : 0, display: 'flex', alignItems: 'center',
                }}
            >
                {children ?? 'Logout'}
            </button>
        </form>
    );
}
