import SmartLink from '../../components/dashboard/SmartLink';
import { ChevronIcon } from '../../components/dashboard/icons';
import { KPIS, QUICK_ACTIONS, MODULES, formatKpi } from '../../components/dashboard/data';
import useDashboardSummary from '../../components/dashboard/useDashboardSummary';

const CARD = 'bg-white rounded-2xl p-5 shadow-sm border border-slate-200 flex items-start gap-4';

function KpiBody({ kpi, summary }) {
    const { Icon } = kpi;

    return (
        <>
            <div className={`w-11 h-11 rounded-xl ${kpi.iconWrap} flex items-center justify-center flex-shrink-0`}>
                <Icon className={`w-5 h-5 ${kpi.iconColor}`} />
            </div>
            <div className="min-w-0">
                <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">{kpi.label}</p>
                <p className={`text-2xl font-bold ${kpi.valueColor ?? 'text-slate-800'} mt-1 truncate`}>
                    {summary ? formatKpi(summary[kpi.key]) : '—'}
                </p>
                <p className="text-xs text-slate-400 mt-0.5">{kpi.caption}</p>
            </div>
        </>
    );
}

export default function DashboardPage({ currentUserId, userName }) {
    const summary = useDashboardSummary(currentUserId);

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-slate-800">Dashboard</h1>
                <p className="text-slate-500 text-sm mt-1">
                    Welcome back, {userName ?? 'there'}. Here's what's happening today.
                </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-8">
                {KPIS.map((kpi) => (kpi.to ? (
                    <SmartLink key={kpi.key} to={kpi.to} className={`${CARD} ${kpi.hover}`}>
                        <KpiBody kpi={kpi} summary={summary} />
                    </SmartLink>
                ) : (
                    <div key={kpi.key} className={CARD}>
                        <KpiBody kpi={kpi} summary={summary} />
                    </div>
                )))}
            </div>

            <div className="mb-6">
                <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Quick Actions</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    {QUICK_ACTIONS.map((action) => (
                        <SmartLink
                            key={action.label}
                            to={action.to}
                            className={`group bg-white rounded-xl p-4 border border-slate-200 ${action.border} hover:shadow-md transition-all duration-200 flex items-center gap-4`}
                        >
                            <div className={`w-10 h-10 rounded-lg ${action.iconWrap} flex items-center justify-center flex-shrink-0 transition-colors`}>
                                <action.Icon className={`w-5 h-5 ${action.iconColor}`} />
                            </div>
                            <div>
                                <p className={`text-sm font-semibold text-slate-700 ${action.labelHover}`}>{action.label}</p>
                                <p className="text-xs text-slate-400 mt-0.5">{action.caption}</p>
                            </div>
                        </SmartLink>
                    ))}
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {MODULES.map((module) => (
                    <div key={module.title} className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className={`${module.header} px-5 py-4`}>
                            <h3 className="text-white font-semibold text-sm">{module.title}</h3>
                            <p className={`${module.captionColor} text-xs mt-0.5`}>{module.caption}</p>
                        </div>
                        <div className="p-4 space-y-1">
                            {module.links.map((link) => (
                                <SmartLink
                                    key={link.label}
                                    to={link.to}
                                    className={`flex items-center justify-between py-1.5 text-sm text-slate-600 ${module.linkHover} transition-colors`}
                                >
                                    <span>{link.label}</span>
                                    <ChevronIcon className="w-3.5 h-3.5 text-slate-300" />
                                </SmartLink>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
