import SmartLink from '../../components/dashboard/SmartLink';
import { ChevronIcon } from '../../components/dashboard/icons';
import { KPIS, QUICK_ACTIONS, MODULES, formatKpi } from '../../components/dashboard/data';
import useDashboardSummary from '../../components/dashboard/useDashboardSummary';

// Same palette and figures as desktop; the layout is what changes. KPIs go
// two-up so all five stay visible without a long scroll, and each card drops
// the caption line to keep the tile compact. Labels wrap rather than truncate:
// at this width "Inventory Value" would clip to "INVENTORY VA…".
const CARD = 'bg-white rounded-2xl p-4 shadow-sm border border-slate-200 flex items-start gap-3';

function KpiBody({ kpi, summary }) {
    const { Icon } = kpi;

    return (
        <>
            <div className={`w-9 h-9 rounded-lg ${kpi.iconWrap} flex items-center justify-center flex-shrink-0`}>
                <Icon className={`w-4 h-4 ${kpi.iconColor}`} />
            </div>
            <div className="min-w-0">
                <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider leading-tight">{kpi.label}</p>
                <p className={`text-xl font-bold ${kpi.valueColor ?? 'text-slate-800'} mt-0.5 truncate`}>
                    {summary ? formatKpi(summary[kpi.key]) : '—'}
                </p>
            </div>
        </>
    );
}

export default function DashboardPage({ currentUserId, userName }) {
    const summary = useDashboardSummary(currentUserId);

    return (
        <div>
            <div className="mb-4">
                <h1 className="text-xl font-bold text-slate-800">Dashboard</h1>
                <p className="text-slate-500 text-sm mt-0.5">
                    Welcome back, {userName ?? 'there'}.
                </p>
            </div>

            <div className="grid grid-cols-2 gap-3 mb-6">
                {KPIS.map((kpi) => (kpi.to ? (
                    <SmartLink key={kpi.key} to={kpi.to} className={CARD}>
                        <KpiBody kpi={kpi} summary={summary} />
                    </SmartLink>
                ) : (
                    <div key={kpi.key} className={CARD}>
                        <KpiBody kpi={kpi} summary={summary} />
                    </div>
                )))}
            </div>

            <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">Quick Actions</h2>
            <div className="grid grid-cols-1 gap-2 mb-6">
                {QUICK_ACTIONS.map((action) => (
                    <SmartLink
                        key={action.label}
                        to={action.to}
                        className="bg-white rounded-xl p-3 border border-slate-200 flex items-center gap-3"
                    >
                        <div className={`w-10 h-10 rounded-lg ${action.iconWrap} flex items-center justify-center flex-shrink-0`}>
                            <action.Icon className={`w-5 h-5 ${action.iconColor}`} />
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-semibold text-slate-700">{action.label}</p>
                            <p className="text-xs text-slate-400 mt-0.5">{action.caption}</p>
                        </div>
                    </SmartLink>
                ))}
            </div>

            <div className="grid grid-cols-1 gap-3">
                {MODULES.map((module) => (
                    <div key={module.title} className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className={`${module.header} px-4 py-3`}>
                            <h3 className="text-white font-semibold text-sm">{module.title}</h3>
                            <p className={`${module.captionColor} text-xs mt-0.5`}>{module.caption}</p>
                        </div>
                        <div className="p-3">
                            {module.links.map((link) => (
                                <SmartLink
                                    key={link.label}
                                    to={link.to}
                                    className="flex items-center justify-between py-2.5 text-sm text-slate-600 border-b border-slate-100 last:border-0"
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
