import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import DesktopGeneralSettingsPage from './GeneralSettingsPage';
import MobileGeneralSettingsPage from '../../mobile/settings/GeneralSettingsPage';
import { NAV_GROUPS, visibleGroups } from '../../../layouts/navItems';

describe('the Settings tab', () => {
    it('renders its heading on desktop', () => {
        render(<DesktopGeneralSettingsPage />);

        expect(screen.getByRole('heading', { name: 'Settings' })).toBeInTheDocument();
        expect(screen.getByText('Nothing here yet')).toBeInTheDocument();
    });

    it('renders on mobile too, since every page is a pair', () => {
        render(<MobileGeneralSettingsPage />);

        expect(screen.getByRole('heading', { name: 'Settings' })).toBeInTheDocument();
    });

    it('is in the System group, behind settings.view', () => {
        const entry = NAV_GROUPS
            .flatMap((group) => group.items)
            .find((item) => item.to === '/app/settings/general');

        expect(entry).toBeDefined();
        expect(entry.label).toBe('Settings');
        expect(entry.permission).toBe('settings.view');
        // Not parked: unlike Payments, this one is meant to be seen.
        expect(entry.hidden).toBeUndefined();
    });

    /** Grantable from day one, rather than bolted on once it has content. */
    it('is offered to someone holding settings.view and nobody else', () => {
        const shownTo = (permissions) => visibleGroups({ can: (p) => permissions.includes(p) })
            .flatMap((group) => group.items)
            .map((item) => item.to);

        expect(shownTo(['settings.view'])).toContain('/app/settings/general');
        expect(shownTo(['finance.view'])).not.toContain('/app/settings/general');
    });
});
