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
                        <div className="bg-white rounded-xl border border-gray-200 overflow-visible mt-2 shadow-sm">
                            <div className="overflow-x-auto min-h-[300px]">
                                <table className="w-full text-left text-sm text-gray-700">
                                    <thead className="bg-white text-gray-500 font-semibold text-xs border-b border-gray-200">
                                        <tr>
                                            <th className="px-6 py-4 whitespace-nowrap">Name</th>
                                            <th className="px-6 py-4 whitespace-nowrap">Category <span className="text-gray-300">↑</span></th>
                                            <th className="px-6 py-4 whitespace-nowrap">Language</th>
                                            <th className="px-6 py-4 whitespace-nowrap">Clicks</th>
                                            <th className="px-6 py-4 whitespace-nowrap">Code (JSON)</th>
                                            <th className="px-6 py-4 whitespace-nowrap">Status</th>
                                            <th className="px-6 py-4 whitespace-nowrap text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {filteredTemplates.map((template) => (
                                            <tr key={template.id} className="hover:bg-gray-50 transition">
                                                <td className="px-6 py-3 whitespace-nowrap flex items-center gap-2">
                                                    <span className="font-medium text-gray-800">{template.name}</span>
                                                    <button className="text-gray-400 hover:text-gray-600 focus:outline-none" title="Copy name" onClick={() => navigator.clipboard.writeText(template.name)}>
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                                    </button>
                                                </td>
                                                <td className="px-6 py-3 whitespace-nowrap text-xs tracking-wide text-gray-600">{template.category}</td>
                                                <td className="px-6 py-3 whitespace-nowrap">
                                                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold ${template.status === 'approved' ? 'bg-green-50 text-green-700' : 'bg-orange-50 text-orange-700'}`}>
                                                        {template.status === 'approved' ? (
                                                            <svg className="w-3.5 h-3.5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd"></path></svg>
                                                        ) : (
                                                            <svg className="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                        )}
                                                        {template.language} <span className="text-[10px] text-gray-400 font-normal ml-0.5 border border-gray-300 rounded-sm px-0.5">D</span>
                                                    </span>
                                                </td>
                                                <td className="px-6 py-3 whitespace-nowrap text-gray-400">-</td>
                                                <td className="px-6 py-3 whitespace-nowrap">
                                                    <span className="text-gray-400 text-xs font-mono tracking-widest">&lt;&gt;</span>
                                                </td>
                                                <td className="px-6 py-3 whitespace-nowrap">
                                                    <div className="flex items-center gap-2">
                                                        <div className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors ${template.status === 'approved' ? 'bg-[#00a884]' : 'bg-gray-200'}`}>
                                                            <span className={`inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform ${template.status === 'approved' ? 'translate-x-5' : 'translate-x-1'}`} />
                                                        </div>
                                                        <span className="text-xs font-medium text-gray-700">
                                                            {template.status === 'approved' ? 'Enabled' : 'Disabled'}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-3 whitespace-nowrap text-right relative">
                                                    <div className="group inline-block text-left relative z-20">
                                                        <button className="text-gray-600 hover:text-gray-900 focus:outline-none p-1">
                                                            <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path></svg>
                                                        </button>
                                                        <div className="hidden group-hover:block absolute right-0 top-6 w-32 bg-white rounded-md shadow-lg border border-gray-100 py-1">
                                                            <Link href={route('templates.show', template.id)} className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-indigo-600 text-left">View</Link>
                                                            <Link href={route('templates.edit', template.id)} className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-amber-600 text-left">Duplicate</Link>
                                                            <button onClick={(e) => { e.stopPropagation(); handleDelete(template); }} className="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Delete</button>
                                                            {template.status === 'approved' && (
                                                                <Link href={route('campaigns.create', { template: template.id })} className="block px-4 py-2 text-sm text-green-600 hover:bg-green-50 text-left">Use Template &rarr;</Link>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
