import React, { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function CampaignsIndex({ campaigns, approvedTemplates }) {
    const [createModalOpen, setCreateModalOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        message_type: 'text',
        template_name: '',
        template_language: 'en',
        template_variable_map: [],
        text_content: '',
        target_type: 'all',
        target_id: '',
    });

    const addVariableMap = () => {
        setData('template_variable_map', [...data.template_variable_map, '']);
    };

    const updateVariableMap = (index, value) => {
        const newMap = [...data.template_variable_map];
        newMap[index] = value;
        setData('template_variable_map', newMap);
    };

    const removeVariableMap = (index) => {
        const newMap = [...data.template_variable_map];
        newMap.splice(index, 1);
        setData('template_variable_map', newMap);
    };

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
                <Link 
                    href={route('campaigns.create')}
                    className="px-4 py-2 bg-[#00a884] text-white rounded-lg font-bold hover:bg-[#009071] shadow-sm transition"
                >
                    New Campaign
                </Link>
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
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-right flex justify-end gap-3 items-center">
                                    {(campaign.status === 'scheduled' || campaign.status === 'queued' || campaign.status === 'sending') && (
                                        <Link
                                            href={route('campaigns.cancel', campaign.id)}
                                            method="post"
                                            as="button"
                                            className="text-rose-500 font-bold hover:text-rose-700 transition"
                                        >
                                            Cancel
                                        </Link>
                                    )}
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

        </AppLayout>
    );
}
