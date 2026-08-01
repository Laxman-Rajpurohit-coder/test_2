import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function TenantSettings({ settings, numbers, webhook }) {
    const { data, setData, post, processing, errors, reset, recentlySuccessful } = useForm({
        msg91_auth_key: settings.msg91_auth_key || '',
        openai_api_key: settings.openai_api_key || '',
        flowise_endpoint: settings.flowise_endpoint || '',
        ai_provider: settings.ai_provider || 'openai',
        ai_model: settings.ai_model || '',
        ai_system_prompt: settings.ai_system_prompt || '',
        ai_is_active: settings.ai_is_active || false,
        ai_human_escalation_enabled: settings.ai_human_escalation_enabled ?? true,
        ai_confidence_threshold: settings.ai_confidence_threshold || 0.70,
    });

    const numberForm = useForm({
        integrated_number: '',
    });

    const handleSaveKeys = (e) => {
        e.preventDefault();
        post(route('settings.tenant.update'), { preserveScroll: true });
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

                {/* Webhook Info */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-4">
                    <h2 className="text-base font-bold text-gray-900 border-b border-gray-100 pb-3">Webhook Configuration</h2>
                    <p className="text-xs text-gray-500">Copy this URL and Secret into your MSG91 dashboard to receive inbound messages and delivery receipts.</p>
                    <div className="space-y-3">
                        <div>
                            <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Webhook URL</label>
                            <div className="flex items-center">
                                <code className="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-700 font-mono break-all select-all">
                                    {webhook?.url}
                                </code>
                            </div>
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Webhook Secret (Header: X-MSG91-Secret)</label>
                            <div className="flex items-center">
                                <code className="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-700 font-mono select-all">
                                    {webhook?.secret || 'Not configured on server'}
                                </code>
                            </div>
                        </div>
                    </div>
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

                        <div className="pt-4 mt-4 border-t border-gray-100">
                            <h3 className="text-sm font-bold text-gray-900 mb-3">AI Bot Fallback Configuration</h3>
                            
                            <div className="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">AI Provider</label>
                                    <select
                                        value={data.ai_provider}
                                        onChange={(e) => setData('ai_provider', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                    >
                                        <option value="openai">OpenAI (Direct LLM)</option>
                                        <option value="flowise">Flowise (LangChain/RAG)</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">AI Model Name</label>
                                    <input
                                        type="text"
                                        value={data.ai_model}
                                        onChange={(e) => setData('ai_model', e.target.value)}
                                        placeholder="e.g. gpt-4o-mini"
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                    />
                                </div>
                            </div>

                            <div className="mb-4">
                                <label className="block text-xs font-bold text-gray-700 mb-1">System Prompt Context</label>
                                <textarea
                                    value={data.ai_system_prompt}
                                    onChange={(e) => setData('ai_system_prompt', e.target.value)}
                                    placeholder="You are a helpful customer service assistant for our company..."
                                    rows="4"
                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                ></textarea>
                            </div>

                            <div className="mb-4">
                                <label className="block text-xs font-bold text-gray-700 mb-1">
                                    AI Confidence Threshold (0.0 to 1.0)
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="1"
                                    value={data.ai_confidence_threshold}
                                    onChange={(e) => setData('ai_confidence_threshold', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                />
                                <p className="text-[10px] text-gray-400 mt-1">If the model scores below this confidence level, it will not answer.</p>
                            </div>

                            <div className="flex gap-6 mt-2">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.ai_is_active}
                                        onChange={(e) => setData('ai_is_active', e.target.checked)}
                                        className="rounded border-gray-300 text-[#00a884] focus:ring-[#00a884]"
                                    />
                                    <span className="text-xs font-bold text-gray-700">Enable AI Fallback Bot</span>
                                </label>

                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.ai_human_escalation_enabled}
                                        onChange={(e) => setData('ai_human_escalation_enabled', e.target.checked)}
                                        className="rounded border-gray-300 text-[#00a884] focus:ring-[#00a884]"
                                    />
                                    <span className="text-xs font-bold text-gray-700">Escalate to Human on Low Confidence</span>
                                </label>
                            </div>
                        </div>

                        <div className="flex justify-end pt-3 items-center gap-4">
                            <span className={`text-sm font-semibold text-emerald-600 transition-opacity duration-300 ${recentlySuccessful ? 'opacity-100' : 'opacity-0'}`}>
                                Saved successfully.
                            </span>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-5 py-2.5 bg-[#00a884] hover:bg-[#008f70] text-white rounded-xl text-xs font-semibold transition shadow-md shadow-emerald-500/20 disabled:opacity-50"
                            >
                                {processing ? 'Saving...' : 'Save API Credentials'}
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
