import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { authService, dashboardService, ppmpService } from './services/api';
import { MainLayout } from './components/Layout/MainLayout';
import { LoginPage } from './pages/Auth/LoginPage';
import { Dashboard } from './pages/Dashboard/Dashboard';
import { PPMPListPage } from './pages/PPMP/PPMPListPage';
import { PPMPDetailPage } from './pages/PPMP/PPMPDetailPage';
import { PPMPFormPage } from './pages/PPMP/PPMPFormPage';
import { PrivacyPolicyPage } from './pages/Privacy/PrivacyPolicyPage';

export function App() {
    const [user, setUser] = useState(() => window.App?.user || null);
    const [loading, setLoading] = useState(() => !window.App?.user && window.App !== undefined ? false : false);
    const [currentView, setCurrentView] = useState(() => {
        if (window.location.pathname === '/privacy') return 'privacy';
        return 'dashboard';
    }); // dashboard, ppmps, create, detail, edit, privacy
    const [selectedPpmpUuid, setSelectedPpmpUuid] = useState(null);
    const [currentPpmp, setCurrentPpmp] = useState(null);
    const [dashboardData, setDashboardData] = useState(null);

    // Check existing session if needed
    const checkAuth = async () => {
        // If server already told us user is not logged in, skip calling /api/me
        if (window.App && !window.App.user) {
            setUser(null);
            setLoading(false);
            return;
        }

        try {
            const res = await authService.getProfile();
            setUser(res.data.user);
        } catch (e) {
            setUser(null);
        } finally {
            setLoading(false);
        }
    };

    const loadDashboard = async () => {
        try {
            const res = await dashboardService.getDashboard();
            setDashboardData(res.data);
        } catch (e) {
            console.error(e);
        }
    };

    const loadPpmpDetail = async (uuid) => {
        try {
            const res = await ppmpService.getOne(uuid);
            const ppmpObj = res.data.ppmp;
            if (res.data.default_signatories) {
                ppmpObj.default_signatories = res.data.default_signatories;
            }
            setCurrentPpmp(ppmpObj);
            setSelectedPpmpUuid(uuid);
            setCurrentView('detail');
            return true;
        } catch (e) {
            alert(e.response?.data?.message || 'Failed to load PPMP details.');
            return false;
        }
    };

    useEffect(() => {
        checkAuth();

        const handleUnauthorized = () => {
            setUser(null);
            setCurrentView('dashboard');
        };
        window.addEventListener('auth:unauthorized', handleUnauthorized);

        const handlePopState = () => {
            if (window.location.pathname === '/privacy') {
                setCurrentView('privacy');
            } else {
                setCurrentView('dashboard');
            }
        };
        window.addEventListener('popstate', handlePopState);

        return () => {
            window.removeEventListener('auth:unauthorized', handleUnauthorized);
            window.removeEventListener('popstate', handlePopState);
        };
    }, []);

    useEffect(() => {
        if (user && currentView === 'dashboard') {
            loadDashboard();
        }
    }, [user, currentView]);

    const handleLogout = async () => {
        try {
            await authService.logout();
        } catch (e) {
            console.error(e);
        } finally {
            setUser(null);
            setCurrentPpmp(null);
            setSelectedPpmpUuid(null);
            setCurrentView('dashboard');
        }
    };

    if (loading) {
        return (
            <div className="min-h-screen bg-slate-900 flex flex-col items-center justify-center text-white">
                <div className="w-12 h-12 rounded-full border-4 border-blue-500 border-t-transparent animate-spin mb-4" />
                <div className="text-sm font-semibold tracking-wider uppercase">Loading Provincial System...</div>
            </div>
        );
    }

    if (currentView === 'privacy') {
        return (
            <PrivacyPolicyPage
                onBack={() => {
                    if (window.location.pathname === '/privacy') {
                        window.history.pushState({}, '', '/');
                    }
                    setCurrentView('dashboard');
                }}
            />
        );
    }

    if (!user) {
        return (
            <LoginPage
                onLoginSuccess={(loggedInUser) => {
                    setUser(loggedInUser);
                    setCurrentView('dashboard');
                }}
                onOpenPrivacy={() => {
                    window.history.pushState({}, '', '/privacy');
                    setCurrentView('privacy');
                }}
            />
        );
    }

    return (
        <MainLayout
            user={user}
            activeTab={currentView}
            onNavigate={(view) => {
                setCurrentView(view);
                if (view === 'dashboard') loadDashboard();
            }}
            onSelectPpmp={(uuid) => loadPpmpDetail(uuid)}
            onLogout={handleLogout}
            onUserUpdate={(updatedUser) => setUser(updatedUser)}
        >
            {currentView === 'dashboard' && (
                <Dashboard
                    user={user}
                    data={dashboardData}
                    onSelectPpmp={(uuid) => loadPpmpDetail(uuid)}
                    onNavigate={(view) => setCurrentView(view)}
                    onReload={loadDashboard}
                />
            )}

            {currentView === 'ppmps' && (
                <PPMPListPage
                    user={user}
                    onSelectPpmp={(uuid) => loadPpmpDetail(uuid)}
                    onNavigate={(view) => setCurrentView(view)}
                />
            )}

            {currentView === 'create' && (
                <PPMPFormPage
                    user={user}
                    onBack={() => setCurrentView('dashboard')}
                    onSaved={(newPpmp) => {
                        loadPpmpDetail(newPpmp.uuid);
                    }}
                />
            )}

            {currentView === 'edit' && currentPpmp && (
                <PPMPFormPage
                    user={user}
                    initialPpmp={currentPpmp}
                    onBack={() => setCurrentView('detail')}
                    onSaved={(updated) => {
                        loadPpmpDetail(updated.uuid);
                    }}
                />
            )}

            {currentView === 'detail' && currentPpmp && (
                <PPMPDetailPage
                    ppmp={currentPpmp}
                    user={user}
                    onBack={() => setCurrentView('ppmps')}
                    onEdit={() => setCurrentView('edit')}
                    onReload={(targetUuid) => loadPpmpDetail(targetUuid || currentPpmp.uuid)}
                />
            )}
        </MainLayout>
    );
}

const rootElement = document.getElementById('root');
if (rootElement) {
    createRoot(rootElement).render(<App />);
}
