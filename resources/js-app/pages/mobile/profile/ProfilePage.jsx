import DesktopProfilePage from '../../desktop/profile/ProfilePage';
import { Hero, MobilePage } from '../../../components/mobile/ui';

/**
 * Three stacked cards with single-column forms is already the phone layout; the
 * only differences are the widths and a full-width delete button, which
 * `compact` handles. Two copies would only drift. The phone adds the mobile
 * design's header, and `compact` leaves the desktop's own title out.
 */
export default function ProfilePage() {
    return (
        <MobilePage>
            <Hero zone="home" back={{ to: '/app/more', label: 'More' }} title="Profile" subtitle="Your account, password and signature" />
            <div><DesktopProfilePage compact /></div>
        </MobilePage>
    );
}
