import { createContext, useContext } from 'react';

/**
 * Who is signed in, for the pages that show it. The mobile design moved the
 * user card and sign-out out of the chrome and onto pages (More, Home), so
 * the props the shell used to keep to itself are offered here instead.
 */
const ShellUserContext = createContext({
    currentUserId: null, userName: 'User', userEmail: '', userRole: '', logoutUrl: '/logout', csrfToken: '',
});

export const ShellUserProvider = ShellUserContext.Provider;

export function useShellUser() {
    return useContext(ShellUserContext);
}
