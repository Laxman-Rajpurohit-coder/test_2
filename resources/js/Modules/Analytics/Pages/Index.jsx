import React, { useState, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    ResponsiveContainer,
    AreaChart,
    Area,
    BarChart,
    Bar,
    PieChart,
    Pie,
    Cell,
    XAxis,
    YAxis,
    Tooltip,
    Legend,
} from 'recharts';

export default function AnalyticsIndex({ metrics, recentMessages: initialRecent = [], tasks = [], filters }) {
    const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    
    const [dateFrom, setDateFrom] = useState(filters?.date_from || metrics?.date_range?.from || '');
    const [dateTo, setDateTo] = useState(filters?.date_to || metrics?.date_range?.to || '');
    const [activePreset, setActivePreset] = useState('30D');
    const [recentMessages, setRecentMessages] = useState(initialRecent);

    const { auth, impersonation } = usePage().props;
    const activeTenantId = impersonation?.is_impersonating 
        ? impersonation.tenant_id 
        : auth?.user?.tenant_id;

    useEffect(() => {
        setRecentMessages(initialRecent);
    }, [initialRecent]);

    // Real-time WebSocket Live Stream Listener (Reusing MessageReceived channel pattern)
    useEffect(() => {
        if (typeof window !== 'undefined' && window.Echo && activeTenantId) {
            const channel = window.Echo.private(`tenant.${activeTenantId}`);
            channel.listen('.message.received', (e) => {
                if (e && e.message) {
                    const content = typeof e.message.content === 'string' 
                        ? JSON.parse(e.message.content) 
                        : (e.message.content || {});
                    
                    const newStreamMsg = {
                        id: e.message.id || Math.random().toString(),
                        customer_number: e.customer_number || 'Incoming Customer',
                        direction: e.message.direction || 'inbound',
                        status: e.message.status || 'received',
                        text: content.text || content.caption || 'Media Message',
                        time: 'Just now',
                    };

                    setRecentMessages((prev) => [newStreamMsg, ...prev.slice(0, 5)]);
                }
            });

            return () => {
                window.Echo.leaveChannel(`private-tenant.${activeTenantId}`);
            };
        }
    }, [activeTenantId]);

    const totals = metrics?.totals || { conversations: 0, inbound_messages: 0, outbound_messages: 0, total_messages: 0, active_triggers: 0 };
    const messagesPerDay = metrics?.messages_per_day || [];
    const deliveryStatus = metrics?.delivery_status || { queued: 0, sent: 0, delivered: 0, read: 0, failed: 0, received: 0 };
    const busiestHours = metrics?.busiest_hours || [];
    const avgFirstResponse = metrics?.avg_first_response_minutes;
    const typeBreakdown = metrics?.type_breakdown || { text: 0, image: 0, audio: 0, template: 0 };

    const applyFilters = (fromVal, toVal, presetKey = 'custom') => {
        setActivePreset(presetKey);
        // Dynamic route navigation stays on current pathname (/dashboard or /analytics)
        router.get(
            window.location.pathname,
            {
                date_from: fromVal,
                date_to: toVal,
                timezone: userTimezone,
            },
            { preserveState: true, replace: true }
        );
    };

    const setPreset = (days, label) => {
        const today = new Date();
        const toStr = today.toISOString().split('T')[0];
        const fromDate = new Date();
        fromDate.setDate(today.getDate() - days);
        const fromStr = fromDate.toISOString().split('T')[0];

        setDateFrom(fromStr);
        setDateTo(toStr);
        applyFilters(fromStr, toStr, label);
    };

    const pieData = [
        { name: 'Delivered', value: deliveryStatus.delivered, color: '#10b981' },
        { name: 'Read', value: deliveryStatus.read, color: '#06b6d4' },
        { name: 'Sent', value: deliveryStatus.sent, color: '#3b82f6' },
        { name: 'Queued', value: deliveryStatus.queued, color: '#f59e0b' },
        { name: 'Failed', value: deliveryStatus.failed, color: '#ef4444' },
        { name: 'Received', value: deliveryStatus.received, color: '#8b5cf6' },
    ].filter(item => item.value > 0);

    return (
        <AppLayout>
            <Head title="SAAS Master Dashboard & Analytics" />

            <div className="space-y-6">
                {/* Hero Header Banner with Quick-Action Buttons */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-gray-900 via-gray-800 to-emerald-950 p-6 md:p-8 rounded-3xl text-white shadow-xl">
                    <div className="space-y-1.5">
                        <div className="inline-flex items-center gap-2 px-3 py-1 bg-white/10 backdrop-blur-md rounded-full text-xs font-bold text-emerald-300 border border-white/10">
                            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                            Multi-Tenant Business Instance
                        </div>
                        <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight">
                            WhatsApp SAAS Dashboard & Analytics
                        </h1>
                        <p className="text-xs text-gray-300 max-w-xl">
                            Real-time volume trends, interactive analytics, live message stream, and automated bot management.
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
                            Bot Rules 🤖
                        </Link>
                        <Link
                            href="/settings/tenant"
                            className="px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold backdrop-blur-md border border-white/15 transition"
                        >
                            API Settings 🔑
                        </Link>
                    </div>
                </div>

                {/* Date Filter & Control Bar */}
                <div className="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="flex items-center gap-2">
                        <span className="text-base">📅</span>
                        <span className="text-xs font-bold text-gray-900">Filter Date Range</span>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <div className="flex items-center gap-1 bg-gray-100 p-1 rounded-xl">
                            <button
                                onClick={() => setPreset(7, '7D')}
                                className={`px-3 py-1 text-xs font-bold rounded-lg transition ${
                                    activePreset === '7D' ? 'bg-[#00a884] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'
                                }`}
                            >
                                7 Days
                            </button>
                            <button
                                onClick={() => setPreset(14, '14D')}
                                className={`px-3 py-1 text-xs font-bold rounded-lg transition ${
                                    activePreset === '14D' ? 'bg-[#00a884] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'
                                }`}
                            >
                                14 Days
                            </button>
                            <button
                                onClick={() => setPreset(30, '30D')}
                                className={`px-3 py-1 text-xs font-bold rounded-lg transition ${
                                    activePreset === '30D' ? 'bg-[#00a884] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'
                                }`}
                            >
                                30 Days
                            </button>
                        </div>

                        <div className="flex items-center gap-2 text-xs">
                            <input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="bg-gray-50 text-gray-800 border border-gray-200 rounded-lg px-2.5 py-1 text-xs focus:ring-[#00a884]"
                            />
                            <span className="text-gray-400">to</span>
                            <input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="bg-gray-50 text-gray-800 border border-gray-200 rounded-lg px-2.5 py-1 text-xs focus:ring-[#00a884]"
                            />
                            <button
                                onClick={() => applyFilters(dateFrom, dateTo, 'custom')}
                                className="bg-[#00a884] hover:bg-[#008f70] text-white px-3 py-1 rounded-lg text-xs font-bold transition shadow-sm"
                            >
                                Apply
                            </button>
                        </div>
                    </div>
                </div>

                {/* Dashboard Task & Issue Sheet (Pinned to Top) */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-4">
                    <div className="flex items-center justify-between border-b border-gray-100 pb-3">
                        <h2 className="text-base font-bold text-gray-900 flex items-center gap-2">
                            <span>📋</span> Active Customer Task Sheet
                        </h2>
                        <div className="flex items-center gap-1.5 bg-gray-50 p-1 rounded-lg border border-gray-200/50">
                            <span className="text-[10px] text-gray-400 font-bold uppercase px-2">Reminders Active</span>
                        </div>
                    </div>

                    {tasks.length === 0 ? (
                        <div className="py-8 text-center text-xs text-gray-400">
                            No active reminders or customer issues logged. Create reminders from the Chat Details panel.
                        </div>
                    ) : (
                        <div className="w-full overflow-x-auto custom-scrollbar">
                            <table className="w-full text-left text-xs border-collapse min-w-[700px]">
                                <thead>
                                    <tr className="border-b border-gray-100 text-gray-400 font-bold uppercase tracking-wider">
                                        <th className="pb-3 pt-1 pl-2 min-w-[140px]">Customer</th>
                                        <th className="pb-3 pt-1 min-w-[200px]">Task Title & Details</th>
                                        <th className="pb-3 pt-1 min-w-[140px]">Due Date</th>
                                        <th className="pb-3 pt-1 min-w-[100px]">Status</th>
                                        <th className="pb-3 pt-1 pr-2 min-w-[120px] text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {tasks.map((task) => {
                                        const isOverdue = task.due_at && new Date(task.due_at) <= new Date() && task.status !== 'resolved';
                                        return (
                                            <tr key={task.id} className="hover:bg-gray-50/50 transition">
                                                <td className="py-3.5 pl-2 font-bold text-gray-900">
                                                    {task.contact?.phone_number ? (
                                                        <Link
                                                            href={`/chat`}
                                                            className="text-[#00a884] hover:underline"
                                                        >
                                                            +{task.contact.phone_number}
                                                        </Link>
                                                    ) : (
                                                        <span className="text-gray-400">No contact linked</span>
                                                    )}
                                                </td>
                                                <td className="py-3.5 max-w-xs">
                                                    <div className="font-bold text-gray-900">{task.title}</div>
                                                    {task.description && (
                                                        <div className="text-[11px] text-gray-400 mt-0.5 truncate" title={task.description}>
                                                            {task.description}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="py-3.5">
                                                    {task.due_at ? (
                                                        <span className={`font-semibold ${isOverdue ? 'text-rose-600 font-bold bg-rose-50 px-2 py-0.5 rounded border border-rose-100' : 'text-gray-600'}`}>
                                                            {new Date(task.due_at).toLocaleString()}
                                                            {isOverdue && ' (Overdue)'}
                                                        </span>
                                                    ) : (
                                                        <span className="text-gray-400">No due date</span>
                                                    )}
                                                </td>
                                                <td className="py-3.5">
                                                    <span className={`text-[9px] px-2 py-0.5 rounded-full font-extrabold uppercase ${
                                                        task.status === 'resolved' 
                                                            ? 'bg-green-50 text-green-700 border border-green-200' 
                                                            : task.status === 'in_progress' 
                                                            ? 'bg-amber-50 text-amber-700 border border-amber-200' 
                                                            : 'bg-blue-50 text-blue-700 border border-blue-200'
                                                    }`}>
                                                        {task.status.replace('_', ' ')}
                                                    </span>
                                                </td>
                                                <td className="py-3.5 pr-2 text-right space-x-2">
                                                    {task.status !== 'resolved' && (
                                                        <button
                                                            onClick={() => router.patch(route('tasks.status', task.id), { status: 'resolved' }, { preserveScroll: true })}
                                                            className="px-2 py-1 bg-green-50 hover:bg-green-100 text-green-700 font-bold rounded-lg border border-green-200/60 transition"
                                                            title="Resolve Task"
                                                        >
                                                            ✓ Resolve
                                                        </button>
                                                    )}
                                                    {task.status === 'open' && (
                                                        <button
                                                            onClick={() => router.patch(route('tasks.status', task.id), { status: 'in_progress' }, { preserveScroll: true })}
                                                            className="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 font-bold rounded-lg border border-amber-200/60 transition"
                                                            title="Mark In Progress"
                                                        >
                                                            In Progress
                                                        </button>
                                                    )}
                                                    <button
                                                        onClick={() => {
                                                            if (confirm('Are you sure you want to delete this task?')) {
                                                                router.delete(route('tasks.destroy', task.id), { preserveScroll: true });
                                                            }
                                                        }}
                                                        className="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded-lg border border-rose-200/60 transition"
                                                        title="Delete Reminder"
                                                    >
                                                        ✕
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Top Metric Cards Row */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs flex items-center justify-between">
                        <div>
                            <span className="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Conversations</span>
                            <div className="text-3xl font-extrabold text-gray-900 mt-1">{totals.conversations}</div>
                            <Link href="/chat" className="text-[11px] text-[#00a884] font-semibold mt-2 inline-block hover:underline">Manage Inbox →</Link>
                        </div>
                        <div className="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl font-bold border border-purple-200/60">
                            💬
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs flex items-center justify-between">
                        <div>
                            <span className="text-xs font-bold text-gray-400 uppercase tracking-wider">Messages Processed</span>
                            <div className="text-3xl font-extrabold text-[#00a884] mt-1">{totals.total_messages}</div>
                            <span className="text-[11px] text-gray-500 font-semibold mt-2 inline-block">In: {totals.inbound_messages} | Out: {totals.outbound_messages}</span>
                        </div>
                        <div className="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-bold border border-blue-200/60">
                            ⚡
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs flex items-center justify-between">
                        <div>
                            <span className="text-xs font-bold text-gray-400 uppercase tracking-wider">Active Bot Rules</span>
                            <div className="text-3xl font-extrabold text-purple-600 mt-1">{totals.active_triggers ?? 0}</div>
                            <Link href="/bot-triggers" className="text-[11px] text-purple-600 font-semibold mt-2 inline-block hover:underline">Manage Triggers →</Link>
                        </div>
                        <div className="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl font-bold border border-rose-200/60">
                            🤖
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs flex items-center justify-between">
                        <div>
                            <span className="text-xs font-bold text-gray-400 uppercase tracking-wider">Avg Response Time</span>
                            <div className="text-3xl font-extrabold text-amber-600 mt-1">
                                {avgFirstResponse !== null ? `${avgFirstResponse}m` : 'N/A'}
                            </div>
                            <span className="text-[11px] text-gray-400 font-semibold mt-2 inline-block">Automated Speed</span>
                        </div>
                        <div className="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold border border-amber-200/60">
                            ⏱️
                        </div>
                    </div>
                </div>

                {/* Main Section: Chart 1 (Daily Volume) & Live Activity Stream */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Chart 1: Messages Volume Over Time (Area Chart) */}
                    <div className="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-4">
                        <h2 className="text-base font-bold text-gray-900 flex items-center gap-2">
                            <span>📈</span> Daily Message Volume Trend
                        </h2>
                        <div className="h-72 w-full">
                            {messagesPerDay.length > 0 ? (
                                <ResponsiveContainer width="100%" height="100%">
                                    <AreaChart data={messagesPerDay} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
                                        <defs>
                                            <linearGradient id="colorInbound" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="#10b981" stopOpacity={0.8}/>
                                                <stop offset="95%" stopColor="#10b981" stopOpacity={0}/>
                                            </linearGradient>
                                            <linearGradient id="colorOutbound" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="#a855f7" stopOpacity={0.8}/>
                                                <stop offset="95%" stopColor="#a855f7" stopOpacity={0}/>
                                            </linearGradient>
                                        </defs>
                                        <XAxis dataKey="date" stroke="#9ca3af" strokeWidth={0.5} tick={{ fontSize: 12 }} />
                                        <YAxis stroke="#9ca3af" strokeWidth={0.5} tick={{ fontSize: 12 }} />
                                        <Tooltip contentStyle={{ backgroundColor: '#ffffff', borderColor: '#e5e7eb', borderRadius: '12px', shadow: '0 10px 15px -3px rgba(0,0,0,0.1)', color: '#111827' }} />
                                        <Legend />
                                        <Area type="monotone" dataKey="inbound" name="Inbound Messages" stroke="#10b981" fillOpacity={1} fill="url(#colorInbound)" />
                                        <Area type="monotone" dataKey="outbound" name="Outbound Messages" stroke="#a855f7" fillOpacity={1} fill="url(#colorOutbound)" />
                                    </AreaChart>
                                </ResponsiveContainer>
                            ) : (
                                <div className="flex h-full items-center justify-center text-gray-400 text-xs font-semibold">No message activity recorded in this date range.</div>
                            )}
                        </div>
                    </div>

                    {/* Live Activity Stream */}
                    <div className="bg-white rounded-2xl border border-gray-200/80 shadow-xs p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-gray-100 pb-3">
                            <h2 className="text-sm font-bold text-gray-900 flex items-center gap-2">
                                <span>⚡</span> Live Stream
                            </h2>
                            <Link href="/chat" className="text-xs font-bold text-[#00a884] hover:underline">
                                View Inbox →
                            </Link>
                        </div>

                        <div className="divide-y divide-gray-100">
                            {recentMessages.length === 0 ? (
                                <div className="py-8 text-center text-xs text-gray-400">
                                    No live messages recorded.
                                </div>
                            ) : (
                                recentMessages.map((msg) => (
                                    <div key={msg.id} className="py-2.5 flex items-center justify-between gap-3 hover:bg-gray-50/50 px-2 rounded-xl transition">
                                        <div className="flex items-center gap-2.5 overflow-hidden">
                                            <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 ${
                                                msg.direction === 'inbound' ? 'bg-blue-50 text-blue-600 border border-blue-200/60' : 'bg-emerald-50 text-[#00a884] border border-emerald-200/60'
                                            }`}>
                                                {msg.direction === 'inbound' ? '📥' : '📤'}
                                            </div>
                                            <div className="truncate">
                                                <div className="text-xs font-bold text-gray-900 truncate">+{msg.customer_number}</div>
                                                <p className="text-[11px] text-gray-500 truncate mt-0.5">{msg.text}</p>
                                            </div>
                                        </div>

                                        <div className="text-right shrink-0">
                                            <span className="text-[9px] font-semibold text-gray-400 block">{msg.time}</span>
                                            <span className="text-[9px] font-bold uppercase tracking-wider text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">
                                                {msg.status}
                                            </span>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>

                {/* Secondary Section: Peak Hours & Delivery Ratio Donut */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Busiest Hours Histogram */}
                    <div className="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs">
                        <h2 className="text-base font-bold text-gray-900 mb-4">⏰ Peak Hourly Activity (Local Time)</h2>
                        <div className="h-64 w-full">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={busiestHours}>
                                    <XAxis dataKey="hour" stroke="#9ca3af" tick={{ fontSize: 10 }} />
                                    <YAxis stroke="#9ca3af" tick={{ fontSize: 10 }} />
                                    <Tooltip contentStyle={{ backgroundColor: '#ffffff', borderColor: '#e5e7eb', borderRadius: '12px', color: '#111827' }} />
                                    <Bar dataKey="count" name="Messages" fill="#00a884" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                    {/* Delivery Status Donut Chart */}
                    <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs">
                        <h2 className="text-base font-bold text-gray-900 mb-4">🎯 Delivery Status Ratio</h2>
                        <div className="h-64 w-full">
                            {pieData.length > 0 ? (
                                <ResponsiveContainer width="100%" height="100%">
                                    <PieChart>
                                        <Pie data={pieData} cx="50%" cy="50%" innerRadius={50} outerRadius={80} paddingAngle={4} dataKey="value">
                                            {pieData.map((entry, index) => (
                                                <Cell key={`cell-${index}`} fill={entry.color} />
                                            ))}
                                        </Pie>
                                        <Tooltip contentStyle={{ backgroundColor: '#ffffff', borderColor: '#e5e7eb', borderRadius: '12px', color: '#111827' }} />
                                        <Legend />
                                    </PieChart>
                                </ResponsiveContainer>
                            ) : (
                                <div className="flex h-full items-center justify-center text-gray-400 text-xs font-semibold">No status data available.</div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Content Type Breakdown Cards */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs">
                    <h2 className="text-base font-bold text-gray-900 mb-4">📁 Content Type Distribution</h2>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div className="bg-gray-50 p-4 rounded-xl border border-gray-100">
                            <span className="text-xs text-gray-500 font-semibold">Text Messages</span>
                            <div className="text-2xl font-bold text-gray-900 mt-1">{typeBreakdown.text}</div>
                        </div>
                        <div className="bg-gray-50 p-4 rounded-xl border border-gray-100">
                            <span className="text-xs text-gray-500 font-semibold">Photos / Images</span>
                            <div className="text-2xl font-bold text-cyan-600 mt-1">{typeBreakdown.image}</div>
                        </div>
                        <div className="bg-gray-50 p-4 rounded-xl border border-gray-100">
                            <span className="text-xs text-gray-500 font-semibold">Voice Notes (Audio)</span>
                            <div className="text-2xl font-bold text-amber-600 mt-1">{typeBreakdown.audio}</div>
                        </div>
                        <div className="bg-gray-50 p-4 rounded-xl border border-gray-100">
                            <span className="text-xs text-gray-500 font-semibold">Templates</span>
                            <div className="text-2xl font-bold text-pink-600 mt-1">{typeBreakdown.template}</div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
