import React, { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';

export default function AppLayout({ children }) {
    const { auth } = usePage().props;
    const userName = auth?.user?.name || 'MTech Systems';
    const userEmail = auth?.user?.email || 'admin@msg91.com';
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [userDropdownOpen, setUserDropdownOpen] = useState(false);

    const currentPath = window.location.pathname;
    const isChatPage = currentPath.startsWith('/chat');
    const isBotPage = currentPath.startsWith('/bot-triggers');
    const isTenantSettingsPage = currentPath.startsWith('/settings/tenant');

    const navigation = [
        { name: 'Dashboard', href: '/dashboard', icon: '🎛️', active: currentPath === '/dashboard' || currentPath.startsWith('/analytics') },
        { name: 'Inbox', href: '/chat', icon: '💬', badge: 'Live', active: isChatPage },
        { name: 'Bot Auto-Responder', href: '/bot-triggers', icon: '🤖', active: isBotPage },
        { name: 'Tenant API Settings', href: '/settings/tenant', icon: '🔑', active: isTenantSettingsPage },
        { name: 'WhatsApp Automation', href: '#', icon: '🏪', hasSub: true },
        { name: 'Contacts', href: '#', icon: '📇', hasSub: true },
        { name: 'Team Management', href: '#', icon: '👥' },
        { name: 'Integrations', href: '#', icon: '🔌' },
        { name: 'Message Logs', href: '#', icon: '📜' },
    ];

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
                        <div className="flex items-center gap-2.5 overflow-hidden">
                            <div className="w-8 h-8 rounded-full bg-emerald-100 text-[#00a884] font-bold text-xs flex items-center justify-center border border-emerald-200">
                                {userName.substring(0, 2).toUpperCase()}
                            </div>
                            <div className="truncate">
                                <div className="text-xs font-bold text-gray-900 truncate">{userName}</div>
                                <div className="text-[10px] text-gray-400 truncate">{userEmail}</div>
                            </div>
                        </div>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="p-1.5 text-gray-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition"
                            title="Log Out"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1m0-10V5" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </aside>

            {/* Mobile Header Top Navigation */}
            <div className="md:hidden fixed top-0 inset-x-0 h-16 bg-white border-b border-gray-200/80 flex items-center justify-between px-4 z-40">
                <div className="flex items-center gap-2">
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
                </div>
            )}

            {/* Main Content Area */}
            <div className="flex-1 md:ml-64 flex flex-col min-w-0 min-h-screen">
                {/* Top Desktop Header Bar */}
                <header className="h-16 bg-white border-b border-gray-200/80 flex items-center justify-between px-6 md:px-8 sticky top-0 z-40 shadow-xs">
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

                <main className="flex-1 p-6 md:p-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
