/**
 * DevTools & Developer Mode Security Guard
 * 
 * Protects application from unauthorized inspection (F12, right-click Inspect, DevTools)
 * when Developer Mode is disabled by the Super Administrator.
 * When Developer Mode is ENABLED by Super Admin, all inspection and DevTools access is permitted.
 */

import { authService, systemSettingService } from '../services/api';

let isDevMode = false;
let isGuardInitialized = false;
let isHandlingUnauthorized = false;
let devCheckInterval = null;

// Initialize state from window.App or default false
if (typeof window !== 'undefined' && window.App && typeof window.App.developer_mode !== 'undefined') {
    isDevMode = Boolean(window.App.developer_mode);
}

/**
 * Check if Developer Mode is currently enabled
 */
export const isDeveloperModeActive = () => isDevMode;

/**
 * Update Developer Mode state in memory and notify listeners
 */
export const setDeveloperModeActive = (enabled) => {
    isDevMode = Boolean(enabled);
    if (typeof window !== 'undefined') {
        if (window.App) {
            window.App.developer_mode = isDevMode;
        }
        window.dispatchEvent(new CustomEvent('developer_mode:changed', {
            detail: { developer_mode: isDevMode }
        }));
    }
};

/**
 * Trigger unauthorized inspection action:
 * Immediately logs out user, dispatches unauthorized event, and redirects to login.
 */
export const handleUnauthorizedDevToolsAccess = (actionName = 'inspect') => {
    if (isDevMode) return;
    if (isHandlingUnauthorized) return;

    isHandlingUnauthorized = true;

    const message = 'Unauthorized inspection detected. Developer Mode is currently DISABLED by the Super Administrator.';

    // Log to console warning
    console.warn(`[SECURITY ALERT] ${actionName.toUpperCase()} is restricted when Developer Mode is disabled.`);

    // Dispatch custom event for UI banners/alerts
    window.dispatchEvent(new CustomEvent('devtools:unauthorized', {
        detail: { action: actionName, message }
    }));

    // If user session exists, perform instant logout and trigger auth:unauthorized
    const currentUser = window.App?.user;
    if (currentUser) {
        authService.logout().catch(() => {}).finally(() => {
            if (window.App) {
                window.App.user = null;
            }
            window.dispatchEvent(new CustomEvent('auth:unauthorized', {
                detail: { reason: message }
            }));
        });
    }

    setTimeout(() => {
        isHandlingUnauthorized = false;
    }, 2000);
};

/**
 * Setup DevTools Guard Listeners
 */
export const initDevToolsGuard = () => {
    if (isGuardInitialized || typeof window === 'undefined') return;
    isGuardInitialized = true;

    // 1. Sync latest developer mode setting from server
    systemSettingService.getDeveloperMode()
        .then((res) => {
            if (res.data && typeof res.data.developer_mode !== 'undefined') {
                setDeveloperModeActive(res.data.developer_mode);
            }
        })
        .catch(() => {});

    // Listen to real-time changes
    window.addEventListener('developer_mode:changed', (e) => {
        if (e.detail && typeof e.detail.developer_mode !== 'undefined') {
            isDevMode = Boolean(e.detail.developer_mode);
        }
    });

    // 2. Keyboard shortcut guard (F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C, Ctrl+U, Mac Cmd+Opt+...)
    window.addEventListener('keydown', (e) => {
        if (isDevMode) return; // Unrestricted when developer mode is ON

        const isF12 = e.key === 'F12' || e.keyCode === 123;
        const isCtrlOrCmd = e.ctrlKey || e.metaKey;
        const isShift = e.shiftKey;

        // Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C, Ctrl+Shift+K
        const isInspectShortcut = isCtrlOrCmd && isShift && (
            e.key === 'I' || e.key === 'i' || e.keyCode === 73 ||
            e.key === 'J' || e.key === 'j' || e.keyCode === 74 ||
            e.key === 'C' || e.key === 'c' || e.keyCode === 67 ||
            e.key === 'K' || e.key === 'k' || e.keyCode === 75
        );

        // Ctrl+U (View Source)
        const isViewSource = isCtrlOrCmd && (e.key === 'u' || e.key === 'U' || e.keyCode === 85);

        if (isF12 || isInspectShortcut || isViewSource) {
            e.preventDefault();
            e.stopPropagation();
            const action = isF12 ? 'F12 key' : (isViewSource ? 'View Source' : 'DevTools shortcut');
            handleUnauthorizedDevToolsAccess(action);
            return false;
        }
    }, true);

    // 3. Right-Click Context Menu Guard (prevent right-click "Inspect")
    window.addEventListener('contextmenu', (e) => {
        if (isDevMode) return; // Allowed when developer mode is ON

        e.preventDefault();
        e.stopPropagation();
        handleUnauthorizedDevToolsAccess('right-click inspect');
        return false;
    }, true);

    // 4. Periodic docked DevTools window dimension check (with zoom & display scaling compensation)
    const checkDevToolsDimensions = () => {
        if (isDevMode) return;

        const dpr = window.devicePixelRatio || 1;
        // In un-docked or normal windows, inner dimensions scaled by DPR correspond closely to outer dimensions minus OS borders
        const effectiveOuterWidth = Math.round(window.outerWidth / dpr);
        const effectiveOuterHeight = Math.round(window.outerHeight / dpr);

        const widthDiff = Math.abs(effectiveOuterWidth - window.innerWidth);
        const heightDiff = Math.abs(effectiveOuterHeight - window.innerHeight);
        const threshold = 220;

        // Only trigger if docked DevTools takes up significant window space
        if ((widthDiff > threshold || heightDiff > threshold) && window.outerWidth > 700 && window.outerHeight > 500) {
            handleUnauthorizedDevToolsAccess('docked devtools panel');
        }
    };

    // Run dimension check on resize and periodic interval
    window.addEventListener('resize', checkDevToolsDimensions);
    devCheckInterval = setInterval(checkDevToolsDimensions, 2000);
};

export default {
    initDevToolsGuard,
    isDeveloperModeActive,
    setDeveloperModeActive,
    handleUnauthorizedDevToolsAccess,
};
