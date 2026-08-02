// Plain POST form to Laravel's logout route — mirrors the mechanism used by
// resources/views/layouts/app.blade.php's sidebar footer (form POST + @csrf),
// since logout is a full page navigation/session action, not a client route.
export default function LogoutForm({ logoutUrl, csrfToken, style }) {
    return (
        <form method="POST" action={logoutUrl || '/logout'} style={style}>
            <input type="hidden" name="_token" value={csrfToken || ''} />
            <button type="submit" title="Sign out" style={{ background: 'none', border: 'none', cursor: 'pointer' }}>
                Logout
            </button>
        </form>
    );
}
