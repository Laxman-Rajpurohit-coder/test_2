import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';

export default function CampaignsShow({ campaign, recipients }) {
    const statusClasses = {
        'draft': 'bg-gray-100 text-gray-700',
        'scheduled': 'bg-indigo-50 text-indigo-700',
        'queued': 'bg-blue-50 text-blue-700',
        'sending': 'bg-amber-50 text-amber-700 animate-pulse',
        'completed': 'bg-emerald-50 text-[#00a884]',
        'failed': 'bg-rose-50 text-rose-700',
        'cancelled': 'bg-gray-200 text-gray-500 line-through',
    };

    return (
        <AppLayout>
            <Head title={`Campaign: ${campaign.name}`} />
            
            <div className="flex items-center justify-between mb-6">
                <div>
                    <Link href={route('campaigns.index')} className="text-sm font-bold text-gray-400 hover:text-gray-600 mb-1 inline-flex items-center gap-1">
                        &larr; Back to Campaigns
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-3">
                        {campaign.name}
                        <span className={`px-3 py-1 rounded-full text-xs font-bold uppercase ${statusClasses[campaign.status] || 'bg-gray-100'}`}>
                            {campaign.status}
                        </span>
                    </h1>
                </div>
                {(campaign.status === 'scheduled' || campaign.status === 'queued' || campaign.status === 'sending') && (
                    <Link
                        href={route('campaigns.cancel', campaign.id)}
                        method="post"
                        as="button"
                        className="px-4 py-2 bg-rose-500 text-white rounded-lg font-bold hover:bg-rose-600 shadow-sm transition"
                    >
                        Cancel Campaign
                    </Link>
                )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div className="bg-white rounded-xl shadow-sm border border-gray-200/60 p-5">
                    <div className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Recipients</div>
                    <div className="text-2xl font-black text-gray-900">{campaign.total_recipients}</div>
                </div>
                <div className="bg-white rounded-xl shadow-sm border border-gray-200/60 p-5">
                    <div className="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">Sent Successfully</div>
                    <div className="text-2xl font-black text-emerald-600">{campaign.sent_count}</div>
                </div>
                <div className="bg-white rounded-xl shadow-sm border border-gray-200/60 p-5">
                    <div className="text-xs font-bold text-rose-600 uppercase tracking-wider mb-1">Failed</div>
                    <div className="text-2xl font-black text-rose-600">{campaign.failed_count}</div>
                </div>
                <div className="bg-white rounded-xl shadow-sm border border-gray-200/60 p-5">
                    <div className="text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">Message Type</div>
                    <div className="text-xl font-black text-blue-600 capitalize">{campaign.message_type}</div>
                </div>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-gray-200/60 overflow-hidden">
                <div className="p-5 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                    <h3 className="font-bold text-gray-900">Delivery Status</h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50/50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Failure Reason</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {(recipients?.data || []).map((recipient) => (
                                <tr key={recipient.id} className="hover:bg-gray-50 transition">
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <div className="text-sm font-medium text-gray-900">
                                            {recipient.contact?.name || 'Unknown'}
                                        </div>
                                        <div className="text-sm text-gray-500">
                                            +{recipient.contact?.phone_number}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                            recipient.status === 'read' ? 'bg-emerald-100 text-emerald-800' :
                                            recipient.status === 'delivered' ? 'bg-blue-100 text-blue-800' :
                                            recipient.status === 'sent' ? 'bg-green-100 text-green-800' : 
                                            recipient.status === 'failed' ? 'bg-red-100 text-red-800' :
                                            recipient.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                            recipient.status === 'queued' ? 'bg-purple-100 text-purple-800' :
                                            'bg-gray-100 text-gray-800'
                                        }`}>
                                            {recipient.status}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-500">
                                        {recipient.failure_reason ? (
                                            <span className="text-rose-600 font-medium">
                                                {recipient.failure_reason}
                                            </span>
                                        ) : (
                                            <span className="text-gray-400">-</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {(recipients?.data?.length === 0) && (
                                <tr>
                                    <td colSpan="3" className="px-6 py-8 text-center text-gray-500">
                                        No recipients found for this campaign.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                {recipients?.links && recipients.data.length > 0 && (
                    <div className="p-4 border-t border-gray-100">
                        <Pagination links={recipients.links} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
