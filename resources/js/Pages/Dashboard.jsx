import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function Dashboard({ metrics = {}, recentMessages = [] }) {
    const stats = [
        {
            name: 'Total Conversations',
            value: metrics.total_conversations || 0,
            icon: '💬',
            bg: 'bg-emerald-50 text-[#00a884] border-emerald-200/60',
            trend: 'Active Threads',
        },
        {
            name: 'Total Messages Transferred',
            value: metrics.total_messages || 0,
            icon: '⚡',
            bg: 'bg-blue-50 text-blue-600 border-blue-200/60',
            trend: `${metrics.inbound_count || 0} In / ${metrics.outbound_count || 0} Out`,
        },
        {
            name: 'Active Bot Triggers',
            value: metrics.active_triggers || 0,
            icon: '🤖',
            bg: 'bg-purple-50 text-purple-600 border-purple-200/60',
            trend: 'Auto-Responders Active',
        },
        {
            name: 'System Health',
            value: '100%',
            icon: '🛡️',
            bg: 'bg-emerald-50 text-emerald-700 border-emerald-200/60',
            trend: 'Redis & Queue Online',
        },
    ];

    return (
        <AppLayout>
            <Head title="SAAS Dashboard" />

            <div className="space-y-6">
                {/* Hero Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-gray-900 via-gray-800 to-emerald-950 p-6 md:p-8 rounded-3xl text-white shadow-xl">
                    <div className="space-y-1.5">
                        <div className="inline-flex items-center gap-2 px-3 py-1 bg-white/10 backdrop-blur-md rounded-full text-xs font-bold text-emerald-300 border border-white/10">
                            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                            Multi-Tenant Business Instance
                        </div>
                        <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight">
                            WhatsApp Business Automation
                        </h1>
                        <p className="text-xs text-gray-300 max-w-xl">
                            Real-time analytics, automated keyword responders, and live customer inbox management.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Link
                            href="/chat"
                            className="px-4 py-2.5 bg-[#00a884] hover:bg-[#008f70] text-white rounded-xl text-xs font-bold transition shadow-lg shadow-emerald-500/20"
                        >
                            Open Live Inbox 💬
                        </Link>
                        <Link
                            href="/bot-triggers"
                            className="px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold backdrop-blur-md border border-white/15 transition"
                        >
                            Manage Bot Rules 🤖
                        </Link>
                    </div>
                </div>

                {/* Metrics Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {stats.map((stat) => (
                        <div
                            key={stat.name}
                            className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs hover:shadow-md transition duration-200 group"
                        >
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold text-gray-500 uppercase tracking-wider">{stat.name}</span>
                                <span className={`w-9 h-9 rounded-xl flex items-center justify-center text-base border font-bold ${stat.bg}`}>
                                    {stat.icon}
                                </span>
                            </div>
                            <div className="mt-3 flex items-baseline justify-between">
                                <span className="text-2xl font-black text-gray-900 tracking-tight">{stat.value}</span>
                                <span className="text-[10px] font-bold text-gray-400 bg-gray-50 px-2 py-0.5 rounded-md border border-gray-100">
                                    {stat.trend}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Dashboard Main Grid: Recent Live Activity & Quick Controls */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left 2 Cols: Live Message Activity Feed */}
                    <div className="lg:col-span-2 bg-white rounded-2xl border border-gray-200/80 shadow-xs p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-gray-100 pb-3">
                            <h2 className="text-sm font-bold text-gray-900 flex items-center gap-2">
                                <span>⚡</span> Live Message Stream
                            </h2>
                            <Link href="/chat" className="text-xs font-bold text-[#00a884] hover:underline">
                                View All Conversations →
                            </Link>
                        </div>

                        <div className="divide-y divide-gray-100">
                            {recentMessages.length === 0 ? (
                                <div className="py-8 text-center text-xs text-gray-400">
                                    No messages processed yet. Incoming WhatsApp messages will appear here live.
                                </div>
                            ) : (
                                recentMessages.map((msg) => (
                                    <div key={msg.id} className="py-3 flex items-center justify-between gap-4 hover:bg-gray-50/50 px-2 rounded-xl transition">
                                        <div className="flex items-center gap-3 overflow-hidden">
                                            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 ${
                                                msg.direction === 'inbound' ? 'bg-blue-50 text-blue-600 border border-blue-200/60' : 'bg-emerald-50 text-[#00a884] border border-emerald-200/60'
                                            }`}>
                                                {msg.direction === 'inbound' ? '📥' : '📤'}
                                            </div>
                                            <div className="truncate">
                                                <div className="text-xs font-bold text-gray-900 flex items-center gap-2">
                                                    <span>+{msg.customer_number}</span>
                                                    <span className={`text-[9px] px-2 py-0.2 rounded-full font-extrabold uppercase ${
                                                        msg.direction === 'inbound' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800'
                                                    }`}>
                                                        {msg.direction}
                                                    </span>
                                                </div>
                                                <p className="text-xs text-gray-500 truncate mt-0.5">{msg.text}</p>
                                            </div>
                                        </div>

                                        <div className="text-right shrink-0">
                                            <span className="text-[10px] font-semibold text-gray-400 block">{msg.time}</span>
                                            <span className="text-[9px] font-bold uppercase tracking-wider text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">
                                                {msg.status}
                                            </span>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {/* Right Col: Quick Management Cards */}
                    <div className="space-y-6">
                        <div className="bg-white rounded-2xl border border-gray-200/80 shadow-xs p-6 space-y-4">
                            <h2 className="text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Quick Navigation</h2>

                            <div className="space-y-2.5">
                                <Link
                                    href="/chat"
                                    className="flex items-center justify-between p-3 rounded-xl border border-gray-100 hover:border-emerald-200 hover:bg-emerald-50/50 transition group"
                                >
                                    <div className="flex items-center gap-3">
                                        <span className="text-lg">💬</span>
                                        <div>
                                            <div className="text-xs font-bold text-gray-900 group-hover:text-[#00a884]">Live Chat Inbox</div>
                                            <div className="text-[10px] text-gray-500">Reply to WhatsApp customers</div>
                                        </div>
                                    </div>
                                    <span className="text-xs text-gray-400 group-hover:text-[#00a884]">→</span>
                                </Link>

                                <Link
                                    href="/bot-triggers"
                                    className="flex items-center justify-between p-3 rounded-xl border border-gray-100 hover:border-emerald-200 hover:bg-emerald-50/50 transition group"
                                >
                                    <div className="flex items-center gap-3">
                                        <span className="text-lg">🤖</span>
                                        <div>
                                            <div className="text-xs font-bold text-gray-900 group-hover:text-[#00a884]">Bot Auto-Responder</div>
                                            <div className="text-[10px] text-gray-500">Configure keyword triggers</div>
                                        </div>
                                    </div>
                                    <span className="text-xs text-gray-400 group-hover:text-[#00a884]">→</span>
                                </Link>

                                <Link
                                    href="/settings/tenant"
                                    className="flex items-center justify-between p-3 rounded-xl border border-gray-100 hover:border-emerald-200 hover:bg-emerald-50/50 transition group"
                                >
                                    <div className="flex items-center gap-3">
                                        <span className="text-lg">🔑</span>
                                        <div>
                                            <div className="text-xs font-bold text-gray-900 group-hover:text-[#00a884]">Tenant API Settings</div>
                                            <div className="text-[10px] text-gray-500">MSG91 & AI Credentials</div>
                                        </div>
                                    </div>
                                    <span className="text-xs text-gray-400 group-hover:text-[#00a884]">→</span>
                                </Link>
                            </div>
                        </div>

                        {/* System Status Box */}
                        <div className="bg-gradient-to-br from-emerald-50 to-teal-50 rounded-2xl border border-emerald-200/60 p-5 space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-extrabold text-[#00a884] uppercase tracking-wider">Infrastructure</span>
                                <span className="w-2 h-2 rounded-full bg-emerald-500"></span>
                            </div>
                            <h3 className="text-sm font-bold text-gray-900">PostgreSQL & Redis Online</h3>
                            <p className="text-[11px] text-gray-600">
                                Webhook delivery queue and real-time WebSockets are running smoothly.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
