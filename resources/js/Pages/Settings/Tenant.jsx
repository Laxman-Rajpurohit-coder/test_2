import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function TenantSettings({ settings, numbers }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        msg91_auth_key: settings.msg91_auth_key || '',
        openai_api_key: settings.openai_api_key || '',
        flowise_endpoint: settings.flowise_endpoint || '',
    });

    const numberForm = useForm({
        integrated_number: '',
    });

    const handleSaveKeys = (e) => {
        e.preventDefault();
        post(route('settings.tenant.update'));
    };

    const handleAddNumber = (e) => {
        e.preventDefault();
        numberForm.post(route('settings.tenant.numbers.store'), {
            onSuccess: () => numberForm.reset(),
        });
    };

    return (
        <AppLayout>
            <Head title="Tenant API Settings" />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Header */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs">
                    <h1 className="text-xl font-extrabold text-gray-900 tracking-tight flex items-center gap-2">
                        <span>🔑</span> Tenant API & Integration Settings
                    </h1>
                    <p className="text-xs text-gray-500 mt-1">
                        Configure custom MSG91, OpenAI, and Flowise credentials for your organization. Keys are encrypted at rest.
                    </p>
                </div>

                {/* API Keys Form */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-5">
                    <h2 className="text-base font-bold text-gray-900 border-b border-gray-100 pb-3">API Credentials</h2>

                    <form onSubmit={handleSaveKeys} className="space-y-4">
                        <div>
                            <label className="block text-xs font-bold text-gray-700 mb-1">MSG91 Auth Key</label>
                            <input
                                type="password"
                                value={data.msg91_auth_key}
                                onChange={(e) => setData('msg91_auth_key', e.target.value)}
                                placeholder="Enter custom MSG91 Auth Key"
                                className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-gray-700 mb-1">OpenAI API Key (BYO Key)</label>
                            <input
                                type="password"
                                value={data.openai_api_key}
                                onChange={(e) => setData('openai_api_key', e.target.value)}
                                placeholder="sk-proj-..."
                                className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-gray-700 mb-1">Flowise AI Endpoint URL</label>
                            <input
                                type="url"
                                value={data.flowise_endpoint}
                                onChange={(e) => setData('flowise_endpoint', e.target.value)}
                                placeholder="https://flowise.yourdomain.com/api/v1/prediction/..."
                                className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                            />
                        </div>

                        <div className="flex justify-end pt-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-5 py-2.5 bg-[#00a884] hover:bg-[#008f70] text-white rounded-xl text-xs font-semibold transition shadow-md shadow-emerald-500/20 disabled:opacity-50"
                            >
                                Save API Credentials
                            </button>
                        </div>
                    </form>
                </div>

                {/* WhatsApp Numbers Registration */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-5">
                    <h2 className="text-base font-bold text-gray-900 border-b border-gray-100 pb-3">Registered WhatsApp Integrated Numbers</h2>

                    <form onSubmit={handleAddNumber} className="flex gap-3">
                        <input
                            type="text"
                            value={numberForm.data.integrated_number}
                            onChange={(e) => numberForm.setData('integrated_number', e.target.value)}
                            placeholder="e.g. 917425889008"
                            className="flex-1 px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                            required
                        />
                        <button
                            type="submit"
                            disabled={numberForm.processing}
                            className="px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white rounded-xl text-xs font-semibold transition"
                        >
                            + Add Number
                        </button>
                    </form>

                    <div className="divide-y divide-gray-100 border border-gray-100 rounded-xl overflow-hidden">
                        {numbers.length === 0 ? (
                            <div className="p-4 text-center text-xs text-gray-400">No WhatsApp numbers registered yet.</div>
                        ) : (
                            numbers.map((num) => (
                                <div key={num.id} className="p-3.5 flex items-center justify-between text-xs font-semibold text-gray-800 bg-gray-50/50">
                                    <span className="font-mono">📱 +{num.integrated_number}</span>
                                    <span className="px-2 py-0.5 bg-emerald-50 text-[#00a884] rounded-md text-[10px] font-bold">Active</span>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
