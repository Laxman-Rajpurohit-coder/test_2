import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function CampaignsShow({ campaign }) {
    // Determine overall status classes
    const statusClasses = {
        'draft': 'bg-gray-100 text-gray-700',
        'queued': 'bg-blue-50 text-blue-700',
        'sending': 'bg-amber-50 text-amber-700 animate-pulse',
        'completed': 'bg-emerald-50 text-[#00a884]',
        'failed': 'bg-rose-50 text-rose-700',
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
                <div className="p-8 text-center text-gray-500">
                    <p>Recipient tracking table will be implemented in a future update.</p>
                    <p className="text-sm mt-2">Currently, <strong>{campaign.failed_count}</strong> messages failed or were <span className="font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded">skipped_24h</span> (outside the active 24h WhatsApp session window).</p>
                </div>
            </div>
        </AppLayout>
    );
}
