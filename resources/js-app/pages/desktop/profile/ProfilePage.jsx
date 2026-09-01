import DeleteAccountModal from '../../../components/profile/DeleteAccountModal';
import PasswordForm from '../../../components/profile/PasswordForm';
import ProfileDetailsForm from '../../../components/profile/ProfileDetailsForm';
import ProfileSection from '../../../components/profile/ProfileSection';
import useProfile from '../../../components/profile/useProfile';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';

export default function ProfilePage({ compact = false }) {
    const p = useProfile();
    const maxWidth = compact ? '100%' : 576;

    useSetPageTitle('Profile');

    return (
        <div style={{ maxWidth: compact ? '100%' : 896 }}>
            <div className="mb-5">
                <h1 className="page-title">Profile</h1>
                <p className="page-subtitle">Your account details, password and account removal.</p>
            </div>

            {p.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}

            {!p.loading && p.user && (
                <>
                    <ProfileSection
                        title="Profile Information"
                        description="Update your account's profile information and email address."
                        maxWidth={maxWidth}
                    >
                        <ProfileDetailsForm
                            user={p.user}
                            onSave={p.saveDetails}
                            onResendVerification={p.resendVerification}
                        />
                    </ProfileSection>

                    <ProfileSection
                        title="Update Password"
                        description="Ensure your account is using a long, random password to stay secure."
                        maxWidth={maxWidth}
                    >
                        <PasswordForm onSave={p.savePassword} />
                    </ProfileSection>

                    <ProfileSection
                        title="Delete Account"
                        description="Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain."
                        maxWidth={maxWidth}
                    >
                        <button
                            type="button" onClick={() => p.setDeleteOpen(true)} className="btn-danger"
                            style={compact ? { width: '100%', justifyContent: 'center' } : undefined}
                        >
                            Delete Account
                        </button>
                    </ProfileSection>
                </>
            )}

            <DeleteAccountModal
                open={p.deleteOpen}
                onClose={() => p.setDeleteOpen(false)}
                onConfirm={p.deleteAccount}
            />
        </div>
    );
}
