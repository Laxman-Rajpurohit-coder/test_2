import React, { useEffect, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import StatusBadge from '../Components/StatusBadge';

export default function Index({ auth, templates }) {
    const [isSyncing, setIsSyncing] = useState(false);
    const [filter, setFilter] = useState('all');
    const [search, setSearch] = useState('');

    useEffect(() => {
        // Auto-sync if we have templates and the first one is older than 10 mins
        // or if we have no templates at all (might be first load)
        const shouldSync = templates.length === 0 || 
            (templates[0].synced_at && new Date() - new Date(templates[0].synced_at) > 10 * 60 * 1000);

        if (shouldSync) {
            handleSync(true); // silent sync
        }
    }, []);

    const handleSync = async (silent = false) => {
        if (!silent) setIsSyncing(true);
        try {
            await axios.post(route('templates.sync'));
            router.reload({ only: ['templates'] });
        } catch (error) {
            console.error('Failed to sync templates', error);
            // Could show a toast here if not silent
        } finally {
            if (!silent) setIsSyncing(false);
        }
    };

    const handleDelete = (template) => {
        if (confirm(`Are you sure you want to delete "${template.name}"? This cannot be undone and may break active campaigns.`)) {
            router.delete(route('templates.destroy', template.id));
        }
    };

    const filteredTemplates = templates.filter(t => {
        if (filter !== 'all' && t.status !== filter) return false;
        if (search && !t.name.toLowerCase().includes(search.toLowerCase())) return false;
        return true;
    });

    const lastSynced = templates.length > 0 ? new Date(templates[0].synced_at).toLocaleString() : 'Never';

    return (
        <AppLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">WhatsApp Templates</h2>
                    <div className="flex space-x-3">
                        <button
                            onClick={() => handleSync()}
                            disabled={isSyncing}
                            className="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                        >
                            {isSyncing ? 'Syncing...' : 'Sync from MSG91'}
                        </button>
                        <Link
                            href={route('templates.create')}
                            className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        >
                            New Template
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Templates" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    
                    <div className="flex flex-col md:flex-row md:justify-between md:items-center mb-6 gap-4">
                        <div className="flex flex-wrap gap-2">
                            {['all', 'approved', 'pending', 'rejected'].map(f => (
                                <button
                                    key={f}
                                    onClick={() => setFilter(f)}
                                    className={`px-4 py-2 rounded-full text-sm font-medium capitalize ${filter === f ? 'bg-indigo-100 text-indigo-700' : 'bg-white text-gray-500 hover:bg-gray-50'}`}
                                >
                                    {f}
                                </button>
                            ))}
                        </div>
                        <div className="flex flex-wrap items-center gap-4">
                            <span className="text-xs text-gray-500">Last synced: {lastSynced}</span>
                            <input
                                type="text"
                                placeholder="Search templates..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm sm:text-sm w-full md:w-auto"
                            />
                            <Link
                                href={route('templates.create')}
                                className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 whitespace-nowrap"
                            >
                                New Template
                            </Link>
                        </div>
                    </div>

                    {filteredTemplates.length === 0 ? (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-12 text-center">
                            <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <h3 className="mt-2 text-sm font-medium text-gray-900">No templates</h3>
                            <p className="mt-1 text-sm text-gray-500">Get started by creating a new WhatsApp template.</p>
                            <div className="mt-6">
                                <Link
                                    href={route('templates.create')}
                                    className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    <svg className="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fillRule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clipRule="evenodd" />
                                    </svg>
                                    New Template
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {filteredTemplates.map((template) => {
                                const components = Array.isArray(template.components) ? template.components : [];
                                const bodyText = components.find(c => c.type === 'BODY')?.text || '';
                                return (
                                    <div key={template.id} className="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200 flex flex-col">
                                        <div className="p-5 flex-grow min-w-0">
                                            <div className="flex items-start justify-between mb-2 gap-2">
                                                <div className="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 min-w-0">
                                                    <h3 className="text-lg font-medium text-gray-900 truncate" title={template.name}>
                                                        {template.name}
                                                    </h3>
                                                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 uppercase self-start sm:self-auto shrink-0">
                                                        {template.language}
                                                    </span>
                                                </div>
                                                <div className="shrink-0">
                                                    <StatusBadge status={template.status} />
                                                </div>
                                            </div>
                                            <p className="text-xs text-gray-500 mb-4 uppercase tracking-wider">{template.category}</p>
                                            
                                            <div className="bg-gray-50 p-3 rounded text-sm text-gray-700 line-clamp-3 h-20 overflow-hidden">
                                                {bodyText}
                                            </div>
                                        </div>
                                        <div className="bg-gray-50 px-5 py-3 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3">
                                            <div className="flex flex-wrap items-center gap-3">
                                                <Link href={route('templates.show', template.id)} className="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                                    View
                                                </Link>
                                                <Link href={route('templates.edit', template.id)} className="text-sm font-medium text-amber-600 hover:text-amber-700">
                                                    Duplicate
                                                </Link>
                                                <button onClick={() => handleDelete(template)} className="text-sm font-medium text-red-600 hover:text-red-900">
                                                    Delete
                                                </button>
                                            </div>
                                            {template.status === 'approved' && (
                                                <Link href={route('campaigns.create', { template: template.id })} className="text-sm font-medium text-green-600 hover:text-green-900 flex items-center ml-auto">
                                                    Use <span aria-hidden="true" className="ml-1">&rarr;</span>
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
        </AppLayout>
    );
}
