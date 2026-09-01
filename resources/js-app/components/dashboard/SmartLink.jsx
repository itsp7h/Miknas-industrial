import { Link } from 'react-router-dom';

/**
 * `/app/...` paths belong to React Router, so they navigate client-side.
 * Everything else is still a Blade page and must be a real browser navigation
 * — same rule as navItems.js's link/href distinction.
 */
export default function SmartLink({ to, className, children, ...rest }) {
    if (to.startsWith('/app')) {
        return <Link to={to} className={className} {...rest}>{children}</Link>;
    }

    return <a href={to} className={className} {...rest}>{children}</a>;
}
