import DesktopProfilePage from '../../desktop/profile/ProfilePage';

/**
 * Three stacked cards with single-column forms is already the phone layout; the
 * only differences are the widths and a full-width delete button, which
 * `compact` handles. Two copies would only drift.
 */
export default function ProfilePage() {
    return <DesktopProfilePage compact />;
}
