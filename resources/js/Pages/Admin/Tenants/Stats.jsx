import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function TenantStats({ auth, tenant, metrics }) {
    return (
        <AuthenticatedLayout
            user={auth?.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Stats for {tenant.name}</h2>}
        >
            <Head title={`Stats - ${tenant.name}`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    
                    <div className="mb-6 flex justify-between items-center">
                        <Link 
                            href="/admin/tenants"
                            className="text-indigo-600 hover:text-indigo-900 font-medium text-sm"
                        >
                            &larr; Back to Tenants
                        </Link>
                        <div className="text-sm text-gray-500">
                            Range: {metrics.date_range.from} to {metrics.date_range.to} ({metrics.date_range.timezone})
                        </div>
                    </div>

                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                        <div className="p-6 bg-white border-b border-gray-200">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Overview Totals</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <div className="text-sm text-gray-500 uppercase tracking-wide font-semibold">Total Messages</div>
                                    <div className="mt-2 text-3xl font-bold text-gray-900">{metrics.totals.total_messages}</div>
                                </div>
                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <div className="text-sm text-gray-500 uppercase tracking-wide font-semibold">Inbound</div>
                                    <div className="mt-2 text-3xl font-bold text-green-600">{metrics.totals.inbound_messages}</div>
                                </div>
                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <div className="text-sm text-gray-500 uppercase tracking-wide font-semibold">Outbound</div>
                                    <div className="mt-2 text-3xl font-bold text-blue-600">{metrics.totals.outbound_messages}</div>
                                </div>
                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <div className="text-sm text-gray-500 uppercase tracking-wide font-semibold">Conversations</div>
                                    <div className="mt-2 text-3xl font-bold text-purple-600">{metrics.totals.conversations}</div>
                                </div>
                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <div className="text-sm text-gray-500 uppercase tracking-wide font-semibold">Bot Triggers</div>
                                    <div className="mt-2 text-3xl font-bold text-yellow-600">{metrics.totals.active_triggers}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        {/* Delivery Status */}
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Delivery Status</h3>
                                <div className="space-y-4">
                                    {Object.entries(metrics.delivery_status).map(([status, count]) => (
                                        <div key={status} className="flex items-center justify-between">
                                            <span className="capitalize text-gray-600">{status}</span>
                                            <span className="font-semibold text-gray-900">{count}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Type Breakdown */}
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Message Types</h3>
                                <div className="space-y-4">
                                    {Object.entries(metrics.type_breakdown).map(([type, count]) => (
                                        <div key={type} className="flex items-center justify-between">
                                            <span className="capitalize text-gray-600">{type}</span>
                                            <span className="font-semibold text-gray-900">{count}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
