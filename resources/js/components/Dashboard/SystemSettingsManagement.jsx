import React, { useState, useEffect } from 'react';
import { 
    Terminal, 
    Shield, 
    ShieldAlert, 
    ShieldCheck, 
    Lock, 
    Unlock, 
    AlertTriangle, 
    CheckCircle2, 
    RefreshCw, 
    Sliders,
    Eye,
    EyeOff,
    Info,
    Check
} from 'lucide-react';
import { systemSettingService } from '../../services/api';
import { isDeveloperModeActive, setDeveloperModeActive } from '../../utils/devToolsGuard';

export const SystemSettingsManagement = () => {
    const [devMode, setDevMode] = useState(() => isDeveloperModeActive());
    const [loading, setLoading] = useState(true);
    const [toggling, setToggling] = useState(false);
    const [notification, setNotification] = useState(null);

    const fetchSetting = async () => {
        setLoading(true);
        try {
            const res = await systemSettingService.getDeveloperMode();
            if (res.data && typeof res.data.developer_mode !== 'undefined') {
                const val = Boolean(res.data.developer_mode);
                setDevMode(val);
                setDeveloperModeActive(val);
            }
        } catch (e) {
            console.error('Failed to fetch developer mode setting', e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchSetting();

        const handleDevModeChanged = (e) => {
            if (e.detail && typeof e.detail.developer_mode !== 'undefined') {
                setDevMode(Boolean(e.detail.developer_mode));
            }
        };

        window.addEventListener('developer_mode:changed', handleDevModeChanged);
        return () => window.removeEventListener('developer_mode:changed', handleDevModeChanged);
    }, []);

    const handleToggle = async () => {
        const nextState = !devMode;
        setToggling(true);
        setNotification(null);

        try {
            const res = await systemSettingService.updateDeveloperMode(nextState);
            const updated = Boolean(res.data.developer_mode);
            setDevMode(updated);
            setDeveloperModeActive(updated);
            setNotification({
                type: 'success',
                message: res.data.message || `Developer Mode is now ${updated ? 'ENABLED' : 'DISABLED'}.`
            });
        } catch (e) {
            const err = e.response?.data?.message || 'Failed to update Developer Mode. Only Super Administrators are authorized.';
            setNotification({
                type: 'error',
                message: err
            });
        } finally {
            setToggling(false);
        }
    };

    return (
        <div className="space-y-6">
            {/* Header Card */}
            <div className="bg-white rounded-2xl border border-slate-200 shadow-xs p-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div className="inline-flex items-center gap-2 px-3 py-1 bg-indigo-50 border border-indigo-200 rounded-lg text-indigo-700 text-xs font-bold uppercase tracking-wider mb-2">
                            <Sliders className="w-3.5 h-3.5" />
                            <span>System Security Governance</span>
                        </div>
                        <h2 className="text-lg font-black text-slate-900 tracking-tight">
                            System Security &amp; Developer Mode Control
                        </h2>
                        <p className="text-xs text-slate-500 mt-1 max-w-2xl">
                            Configure platform-wide developer inspection privileges, F12 console access, and client-side inspection restrictions.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={fetchSetting}
                        disabled={loading || toggling}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold transition self-start sm:self-auto cursor-pointer disabled:opacity-50"
                        title="Refresh Setting"
                    >
                        <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
                        <span>Refresh</span>
                    </button>
                </div>
            </div>

            {/* Notification Banner */}
            {notification && (
                <div className={`p-4 rounded-xl border text-xs flex items-start gap-3 animate-fade-in ${
                    notification.type === 'success' 
                        ? 'bg-emerald-50 border-emerald-200 text-emerald-800' 
                        : 'bg-rose-50 border-rose-200 text-rose-800'
                }`}>
                    {notification.type === 'success' ? (
                        <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600 mt-0.5" />
                    ) : (
                        <AlertTriangle className="w-4 h-4 shrink-0 text-rose-600 mt-0.5" />
                    )}
                    <span className="font-semibold leading-relaxed">{notification.message}</span>
                </div>
            )}

            {/* Developer Mode Main Toggle Card */}
            <div className={`bg-white rounded-2xl border-2 transition-all duration-300 shadow-sm overflow-hidden ${
                devMode 
                    ? 'border-amber-400 bg-gradient-to-br from-white via-amber-50/20 to-amber-100/30' 
                    : 'border-slate-200'
            }`}>
                <div className="p-6">
                    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                        <div className="flex items-start gap-4">
                            <div className={`w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 shadow-md ${
                                devMode 
                                    ? 'bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-amber-500/20' 
                                    : 'bg-gradient-to-br from-slate-900 to-slate-800 text-white shadow-slate-900/20'
                            }`}>
                                {devMode ? <Unlock className="w-6 h-6" /> : <Lock className="w-6 h-6" />}
                            </div>

                            <div className="space-y-1">
                                <div className="flex items-center gap-2.5 flex-wrap">
                                    <h3 className="text-base font-extrabold text-slate-900">
                                        Developer Mode / F12 Inspection Access
                                    </h3>
                                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider ${
                                        devMode 
                                            ? 'bg-amber-100 text-amber-900 border border-amber-300' 
                                            : 'bg-emerald-100 text-emerald-900 border border-emerald-300'
                                    }`}>
                                        <span className={`w-2 h-2 rounded-full ${devMode ? 'bg-amber-500 animate-ping' : 'bg-emerald-600'}`} />
                                        {devMode ? 'Active / Unrestricted' : 'Strict / Protected (OFF)'}
                                    </span>
                                </div>

                                <p className="text-xs text-slate-600 leading-relaxed max-w-2xl">
                                    {devMode ? (
                                        <>
                                            <strong className="text-amber-900">Developer Mode is ON:</strong> Users and administrators can open browser Developer Tools (F12), right-click Inspect, and view developer console logs freely for maintenance, debugging, and system audits.
                                        </>
                                    ) : (
                                        <>
                                            <strong className="text-slate-900">Developer Mode is OFF (Strict Authorized Mode):</strong> Pressing F12, right-clicking Inspect, or opening browser developer tools is strictly prohibited. Any unauthorized inspection attempt immediately logs the client out and redirects back to the login screen with an unauthorized violation notice.
                                        </>
                                    )}
                                </p>
                            </div>
                        </div>

                        {/* Interactive Toggle Control */}
                        <div className="flex items-center gap-4 lg:self-center bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <div className="text-right">
                                <div className="text-xs font-bold text-slate-800 uppercase">
                                    {devMode ? 'Disable Dev Mode' : 'Enable Dev Mode'}
                                </div>
                                <div className="text-[11px] text-slate-500 font-medium">
                                    {devMode ? 'Switch to Strict Mode' : 'Allow F12 & Inspect'}
                                </div>
                            </div>

                            <button
                                type="button"
                                onClick={handleToggle}
                                disabled={loading || toggling}
                                className={`relative inline-flex h-8 w-16 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 disabled:opacity-50 ${
                                    devMode ? 'bg-amber-500' : 'bg-slate-300'
                                }`}
                                role="switch"
                                aria-checked={devMode}
                                title={devMode ? 'Click to Disable Developer Mode' : 'Click to Enable Developer Mode'}
                            >
                                <span className="sr-only">Toggle Developer Mode</span>
                                <span
                                    aria-hidden="true"
                                    className={`pointer-events-none inline-block h-7 w-7 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out flex items-center justify-center ${
                                        devMode ? 'translate-x-8' : 'translate-x-0'
                                    }`}
                                >
                                    {toggling ? (
                                        <RefreshCw className="w-3.5 h-3.5 text-slate-500 animate-spin" />
                                    ) : devMode ? (
                                        <Unlock className="w-3.5 h-3.5 text-amber-600" />
                                    ) : (
                                        <Lock className="w-3.5 h-3.5 text-slate-500" />
                                    )}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                {/* Additional Guidance Footer */}
                <div className={`px-6 py-3 border-t text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2 ${
                    devMode ? 'bg-amber-50/70 border-amber-200 text-amber-900' : 'bg-slate-50 border-slate-100 text-slate-600'
                }`}>
                    <div className="flex items-center gap-2">
                        <Info className="w-4 h-4 shrink-0" />
                        <span>
                            {devMode 
                                ? 'Developers and Super Admins may inspect elements and diagnose client network calls.' 
                                : 'All user roles (End-User, Head, Reviewers, Admins) are restricted from inspecting source or DevTools.'}
                        </span>
                    </div>

                    <div className="font-mono text-[11px] font-semibold text-slate-500">
                        Audit Log: DEVELOPER_MODE_TOGGLED
                    </div>
                </div>
            </div>

            {/* Feature Behavior Breakdown */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="bg-white rounded-xl border border-slate-200 p-5 space-y-2 shadow-xs">
                    <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-900">
                        <Terminal className="w-4 h-4 text-blue-600" />
                        <span>F12 &amp; DevTools Inspection Rules</span>
                    </div>
                    <ul className="text-xs text-slate-600 space-y-1.5 list-disc list-inside">
                        <li><strong>F12 key:</strong> Blocked when OFF, launches DevTools when ON.</li>
                        <li><strong>Ctrl+Shift+I / J / C:</strong> Blocked when OFF, opens DevTools when ON.</li>
                        <li><strong>Right-Click Inspect:</strong> Context menu blocked when OFF, available when ON.</li>
                        <li><strong>Docked DevTools:</strong> Auto-detected and terminated when OFF.</li>
                    </ul>
                </div>

                <div className="bg-white rounded-xl border border-slate-200 p-5 space-y-2 shadow-xs">
                    <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-900">
                        <ShieldAlert className="w-4 h-4 text-indigo-600" />
                        <span>Security Enforcement Action</span>
                    </div>
                    <ul className="text-xs text-slate-600 space-y-1.5 list-disc list-inside">
                        <li><strong>Session Invalidation:</strong> User session is immediately revoked via API logout.</li>
                        <li><strong>Login Redirection:</strong> Client is redirected back to the login screen.</li>
                        <li><strong>Unauthorized Notice:</strong> An explicit security violation banner is displayed.</li>
                        <li><strong>Audit Trail:</strong> Setting changes are permanently logged with actor details.</li>
                    </ul>
                </div>
            </div>
        </div>
    );
};

export default SystemSettingsManagement;
