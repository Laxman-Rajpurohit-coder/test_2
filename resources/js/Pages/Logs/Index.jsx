import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import Pagination from '@/Components/Pagination';

export default function Index({ logs, filters }) {
    const handleFilterChange = (e) => {
        const { name, value } = e.target;
        router.get(route('logs.index'), { ...filters, [name]: value }, { preserveState: true });
    };

    const handleSearch = (e) => {
        if (e.key === 'Enter') {
            router.get(route('logs.index'), { ...filters, search: e.target.value }, { preserveState: true });
        }
    };

    const formatContent = (contentJson) => {
        try {
            const data = JSON.parse(contentJson);
            if (data.type === 'text') return data.text;
            if (data.type === 'template') return `[Template: ${data.template_name}] ${data.text}`;
            if (data.type === 'image') return `[Image] ${data.caption || ''}`;
            if (data.type === 'audio') return `[Audio]`;
            if (data.type === 'interactive') return `[Interactive] ${data.text}`;
            if (data.type === 'note') return `[Internal Note] ${data.text}`;
            return data.text || 'Unsupported content type';
        } catch {
            return contentJson;
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

    return (
        <AppLayout>
            <Head title="Message Logs" />

            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Message Logs</h1>
                    <p className="text-sm text-gray-500 mt-1">Audit trail for all inbound and outbound WhatsApp messages.</p>
                </div>
                <div className="flex gap-3">
                    <div className="relative">
                        <input
                            type="text"
                            name="search"
                            defaultValue={filters.search}
                            onKeyDown={handleSearch}
                            placeholder="Search numbers or UUIDs..."
                            className="w-64 pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg className="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col h-[calc(100vh-14rem)]">
                <div className="p-4 border-b border-gray-200 bg-gray-50 flex gap-4 shrink-0">
                    <select
                        name="status"
                        value={filters.status || ''}
                        onChange={handleFilterChange}
                        className="border-gray-200 rounded-lg text-sm focus:ring-indigo-500"
                    >
                        <option value="">All Statuses</option>
                        <option value="queued">Queued</option>
                        <option value="sent">Sent</option>
                        <option value="delivered">Delivered</option>
                        <option value="read">Read</option>
                        <option value="failed">Failed</option>
                        <option value="received">Received</option>
                    </select>

                    <select
                        name="direction"
                        value={filters.direction || ''}
                        onChange={handleFilterChange}
                        className="border-gray-200 rounded-lg text-sm focus:ring-indigo-500"
                    >
                        <option value="">All Directions</option>
                        <option value="inbound">Inbound</option>
                        <option value="outbound">Outbound</option>
                    </select>
                </div>

                <div className="flex-1 overflow-auto">
                    <table className="w-full text-left text-sm whitespace-nowrap">
                        <thead className="bg-gray-50 sticky top-0 z-10 border-b border-gray-200 shadow-sm">
                            <tr>
                                <th className="px-6 py-3 font-semibold text-gray-900">Timestamp</th>
                                <th className="px-6 py-3 font-semibold text-gray-900">Direction</th>
                                <th className="px-6 py-3 font-semibold text-gray-900">Customer</th>
                                <th className="px-6 py-3 font-semibold text-gray-900">Content</th>
                                <th className="px-6 py-3 font-semibold text-gray-900">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {logs.data.map((log) => (
                                <tr key={log.id} className="hover:bg-gray-50/50 transition-colors">
                                    <td className="px-6 py-4 text-gray-500">
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
                                        <div className="max-w-md truncate text-gray-700" title={formatContent(log.content)}>
                                            {formatContent(log.content)}
                                        </div>
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
                                    <td colSpan="5" className="px-6 py-12 text-center text-gray-400">
                                        <svg className="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <p className="text-sm">No messages found matching your criteria.</p>
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
