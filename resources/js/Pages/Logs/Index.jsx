import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import Pagination from '@/Components/Pagination';

export default function Index({ logs, filters, billing }) {
    const handleFilterChange = (name, value) => {
        router.get(route('logs.index'), { ...filters, [name]: value }, { preserveState: true, replace: true });
    };

    const handleSearch = (e) => {
        if (e.key === 'Enter') {
            router.get(route('logs.index'), { ...filters, search: e.target.value }, { preserveState: true, replace: true });
        }
    };

    const handleClearFilters = () => {
        router.get(route('logs.index'), {}, { preserveState: true, replace: true });
    };

    const formatContent = (contentJson) => {
        try {
            const data = typeof contentJson === 'string' ? JSON.parse(contentJson) : contentJson;
            if (!data) return '';
            if (data.type === 'text') return data.text || '';
            if (data.type === 'template') return `[Template: ${data.template_name || 'WhatsApp'}] ${data.text || ''}`;
            if (data.type === 'image') return `[Image] ${data.caption || ''}`;
            if (data.type === 'audio') return `[Audio]`;
            if (data.type === 'video') return `[Video] ${data.caption || ''}`;
            if (data.type === 'document') return `[Document] ${data.filename || ''}`;
            if (data.type === 'interactive') return `[Interactive] ${data.text || ''}`;
            if (data.type === 'note') return `[Internal Note] ${data.text || ''}`;
            return data.text || 'Message Content';
        } catch {
            return String(contentJson || '');
        }
    };

    const getStatusBadge = (status) => {
        const colors = {
            queued: 'bg-amber-100 text-amber-800 border-amber-200',
            sent: 'bg-sky-100 text-sky-800 border-sky-200',
            delivered: 'bg-emerald-100 text-emerald-800 border-emerald-200',
            read: 'bg-indigo-100 text-indigo-800 border-indigo-200',
            failed: 'bg-rose-100 text-rose-800 border-rose-200',
            received: 'bg-slate-100 text-slate-800 border-slate-200'
        };
        const color = colors[status] || 'bg-gray-100 text-gray-800 border-gray-200';
        return <span className={`px-2 py-0.5 text-[10px] font-bold uppercase rounded-md border ${color}`}>{status}</span>;
    };

    const getCategoryBadge = (category) => {
        if (!category) return null;
        if (category.includes('Marketing')) {
            return <span className="px-2 py-0.5 text-[10px] font-bold rounded-md bg-purple-100 text-purple-700 border border-purple-200">{category}</span>;
        }
        if (category.includes('Utility')) {
            return <span className="px-2 py-0.5 text-[10px] font-bold rounded-md bg-blue-100 text-blue-700 border border-blue-200">{category}</span>;
        }
        if (category.includes('Authentication')) {
            return <span className="px-2 py-0.5 text-[10px] font-bold rounded-md bg-emerald-100 text-emerald-700 border border-emerald-200">{category}</span>;
        }
        if (category.includes('Media')) {
            return <span className="px-2 py-0.5 text-[10px] font-bold rounded-md bg-amber-100 text-amber-700 border border-amber-200">{category}</span>;
        }
        return <span className="px-2 py-0.5 text-[10px] font-medium rounded-md bg-gray-100 text-gray-700 border border-gray-200">{category}</span>;
    };

    // Total page cost calculation
    const totalPageCost = logs.data.reduce((acc, item) => acc + (parseFloat(item.estimated_cost) || 0), 0);

    return (
        <AppLayout>
            <Head title="Message Logs" />

            {/* Header */}
            <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-3">
                        Message Logs
                        <span className="text-xs font-semibold px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-full border border-indigo-200">
                            Audit & Billing Logs
                        </span>
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">Audit trail, date range filters, and billing calculations for all messages.</p>
                </div>

                <div className="flex items-center gap-3">
                    {/* Search */}
                    <div className="relative">
                        <input
                            type="text"
                            name="search"
                            defaultValue={filters.search || ''}
                            onKeyDown={handleSearch}
                            placeholder="Search numbers or IDs..."
                            className="w-full sm:w-64 pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg className="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            {/* Cost Summary Banner */}
            <div className="mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="bg-white border border-gray-200 rounded-xl p-4 shadow-sm flex items-center justify-between">
                    <div>
                        <div className="text-xs font-medium text-gray-500 uppercase tracking-wider">Filtered Messages</div>
                        <div className="text-xl font-bold text-gray-900 mt-1">{logs.total || logs.data.length} Messages</div>
                    </div>
                    <div className="h-10 w-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center font-bold text-lg">
                        📩
                    </div>
                </div>

                <div className="bg-white border border-gray-200 rounded-xl p-4 shadow-sm flex items-center justify-between">
                    <div>
                        <div className="text-xs font-medium text-gray-500 uppercase tracking-wider">Page Estimated Cost</div>
                        <div className="text-xl font-bold text-emerald-600 mt-1">₹{totalPageCost.toFixed(4)}</div>
                    </div>
                    <div className="h-10 w-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center font-bold text-lg">
                        💰
                    </div>
                </div>

                <div className="bg-white border border-gray-200 rounded-xl p-4 shadow-sm flex items-center justify-between">
                    <div>
                        <div className="text-xs font-medium text-gray-500 uppercase tracking-wider">Billing Rate Unit</div>
                        <div className="text-sm font-bold text-gray-800 mt-1">Per {billing?.rate_unit || 1000} Messages</div>
                    </div>
                    <div className="h-10 w-10 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center font-bold text-lg">
                        📊
                    </div>
                </div>
            </div>

            {/* Main Log Table */}
            <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col">
                {/* Advanced Filter Toolbar */}
                <div className="p-4 border-b border-gray-200 bg-gray-50 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 shrink-0">
                    {/* Status Filter */}
                    <div>
                        <label className="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Status</label>
                        <select
                            name="status"
                            value={filters.status || ''}
                            onChange={(e) => handleFilterChange('status', e.target.value)}
                            className="w-full border-gray-200 rounded-lg text-xs focus:ring-indigo-500"
                        >
                            <option value="">All Statuses</option>
                            <option value="queued">Queued</option>
                            <option value="sent">Sent</option>
                            <option value="delivered">Delivered</option>
                            <option value="read">Read</option>
                            <option value="failed">Failed</option>
                            <option value="received">Received</option>
                        </select>
                    </div>

                    {/* Direction Filter */}
                    <div>
                        <label className="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Direction</label>
                        <select
                            name="direction"
                            value={filters.direction || ''}
                            onChange={(e) => handleFilterChange('direction', e.target.value)}
                            className="w-full border-gray-200 rounded-lg text-xs focus:ring-indigo-500"
                        >
                            <option value="">All Directions</option>
                            <option value="inbound">Inbound</option>
                            <option value="outbound">Outbound</option>
                        </select>
                    </div>

                    {/* Content Type Filter */}
                    <div>
                        <label className="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Content Type</label>
                        <select
                            name="type"
                            value={filters.type || ''}
                            onChange={(e) => handleFilterChange('type', e.target.value)}
                            className="w-full border-gray-200 rounded-lg text-xs focus:ring-indigo-500"
                        >
                            <option value="">All Content Types</option>
                            <option value="text">Text</option>
                            <option value="template">Template</option>
                            <option value="image">Image</option>
                            <option value="audio">Audio</option>
                            <option value="video">Video</option>
                            <option value="document">Document</option>
                            <option value="interactive">Interactive</option>
                            <option value="note">Internal Note</option>
                        </select>
                    </div>

                    {/* Start Date & Time Filter */}
                    <div>
                        <label className="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Start Date & Time</label>
                        <input
                            type="datetime-local"
                            name="start_date"
                            value={filters.start_date || ''}
                            onChange={(e) => handleFilterChange('start_date', e.target.value)}
                            className="w-full border-gray-200 rounded-lg text-xs focus:ring-indigo-500"
                        />
                    </div>

                    {/* End Date & Time Filter */}
                    <div>
                        <div className="flex items-center justify-between mb-1">
                            <label className="block text-[11px] font-semibold text-gray-500 uppercase">End Date & Time</label>
                            {(filters.start_date || filters.end_date || filters.type || filters.status || filters.direction) && (
                                <button
                                    type="button"
                                    onClick={handleClearFilters}
                                    className="text-[10px] font-bold text-rose-600 hover:underline"
                                >
                                    Clear Filters
                                </button>
                            )}
                        </div>
                        <input
                            type="datetime-local"
                            name="end_date"
                            value={filters.end_date || ''}
                            onChange={(e) => handleFilterChange('end_date', e.target.value)}
                            className="w-full border-gray-200 rounded-lg text-xs focus:ring-indigo-500"
                        />
                    </div>
                </div>

                {/* Table */}
                <div className="flex-1 overflow-x-auto min-h-[400px]">
                    <table className="w-full min-w-[900px] text-left text-sm whitespace-nowrap">
                        <thead className="bg-gray-50 sticky top-0 z-10 border-b border-gray-200 shadow-sm">
                            <tr>
                                <th className="px-6 py-3 font-semibold text-gray-900">Timestamp</th>
                                <th className="px-6 py-3 font-semibold text-gray-900">Direction</th>
                                <th className="px-6 py-3 font-semibold text-gray-900">Customer</th>
                                <th className="px-6 py-3 font-semibold text-gray-900">Content</th>
                                <th className="px-6 py-3 font-semibold text-gray-900">Billing Category</th>
                                <th className="px-6 py-3 font-semibold text-gray-900">Est. Cost</th>
                                <th className="px-6 py-3 font-semibold text-gray-900">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {logs.data.map((log) => (
                                <tr key={log.id} className="hover:bg-gray-50/50 transition-colors">
                                    <td className="px-6 py-4 text-xs text-gray-500">
                                        {new Date(log.created_at).toLocaleString()}
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-medium ${
                                            log.direction === 'inbound' ? 'bg-sky-50 text-sky-700' : 'bg-indigo-50 text-indigo-700'
                                        }`}>
                                            {log.direction === 'inbound' ? '↓ In' : '↑ Out'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <Link href={`/contacts/${log.customer_number}`} className="font-medium text-indigo-600 hover:text-indigo-900">
                                            {log.customer_number}
                                        </Link>
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="max-w-xs truncate text-gray-700" title={formatContent(log.content)}>
                                            {formatContent(log.content)}
                                        </div>
                                    </td>
                                    {/* Billing Category Column */}
                                    <td className="px-6 py-4">
                                        {getCategoryBadge(log.billing_category)}
                                    </td>
                                    {/* Est. Cost Column */}
                                    <td className="px-6 py-4">
                                        <span className="font-mono text-xs font-bold text-gray-900">
                                            ₹{parseFloat(log.estimated_cost || 0).toFixed(4)}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="flex flex-col items-start gap-1">
                                            {getStatusBadge(log.status)}
                                            {log.failure_reason && (
                                                <span className="text-[10px] text-rose-500 max-w-xs truncate" title={log.failure_reason}>
                                                    {log.failure_reason}
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {logs.data.length === 0 && (
                                <tr>
                                    <td colSpan="7" className="px-6 py-12 text-center text-gray-400">
                                        <svg className="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <p className="text-sm font-medium">No messages found matching your criteria.</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="border-t border-gray-200 bg-white p-4 shrink-0">
                    <Pagination links={logs.links} />
                </div>
            </div>
        </AppLayout>
    );
}
