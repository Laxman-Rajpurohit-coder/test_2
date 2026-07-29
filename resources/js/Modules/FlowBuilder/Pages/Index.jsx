import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Index({ flows }) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
    });

    const handleCreate = (e) => {
        e.preventDefault();
        post(route('flows.store'), {
            onSuccess: () => {
                reset();
                setIsCreateOpen(false);
            },
        });
    };

    const toggleActive = (flow) => {
        router.put(route('flows.update', flow.id), {
            is_active: !flow.is_active,
        });
    };

    const handleDelete = (id) => {
        if (confirm('Are you sure you want to delete this flow? Active customer sessions will be terminated.')) {
            router.delete(route('flows.destroy', id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-slate-100">
                            Flow Builder
                        </h2>
                        <p className="text-sm text-slate-400">
                            Design, automate, and orchestrate multi-step WhatsApp customer journeys
                        </p>
                    </div>
                    <button
                        onClick={() => setIsCreateOpen(true)}
                        className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-500 transition-all"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Create New Flow
                    </button>
                </div>
            }
        >
            <Head title="Flow Builder" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Flow List Cards / Grid */}
                {flows.length === 0 ? (
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl p-12 text-center">
                        <div className="w-16 h-16 mx-auto mb-4 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                            <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <h3 className="text-lg font-semibold text-slate-200">No Flows Created Yet</h3>
                        <p className="text-sm text-slate-400 mt-1 max-w-md mx-auto">
                            Build visual drag-and-drop conversational workflows for automated WhatsApp support and lead generation.
                        </p>
                        <button
                            onClick={() => setIsCreateOpen(true)}
                            className="mt-6 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-500 shadow-lg shadow-indigo-500/20 transition-all"
                        >
                            Create First Flow
                        </button>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {flows.map((flow) => (
                            <div
                                key={flow.id}
                                className="bg-slate-900 border border-slate-800 hover:border-slate-700 rounded-2xl p-6 transition-all flex flex-col justify-between"
                            >
                                <div className="space-y-4">
                                    <div className="flex items-start justify-between">
                                        <h3 className="text-lg font-semibold text-slate-100 truncate">
                                            {flow.name}
                                        </h3>
                                        <button
                                            onClick={() => toggleActive(flow)}
                                            className={`px-2.5 py-1 text-xs font-semibold rounded-full border transition-all ${
                                                flow.is_active
                                                    ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/20'
                                                    : 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'
                                            }`}
                                        >
                                            {flow.is_active ? 'Active' : 'Inactive'}
                                        </button>
                                    </div>

                                    <div className="flex items-center gap-4 text-xs text-slate-400 border-t border-b border-slate-800/80 py-3">
                                        <div>
                                            <span className="text-slate-500 block">Active Sessions</span>
                                            <span className="font-semibold text-slate-200">{flow.active_sessions}</span>
                                        </div>
                                        <div className="border-l border-slate-800 pl-4">
                                            <span className="text-slate-500 block">Created</span>
                                            <span className="font-semibold text-slate-200">{flow.created_at}</span>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between pt-4 mt-2">
                                    <Link
                                        href={route('flows.show', flow.id)}
                                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-400 hover:text-indigo-300 transition-colors"
                                    >
                                        Open Canvas Editor
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                    <button
                                        onClick={() => handleDelete(flow.id)}
                                        className="text-slate-500 hover:text-rose-400 p-1.5 rounded-lg hover:bg-rose-500/10 transition-colors"
                                        title="Delete Flow"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Modal: Create Flow */}
                {isCreateOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
                        <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-5 shadow-2xl">
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-bold text-slate-100">Create New Flow</h3>
                                <button
                                    onClick={() => setIsCreateOpen(false)}
                                    className="text-slate-400 hover:text-slate-200"
                                >
                                    ✕
                                </button>
                            </div>
                            <form onSubmit={handleCreate} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-medium text-slate-300 mb-1.5">
                                        Flow Name
                                    </label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="e.g. Lead Qualification Flow"
                                        className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500"
                                        required
                                    />
                                    {errors.name && (
                                        <p className="text-xs text-rose-400 mt-1">{errors.name}</p>
                                    )}
                                </div>

                                <div className="flex justify-end gap-3 pt-3 border-t border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setIsCreateOpen(false)}
                                        className="px-4 py-2 text-xs font-semibold text-slate-300 bg-slate-800 hover:bg-slate-700 rounded-lg transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 rounded-lg shadow-sm transition-colors"
                                    >
                                        {processing ? 'Creating...' : 'Create & Edit'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
