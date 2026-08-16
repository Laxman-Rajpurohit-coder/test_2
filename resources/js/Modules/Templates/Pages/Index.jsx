import React, { useEffect, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import StatusBadge from '../Components/StatusBadge';

export default function Index({ auth, templates = [] }) {
    const [isSyncing, setIsSyncing] = useState(false);
    const [filter, setFilter] = useState('all');
    const [categoryFilter, setCategoryFilter] = useState('all');
    const [search, setSearch] = useState('');
    const [viewMode, setViewMode] = useState('table'); // 'table' | 'cards'
    const [jsonModalTemplate, setJsonModalTemplate] = useState(null);
    const [copiedName, setCopiedName] = useState(null);
    const [copiedJson, setCopiedJson] = useState(false);

    useEffect(() => {
        const shouldSync = templates.length === 0 || 
            (templates[0]?.synced_at && new Date() - new Date(templates[0].synced_at) > 10 * 60 * 1000);

        if (shouldSync) {
            handleSync(true);
        }
    }, []);

    const handleSync = async (silent = false) => {
        if (!silent) setIsSyncing(true);
        try {
            await axios.post(route('templates.sync'));
            router.reload({ only: ['templates'] });
        } catch (error) {
            console.error('Failed to sync templates', error);
        } finally {
            if (!silent) setIsSyncing(false);
        }
    };

    const handleDelete = (template) => {
        if (confirm(`Are you sure you want to delete "${template.name}"? This cannot be undone and may break active campaigns.`)) {
            router.delete(route('templates.destroy', template.id));
        }
    };

    const handleCopy = (name) => {
        navigator.clipboard.writeText(name);
        setCopiedName(name);
        setTimeout(() => setCopiedName(null), 2000);
    };

    const handleCopyJson = (content) => {
        navigator.clipboard.writeText(JSON.stringify(content, null, 2));
        setCopiedJson(true);
        setTimeout(() => setCopiedJson(false), 2000);
    };

    const parseComponents = (raw) => {
        if (!raw) return [];
        if (Array.isArray(raw)) return raw;
        try {
            return JSON.parse(raw);
        } catch (e) {
            return [];
        }
    };

    const getButtonsSummary = (components) => {
        const btnComp = components.find(c => (c.type || '').toUpperCase() === 'BUTTONS');
        if (!btnComp || !Array.isArray(btnComp.buttons) || btnComp.buttons.length === 0) {
            return null;
        }
        const quickCount = btnComp.buttons.filter(b => (b.type || '').toUpperCase() === 'QUICK_REPLY').length;
        const callCount = btnComp.buttons.filter(b => (b.type || '').toUpperCase() === 'PHONE_NUMBER').length;
        const urlCount = btnComp.buttons.filter(b => (b.type || '').toUpperCase() === 'URL').length;

        const parts = [];
        if (quickCount > 0) parts.push(`Quick Buttons ${quickCount}`);
        if (callCount > 0) parts.push(`Call (${callCount})`);
        if (urlCount > 0) parts.push(`URL (${urlCount})`);
        return parts.join(', ') || `Buttons (${btnComp.buttons.length})`;
    };

    const filteredTemplates = templates.filter(t => {
        if (filter !== 'all' && (t.status || '').toLowerCase() !== filter.toLowerCase()) return false;
        if (categoryFilter !== 'all' && (t.category || '').toUpperCase() !== categoryFilter.toUpperCase()) return false;
        if (search && !t.name.toLowerCase().includes(search.toLowerCase())) return false;
        return true;
    });

    const lastSynced = templates.length > 0 && templates[0].synced_at 
        ? new Date(templates[0].synced_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) 
        : 'Never';

    return (
        <AppLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="font-extrabold text-2xl text-gray-900 tracking-tight">WhatsApp Templates</h1>
                        <p className="text-xs text-gray-500 mt-0.5">Manage and sync approved MSG91 broadcast and utility message templates</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => handleSync()}
                            disabled={isSyncing}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-xl font-bold text-xs text-gray-700 shadow-xs hover:bg-gray-50 hover:border-gray-300 transition disabled:opacity-50"
                        >
                            <span className={isSyncing ? 'animate-spin' : ''}>🔄</span>
                            <span>{isSyncing ? 'Syncing...' : 'Sync from MSG91'}</span>
                        </button>
                        <Link
                            href={route('templates.create')}
                            className="inline-flex items-center gap-1.5 px-4 py-2 bg-[#00a884] hover:bg-emerald-700 text-white rounded-xl font-extrabold text-xs shadow-sm transition"
                        >
                            <span>+</span>
                            <span>New Template</span>
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="WhatsApp Templates" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    
                    {/* Filter & Controls Bar */}
                    <div className="bg-white p-4 sm:p-5 rounded-2xl border border-gray-200 shadow-xs space-y-4">
                        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                            
                            {/* Status Filter Tabs */}
                            <div className="flex items-center gap-1.5 p-1 bg-gray-100/80 rounded-xl overflow-x-auto">
                                {[
                                    { key: 'all', label: 'All' },
                                    { key: 'approved', label: 'Approved' },
                                    { key: 'pending', label: 'Pending' },
                                    { key: 'rejected', label: 'Rejected' },
                                ].map(({ key, label }) => {
                                    const count = key === 'all' 
                                        ? templates.length 
                                        : templates.filter(t => (t.status || '').toLowerCase() === key).length;
                                    const active = filter === key;
                                    return (
                                        <button
                                            key={key}
                                            onClick={() => setFilter(key)}
                                            className={`px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap ${
                                                active 
                                                    ? 'bg-white text-gray-900 shadow-xs' 
                                                    : 'text-gray-600 hover:text-gray-900'
                                            }`}
                                        >
                                            <span>{label}</span>
                                            <span className={`px-1.5 py-0.2 rounded-full text-[10px] font-extrabold ${
                                                active ? 'bg-[#00a884]/15 text-[#00a884]' : 'bg-gray-200 text-gray-600'
                                            }`}>
                                                {count}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>

                            {/* Category, Search & View Switcher */}
                            <div className="flex flex-wrap items-center gap-3">
                                <select
                                    value={categoryFilter}
                                    onChange={e => setCategoryFilter(e.target.value)}
                                    className="text-xs rounded-xl border-gray-200 font-medium text-gray-700 py-2 focus:ring-[#00a884] focus:border-[#00a884]"
                                >
                                    <option value="all">All Categories</option>
                                    <option value="MARKETING">Marketing</option>
                                    <option value="UTILITY">Utility</option>
                                    <option value="AUTHENTICATION">Authentication</option>
                                </select>

                                <div className="relative flex-1 sm:w-64">
                                    <input
                                        type="text"
                                        placeholder="Search template name..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="w-full pl-8 pr-3 py-2 text-xs rounded-xl border-gray-200 focus:ring-[#00a884] focus:border-[#00a884]"
                                    />
                                    <span className="absolute left-2.5 top-2.5 text-gray-400 text-xs">🔍</span>
                                </div>

                                {/* View Switcher */}
                                <div className="flex bg-gray-100 p-1 rounded-xl gap-1">
                                    <button
                                        onClick={() => setViewMode('table')}
                                        title="Table View"
                                        className={`p-1.5 rounded-lg text-xs font-bold transition ${
                                            viewMode === 'table' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-900'
                                        }`}
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                        </svg>
                                    </button>
                                    <button
                                        onClick={() => setViewMode('cards')}
                                        title="Cards View"
                                        className={`p-1.5 rounded-lg text-xs font-bold transition ${
                                            viewMode === 'cards' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-900'
                                        }`}
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                        </svg>
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    onClick={() => handleSync()}
                                    disabled={isSyncing}
                                    className="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-200 rounded-xl font-bold text-xs text-gray-700 hover:bg-gray-50 shadow-xs transition disabled:opacity-50"
                                    title="Sync templates with MSG91"
                                >
                                    <span className={isSyncing ? 'animate-spin' : ''}>🔄</span>
                                    <span>{isSyncing ? 'Syncing...' : 'Sync'}</span>
                                </button>

                                <Link
                                    href={route('templates.create')}
                                    className="inline-flex items-center gap-1.5 px-4 py-2 bg-[#00a884] hover:bg-[#008f70] text-white rounded-xl font-extrabold text-xs shadow-sm transition"
                                >
                                    <span>+</span>
                                    <span>Create Template</span>
                                </Link>
                            </div>
                        </div>

                        <div className="flex items-center justify-between text-[11px] text-gray-500 pt-2 border-t border-gray-100">
                            <span>Showing <b>{filteredTemplates.length}</b> of <b>{templates.length}</b> templates</span>
                            <span>Last synchronized: <b>{lastSynced}</b></span>
                        </div>
                    </div>

                    {/* Empty State */}
                    {filteredTemplates.length === 0 ? (
                        <div className="bg-white overflow-hidden shadow-xs rounded-2xl border border-gray-200 p-12 text-center space-y-4">
                            <div className="w-14 h-14 bg-emerald-50 text-[#00a884] rounded-full flex items-center justify-center text-2xl mx-auto">
                                📋
                            </div>
                            <div>
                                <h3 className="text-base font-bold text-gray-900">No templates found</h3>
                                <p className="text-xs text-gray-500 mt-1">Try changing your filters or sync from MSG91.</p>
                            </div>
                            <button
                                onClick={() => handleSync()}
                                className="inline-flex items-center gap-2 px-4 py-2 bg-[#00a884] text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition"
                            >
                                🔄 Sync Templates Now
                            </button>
                        </div>
                    ) : viewMode === 'table' ? (
                        /* TABLE VIEW (Matching MSG91 Dashboard Style) */
                        <div className="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-left text-xs">
                                    <thead className="bg-gray-50/80 text-gray-500 font-extrabold uppercase tracking-wider">
                                        <tr>
                                            <th className="py-3.5 px-4 sm:px-6">Name</th>
                                            <th className="py-3.5 px-4">Category</th>
                                            <th className="py-3.5 px-4">Language</th>
                                            <th className="py-3.5 px-4">Clicks / Buttons</th>
                                            <th className="py-3.5 px-4 text-center">Code (JSON)</th>
                                            <th className="py-3.5 px-4">Status</th>
                                            <th className="py-3.5 px-4 sm:px-6 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 text-gray-700 font-medium">
                                        {filteredTemplates.map((template) => {
                                            const components = parseComponents(template.components);
                                            const buttonsText = getButtonsSummary(components);
                                            const isApproved = (template.status || '').toLowerCase() === 'approved';

                                            return (
                                                <tr key={template.id} className="hover:bg-gray-50/60 transition group">
                                                    
                                                    {/* Template Name & Copy */}
                                                    <td className="py-3.5 px-4 sm:px-6">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-bold text-gray-900 text-xs">
                                                                {template.name}
                                                            </span>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleCopy(template.name)}
                                                                title="Copy Template Name"
                                                                className="text-gray-400 hover:text-gray-700 transition p-1 rounded hover:bg-gray-100"
                                                            >
                                                                {copiedName === template.name ? (
                                                                    <span className="text-emerald-600 font-bold text-[10px]">Copied!</span>
                                                                ) : (
                                                                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                                    </svg>
                                                                )}
                                                            </button>
                                                        </div>
                                                    </td>

                                                    {/* Category */}
                                                    <td className="py-3.5 px-4">
                                                        <span className="text-[11px] font-bold text-gray-600 uppercase tracking-wider">
                                                            {template.category || 'MARKETING'}
                                                        </span>
                                                    </td>

                                                    {/* Language Badge */}
                                                    <td className="py-3.5 px-4">
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200/60 uppercase">
                                                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                            {template.language}
                                                        </span>
                                                    </td>

                                                    {/* Clicks / Buttons */}
                                                    <td className="py-3.5 px-4">
                                                        {buttonsText ? (
                                                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-semibold bg-gray-100 text-gray-800 border border-gray-200">
                                                                <span>🔘</span>
                                                                <span>{buttonsText}</span>
                                                            </span>
                                                        ) : (
                                                            <span className="text-gray-400">-</span>
                                                        )}
                                                    </td>

                                                    {/* Code (JSON) */}
                                                    <td className="py-3.5 px-4 text-center">
                                                        <button
                                                            type="button"
                                                            onClick={() => setJsonModalTemplate(template)}
                                                            title="Inspect JSON Schema"
                                                            className="p-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-mono text-xs transition inline-flex items-center justify-center border border-gray-200"
                                                        >
                                                            &lt;&gt;
                                                        </button>
                                                    </td>

                                                    {/* Status */}
                                                    <td className="py-3.5 px-4">
                                                        <StatusBadge status={template.status} />
                                                    </td>

                                                    {/* Actions */}
                                                    <td className="py-3.5 px-4 sm:px-6 text-right">
                                                        <div className="flex items-center justify-end gap-2">
                                                            {isApproved && (
                                                                <Link
                                                                    href={route('campaigns.create', { template: template.id })}
                                                                    className="px-2.5 py-1 bg-[#00a884] hover:bg-emerald-700 text-white rounded-lg font-bold text-xs transition flex items-center gap-1 shadow-2xs"
                                                                >
                                                                    <span>Use</span>
                                                                    <span>➔</span>
                                                                </Link>
                                                            )}
                                                            <Link
                                                                href={route('templates.show', template.id)}
                                                                className="p-1.5 text-gray-500 hover:text-gray-900 rounded-lg hover:bg-gray-100 transition"
                                                                title="View Template"
                                                            >
                                                                👁️
                                                            </Link>
                                                            <Link
                                                                href={route('templates.edit', template.id)}
                                                                className="p-1.5 text-gray-500 hover:text-amber-700 rounded-lg hover:bg-gray-100 transition"
                                                                title="Duplicate / Edit Template"
                                                            >
                                                                📑
                                                            </Link>
                                                            <button
                                                                onClick={() => handleDelete(template)}
                                                                className="p-1.5 text-gray-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition"
                                                                title="Delete Template"
                                                            >
                                                                🗑️
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : (
                        /* CARDS VIEW */
                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {filteredTemplates.map((template) => {
                                const components = parseComponents(template.components);
                                const bodyText = components.find(c => (c.type || '').toUpperCase() === 'BODY')?.text || '';
                                const buttonsText = getButtonsSummary(components);
                                const isApproved = (template.status || '').toLowerCase() === 'approved';

                                return (
                                    <div key={template.id} className="bg-white overflow-hidden shadow-xs rounded-2xl border border-gray-200 flex flex-col hover:border-gray-300 transition">
                                        <div className="p-5 flex-grow min-w-0 space-y-3">
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="min-w-0">
                                                    <div className="flex items-center gap-1.5">
                                                        <h3 className="text-sm font-bold text-gray-900 truncate" title={template.name}>
                                                            {template.name}
                                                        </h3>
                                                        <button 
                                                            onClick={() => handleCopy(template.name)}
                                                            className="text-gray-400 hover:text-gray-700"
                                                            title="Copy Name"
                                                        >
                                                            {copiedName === template.name ? '✓' : '📋'}
                                                        </button>
                                                    </div>
                                                    <p className="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mt-0.5">
                                                        {template.category}
                                                    </p>
                                                </div>
                                                <div className="flex flex-col items-end gap-1 shrink-0">
                                                    <StatusBadge status={template.status} />
                                                    <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 uppercase">
                                                        {template.language}
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            {/* Preview Box */}
                                            <div className="bg-gray-50 p-3 rounded-xl text-xs text-gray-700 line-clamp-3 min-h-[60px] whitespace-pre-wrap border border-gray-100 font-normal">
                                                {bodyText || <span className="italic text-gray-400">No body text</span>}
                                            </div>

                                            {buttonsText && (
                                                <div className="text-[11px] font-semibold text-gray-700 flex items-center gap-1.5 bg-gray-50 p-2 rounded-lg border border-gray-100">
                                                    <span>🔘</span>
                                                    <span>{buttonsText}</span>
                                                </div>
                                            )}
                                        </div>

                                        <div className="bg-gray-50/80 px-5 py-3 border-t border-gray-100 flex items-center justify-between gap-2">
                                            <div className="flex items-center gap-2 text-xs font-bold">
                                                <Link href={route('templates.show', template.id)} className="text-gray-600 hover:text-gray-900">
                                                    View
                                                </Link>
                                                <span className="text-gray-300">•</span>
                                                <Link href={route('templates.edit', template.id)} className="text-amber-600 hover:text-amber-800">
                                                    Duplicate
                                                </Link>
                                                <span className="text-gray-300">•</span>
                                                <button onClick={() => handleDelete(template)} className="text-rose-600 hover:text-rose-800">
                                                    Delete
                                                </button>
                                            </div>
                                            
                                            {isApproved && (
                                                <Link 
                                                    href={route('campaigns.create', { template: template.id })} 
                                                    className="text-xs font-bold text-[#00a884] hover:text-emerald-800 flex items-center gap-1"
                                                >
                                                    Use ➔
                                                </Link>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            {/* JSON Code Modal */}
            {jsonModalTemplate && (
                <div 
                    onClick={() => setJsonModalTemplate(null)}
                    className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-xs"
                >
                    <div 
                        onClick={e => e.stopPropagation()}
                        className="bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-gray-200 overflow-hidden flex flex-col max-h-[85vh]"
                    >
                        <div className="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/80">
                            <div>
                                <h3 className="text-sm font-bold text-gray-900 font-mono">
                                    {jsonModalTemplate.name} ({jsonModalTemplate.language})
                                </h3>
                                <p className="text-[11px] text-gray-500">Raw MSG91 WhatsApp Component JSON</p>
                            </div>
                            <div className="flex items-center gap-2">
                                <button
                                    onClick={() => handleCopyJson(parseComponents(jsonModalTemplate.components))}
                                    className="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg text-xs font-bold transition"
                                >
                                    {copiedJson ? '✓ Copied' : 'Copy JSON'}
                                </button>
                                <button 
                                    onClick={() => setJsonModalTemplate(null)}
                                    className="p-1 rounded-lg text-gray-400 hover:text-gray-700 text-base font-bold"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>

                        <div className="p-4 overflow-y-auto bg-gray-900 text-emerald-400 font-mono text-xs leading-relaxed flex-1">
                            <pre className="whitespace-pre-wrap">
                                {JSON.stringify(parseComponents(jsonModalTemplate.components), null, 2)}
                            </pre>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
