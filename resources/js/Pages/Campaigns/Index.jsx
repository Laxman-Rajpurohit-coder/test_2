import React, { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function CampaignsIndex({ campaigns }) {
    const [createModalOpen, setCreateModalOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        message_type: 'text',
        template_name: '',
        template_language: 'en',
        text_content: '',
        target_type: 'all',
        target_id: '',
    });

    const handleCreate = (e) => {
        e.preventDefault();
        post(route('campaigns.store'), {
            onSuccess: () => {
                setCreateModalOpen(false);
                reset();
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Campaigns" />
            
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Campaigns</h1>
                    <p className="text-sm text-gray-500 mt-1">Send bulk WhatsApp messages to your contacts.</p>
                </div>
                <button 
                    onClick={() => setCreateModalOpen(true)}
                    className="px-4 py-2 bg-[#00a884] text-white rounded-lg font-bold hover:bg-[#009071] shadow-sm transition"
                >
                    New Campaign
                </button>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-gray-200/60 overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50/50">
                        <tr>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Name</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Type</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Target</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Progress</th>
                            <th className="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {campaigns && campaigns.data && campaigns.data.map((campaign) => (
                            <tr key={campaign.id} className="hover:bg-gray-50 transition">
                                <td className="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                    {campaign.name}
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <span className={`px-2.5 py-1 rounded-full text-xs font-bold ${campaign.message_type === 'template' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700'}`}>
                                        {campaign.message_type === 'template' ? 'Template' : 'Free Text'}
                                    </span>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600 capitalize">
                                    {campaign.target_type}
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <span className={`px-2.5 py-1 rounded-full text-xs font-bold ${
                                        campaign.status === 'completed' ? 'bg-emerald-50 text-[#00a884]' :
                                        campaign.status === 'failed' ? 'bg-rose-50 text-rose-700' :
                                        campaign.status === 'sending' ? 'bg-amber-50 text-amber-700 animate-pulse' :
                                        'bg-gray-100 text-gray-700'
                                    }`}>
                                        {campaign.status.toUpperCase()}
                                    </span>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <div className="flex flex-col gap-1">
                                        <div className="text-xs font-semibold">
                                            {campaign.sent_count} / {campaign.total_recipients} Sent
                                        </div>
                                        <div className="w-full bg-gray-200 rounded-full h-1.5">
                                            <div 
                                                className="bg-[#00a884] h-1.5 rounded-full" 
                                                style={{ width: `${campaign.total_recipients > 0 ? (campaign.sent_count / campaign.total_recipients) * 100 : 0}%` }}
                                            ></div>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <Link 
                                        href={route('campaigns.show', campaign.id)}
                                        className="text-[#00a884] font-bold hover:text-[#009071] transition"
                                    >
                                        View Details
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Create Campaign Modal */}
            {createModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" onClick={() => setCreateModalOpen(false)}></div>
                    <div className="relative bg-white rounded-2xl p-6 w-full max-w-lg shadow-xl border border-gray-100 overflow-y-auto max-h-[90vh]">
                        <h2 className="text-xl font-bold text-gray-900 mb-6">New Campaign</h2>
                        
                        <form onSubmit={handleCreate} className="space-y-4">
                            <div>
                                <label className="block text-sm font-bold text-gray-700 mb-1">Campaign Name</label>
                                <input 
                                    type="text" 
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-[#00a884] focus:border-[#00a884]"
                                    placeholder="e.g. Summer Promo"
                                />
                                {errors.name && <div className="text-rose-600 text-xs mt-1 font-semibold">{errors.name}</div>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-1">Message Type</label>
                                    <select 
                                        value={data.message_type}
                                        onChange={e => setData('message_type', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-[#00a884] focus:border-[#00a884]"
                                    >
                                        <option value="text">Free Text (24h window only)</option>
                                        <option value="template">WhatsApp Template</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-1">Target Audience</label>
                                    <select 
                                        value={data.target_type}
                                        onChange={e => setData('target_type', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-[#00a884] focus:border-[#00a884]"
                                    >
                                        <option value="all">All Contacts</option>
                                        <option value="group">Specific Group</option>
                                        <option value="tag">Specific Tag</option>
                                    </select>
                                </div>
                            </div>

                            {data.target_type !== 'all' && (
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-1">Target ID</label>
                                    <input 
                                        type="text" 
                                        value={data.target_id}
                                        onChange={e => setData('target_id', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-[#00a884] focus:border-[#00a884]"
                                        placeholder={`UUID of the ${data.target_type}`}
                                    />
                                    {errors.target_id && <div className="text-rose-600 text-xs mt-1 font-semibold">{errors.target_id}</div>}
                                </div>
                            )}

                            {data.message_type === 'text' ? (
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-1">Message Content</label>
                                    <textarea 
                                        value={data.text_content}
                                        onChange={e => setData('text_content', e.target.value)}
                                        rows="4"
                                        className="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-[#00a884] focus:border-[#00a884]"
                                        placeholder="Type your message here..."
                                    ></textarea>
                                    {errors.text_content && <div className="text-rose-600 text-xs mt-1 font-semibold">{errors.text_content}</div>}
                                    <p className="text-xs text-amber-600 mt-1 font-semibold">Note: This will only be sent to contacts who have messaged you in the last 24 hours.</p>
                                </div>
                            ) : (
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-bold text-gray-700 mb-1">Template Name</label>
                                        <input 
                                            type="text" 
                                            value={data.template_name}
                                            onChange={e => setData('template_name', e.target.value)}
                                            className="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-[#00a884] focus:border-[#00a884]"
                                        />
                                        {errors.template_name && <div className="text-rose-600 text-xs mt-1 font-semibold">{errors.template_name}</div>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-bold text-gray-700 mb-1">Language</label>
                                        <input 
                                            type="text" 
                                            value={data.template_language}
                                            onChange={e => setData('template_language', e.target.value)}
                                            className="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-[#00a884] focus:border-[#00a884]"
                                            placeholder="e.g. en"
                                        />
                                        {errors.template_language && <div className="text-rose-600 text-xs mt-1 font-semibold">{errors.template_language}</div>}
                                    </div>
                                </div>
                            )}
                            
                            <div className="flex justify-end gap-2 mt-6">
                                <button
                                    type="button"
                                    onClick={() => setCreateModalOpen(false)}
                                    className="px-4 py-2 text-sm font-bold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 text-sm font-bold text-white bg-[#00a884] rounded-lg hover:bg-[#009071] transition disabled:opacity-50"
                                >
                                    {processing ? 'Queuing...' : 'Create & Send Campaign'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
