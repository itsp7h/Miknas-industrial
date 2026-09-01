// The exact stroke paths the Blade dashboard used, so the icons are identical.
const Svg = ({ className, children }) => (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
        {children}
    </svg>
);

const path = (d) => <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={d} />;

export const CurrencyIcon = ({ className }) => (
    <Svg className={className}>
        {path('M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z')}
    </Svg>
);

export const BoxIcon = ({ className }) => (
    <Svg className={className}>
        {path('M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4')}
    </Svg>
);

export const CogIcon = ({ className }) => (
    <Svg className={className}>
        {path('M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z')}
        {path('M15 12a3 3 0 11-6 0 3 3 0 016 0z')}
    </Svg>
);

export const ClockIcon = ({ className }) => (
    <Svg className={className}>{path('M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z')}</Svg>
);

export const ClipboardIcon = ({ className }) => (
    <Svg className={className}>
        {path('M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2')}
    </Svg>
);

export const PlusIcon = ({ className }) => (
    <Svg className={className}>{path('M12 4v16m8-8H4')}</Svg>
);

export const WarningIcon = ({ className }) => (
    <Svg className={className}>
        {path('M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z')}
    </Svg>
);

export const ChevronIcon = ({ className }) => (
    <Svg className={className}>{path('M9 5l7 7-7 7')}</Svg>
);
