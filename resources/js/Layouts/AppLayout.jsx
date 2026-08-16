import React, { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';

/**
 * Render the application shell with responsive navigation and the main content area.
 * @param {React.ReactNode} children - The content to render inside the main content area.
 * @returns {JSX.Element} The application layout.
 */
export default function AppLayout({ children, header }) {
    const { auth, tenant_features, flash } = usePage().props;
    const userName = auth?.user?.name || 'MTech Systems';
    const userEmail = auth?.user?.email || 'admin@msg91.com';
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [userDropdownOpen, setUserDropdownOpen] = useState(false);
    const [toast, setToast] = useState(null);

    useEffect(() => {
        if (flash?.success) {
            setToast({ type: 'success', message: flash.success });
        } else if (flash?.error) {
            setToast({ type: 'error', message: flash.error });
        }
    }, [flash]);

    useEffect(() => {
        if (toast) {
            const timer = setTimeout(() => setToast(null), 4000);
            return () => clearTimeout(timer);
        }
    }, [toast]);

    // Layout mount state initialization
    useEffect(() => {
        localStorage.setItem('is_logged_in', 'true');
        
        // pagehide listener to disable browser bfcache cleanly without Permissions Policy violations
        const handlePageHide = () => {};
        window.addEventListener('pagehide', handlePageHide);

        return () => {
            window.removeEventListener('pagehide', handlePageHide);
        };
    }, []);


    const currentPath = window.location.pathname;
    const isChatPage = currentPath.startsWith('/chat');
    const isBotPage = currentPath.startsWith('/bot-triggers');
    const isTenantSettingsPage = currentPath.startsWith('/settings/tenant');

    const features = tenant_features || {};
    const userRole = auth?.user?.role || 'owner';
    const isMember = userRole === 'member';

    const navigation = [
        { name: 'Dashboard', href: '/dashboard', icon: '🎛️', active: currentPath === '/dashboard' || currentPath.startsWith('/analytics') },
        { name: 'Inbox', href: '/chat', icon: '💬', badge: 'Live', active: isChatPage },
        features.bot_auto_responder && !isMember && { name: 'Bot Auto-Responder', href: '/bot-triggers', icon: '🤖', active: isBotPage },
        features.flow_builder && !isMember && { name: 'Flow Builder', href: '/flows', icon: '🔄', active: currentPath.startsWith('/flows') },
        !isMember && { name: 'Tenant API Settings', href: '/settings/tenant', icon: '🔑', active: isTenantSettingsPage },
        { name: 'Contacts', href: '/contacts', icon: '📇', active: currentPath.startsWith('/contacts') },
        { name: 'Campaigns', href: '/campaigns', icon: '📢', active: currentPath.startsWith('/campaigns') },
        features.template_management && !isMember && { name: 'Templates', href: '/templates', icon: '📑', active: currentPath.startsWith('/templates') },
        !isMember && { name: 'Team Management', href: '/team', icon: '👥', active: currentPath.startsWith('/team') },
        { name: 'Integrations', href: '/coming-soon', icon: '🔌', active: currentPath === '/coming-soon' },
        { name: 'Message Logs', href: '/logs', icon: '📜', active: currentPath.startsWith('/logs') },
    ].filter(Boolean);

    return (
        <div className="min-h-screen bg-[#f4f6f9] text-gray-800 flex font-sans antialiased">
            {/* Sidebar Desktop */}
            <aside className="w-64 bg-white border-r border-gray-200/80 flex flex-col hidden md:flex fixed inset-y-0 z-50 shadow-sm">
                {/* Brand Logo Header */}
                <div className="h-16 flex items-center justify-between px-6 border-b border-gray-100">
                    <div className="flex items-center gap-2">
                        <div className="w-8 h-8 rounded-lg bg-[#00a884] flex items-center justify-center text-white font-extrabold text-lg shadow-md shadow-emerald-500/20">
                            W
                        </div>
                        <span className="font-extrabold text-lg tracking-tight text-gray-900">MSG91<span className="text-[#00a884]">WA</span></span>
                    </div>
                    <span className="text-[10px] uppercase tracking-wider bg-emerald-50 text-[#00a884] px-2 py-0.5 rounded-full font-bold border border-emerald-200/50">
                        PRO
                    </span>
                </div>

                {/* Main Navigation Links */}
                <nav className="flex-1 px-3 py-5 space-y-1.5 overflow-y-auto custom-scrollbar">
                    {navigation.map((item) => (
                        <Link
                            key={item.name}
                            href={item.href}
                            className={`group flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150 ${
                                item.active
                                    ? 'bg-[#00a884] text-white shadow-md shadow-emerald-500/20'
                                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                            }`}
                        >
                            <div className="flex items-center gap-3">
                                <span className="text-base">{item.icon}</span>
                                <span>{item.name}</span>
                            </div>
                            {item.badge && (
                                <span className={`text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider ${
                                    item.active ? 'bg-white/20 text-white' : 'bg-emerald-100 text-[#00a884]'
                                }`}>
                                    {item.badge}
                                </span>
                            )}
                            {item.hasSub && (
                                <span className="text-xs text-gray-400 group-hover:text-gray-600">›</span>
                            )}
                        </Link>
                    ))}
                </nav>

                {/* Bottom User Card */}
                <div className="p-4 border-t border-gray-100">
                    <div className="flex items-center justify-between bg-gray-50/80 p-2.5 rounded-xl border border-gray-100">
                        <Link
                            href={route('profile.edit')}
                            className="flex items-center gap-2.5 overflow-hidden group hover:opacity-80 transition"
                            title="Edit Profile"
                        >
                            <div className="w-8 h-8 rounded-full bg-emerald-100 text-[#00a884] font-bold text-xs flex items-center justify-center border border-emerald-200 shrink-0 group-hover:bg-[#00a884] group-hover:text-white transition">
                                {auth?.user?.tenant?.name ? auth.user.tenant.name.substring(0, 2).toUpperCase() : userName.substring(0, 2).toUpperCase()}
                            </div>
                            <div className="truncate">
                                <div className="text-xs font-bold text-gray-900 truncate" title={auth?.user?.tenant?.name || userName}>
                                    {auth?.user?.tenant?.name || userName}
                                </div>
                                <div className="text-[10px] text-gray-500 truncate font-medium" title={userName}>
                                    {userName}
                                </div>
                            </div>
                        </Link>
                        <div className="flex items-center gap-0.5">
                            <Link
                                href={route('profile.edit')}
                                className="p-1.5 text-gray-400 hover:text-gray-700 rounded-lg hover:bg-gray-200/60 transition"
                                title="Profile Settings"
                            >
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </Link>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                onClick={() => localStorage.removeItem('is_logged_in')}
                                className="p-1.5 text-gray-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition"
                                title="Log Out"
                            >
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1m0-10V5" />
                                </svg>
                            </Link>
                        </div>
                    </div>
                </div>
            </aside>

            {/* Mobile Header Top Navigation */}
            <div className="md:hidden fixed top-0 inset-x-0 h-16 bg-white border-b border-gray-200/80 flex items-center justify-between px-4 z-40">
                <div className="flex items-center gap-2">
                    {currentPath !== '/dashboard' && (
                        <Link href="/dashboard" className="p-1 -ml-1 text-gray-600 hover:bg-gray-100 rounded-lg" title="Back to Main Menu">
                            <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </Link>
                    )}
                    <div className="w-8 h-8 rounded-lg bg-[#00a884] flex items-center justify-center text-white font-extrabold text-base">
                        W
                    </div>
                    <span className="font-extrabold text-base tracking-tight text-gray-900">MSG91<span className="text-[#00a884]">WA</span></span>
                </div>

                <button
                    onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                    className="p-2 rounded-lg text-gray-600 hover:bg-gray-100"
                >
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>

            {/* Mobile Navigation Drawer */}
            {mobileMenuOpen && (
                <div className="md:hidden fixed inset-0 z-50 flex">
                    <div className="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" onClick={() => setMobileMenuOpen(false)}></div>
                    <div className="relative w-64 bg-white flex flex-col z-10 p-4 space-y-2">
                        <div className="flex items-center justify-between pb-4 border-b border-gray-100">
                            <span className="font-bold text-lg text-gray-900">Navigation</span>
                            <button onClick={() => setMobileMenuOpen(false)} className="text-gray-500 hover:text-gray-900">✕</button>
                        </div>
                        <div className="flex-1 overflow-y-auto space-y-1">
                            {navigation.map((item) => (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    onClick={() => setMobileMenuOpen(false)}
                                    className={`flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold ${
                                        item.active ? 'bg-[#00a884] text-white' : 'text-gray-700 hover:bg-gray-100'
                                    }`}
                                >
                                    <span>{item.icon}</span>
                                    <span>{item.name}</span>
                                </Link>
                            ))}
                        </div>

                        {/* Mobile User Profile & Logout */}
                        <div className="mt-auto pt-4 border-t border-gray-100">
                            <div className="flex items-center justify-between bg-gray-50/80 p-3 rounded-xl border border-gray-100">
                                <Link
                                    href={route('profile.edit')}
                                    onClick={() => setMobileMenuOpen(false)}
                                    className="flex items-center gap-3 overflow-hidden group hover:opacity-80 transition"
                                    title="Edit Profile"
                                >
                                    <div className="w-9 h-9 rounded-full bg-emerald-100 text-[#00a884] font-bold text-sm flex items-center justify-center border border-emerald-200 shrink-0 group-hover:bg-[#00a884] group-hover:text-white transition">
                                        {auth?.user?.tenant?.name ? auth.user.tenant.name.substring(0, 2).toUpperCase() : userName.substring(0, 2).toUpperCase()}
                                    </div>
                                    <div className="truncate">
                                        <div className="text-sm font-bold text-gray-900 truncate" title={auth?.user?.tenant?.name || userName}>
                                            {auth?.user?.tenant?.name || userName}
                                        </div>
                                        <div className="text-xs text-gray-500 truncate font-medium" title={userEmail}>
                                            {userEmail}
                                        </div>
                                    </div>
                                </Link>
                                <div className="flex items-center gap-1 shrink-0">
                                    <Link
                                        href={route('profile.edit')}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="p-2 text-gray-400 hover:text-gray-700 rounded-lg hover:bg-gray-200/60 transition"
                                        title="Profile Settings"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </Link>
                                    <Link
                                        href={route('logout')}
                                        method="post"
                                        as="button"
                                        onClick={() => localStorage.removeItem('is_logged_in')}
                                        className="p-2 text-gray-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition shrink-0"
                                        title="Log Out"
                                    >
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1m0-10V5" />
                                        </svg>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Main Content Area */}
            <div className="flex-1 md:ml-64 flex flex-col min-w-0 min-h-screen pt-16 md:pt-0">
                {/* Top Desktop Header Bar */}
                <header className="hidden md:flex h-16 bg-white border-b border-gray-200/80 items-center justify-between px-6 md:px-8 sticky top-0 z-40 shadow-xs">
                    <div className="flex items-center gap-4 flex-1 max-w-md">
                        <div className="relative w-full">
                            <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">🔍</span>
                            <input
                                type="text"
                                placeholder="Search conversations, numbers, triggers..."
                                className="w-full pl-9 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] transition outline-none"
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-2 px-3 py-1.5 bg-emerald-50 text-[#00a884] border border-emerald-200/60 rounded-xl font-semibold text-xs">
                            <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>System Active</span>
                        </div>
                    </div>
                </header>

                {usePage().props.impersonation?.is_impersonating && (
                    <div className="bg-rose-50 border-b border-rose-200 px-6 py-2 flex items-center justify-between sticky top-16 z-30">
                        <div className="flex items-center gap-2 text-rose-800">
                            <span className="animate-pulse">🔴</span>
                            <span className="font-bold text-sm">Super Admin Impersonation Active:</span>
                            <span className="text-sm">Viewing as <strong>{usePage().props.impersonation.tenant_name}</strong> (Read-Only)</span>
                        </div>
                        <Link href="/admin/impersonate-stop" method="post" as="button" type="button" className="px-4 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-lg shadow-sm transition-colors">
                            Exit Impersonation
                        </Link>
                    </div>
                )}

                <main className={`flex-1 flex flex-col ${isChatPage ? 'p-2 md:p-4' : 'p-6 md:p-8'}`}>
                    {header && <div className="mb-6">{header}</div>}
                    {children}
                </main>
            </div>

            {/* Global Toast Notification */}
            {toast && (
                <div className="fixed bottom-4 right-4 z-[100] animate-fade-in-up">
                    <div className={`flex items-center gap-3 px-4 py-3 rounded-lg shadow-xl border ${
                        toast.type === 'success' 
                            ? 'bg-emerald-50 border-emerald-200 text-emerald-800' 
                            : 'bg-rose-50 border-rose-200 text-rose-800'
                    }`}>
                        {toast.type === 'success' ? (
                            <svg className="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        ) : (
                            <svg className="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        )}
                        <span className="text-sm font-semibold">{toast.message}</span>
                        <button onClick={() => setToast(null)} className="ml-2 hover:opacity-70 transition-opacity">
                            ✕
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

// Add Tailwind custom animation to index.css or just rely on simple transition.
// But we'll just add inline style for the animation if needed, or stick to standard Tailwind.
