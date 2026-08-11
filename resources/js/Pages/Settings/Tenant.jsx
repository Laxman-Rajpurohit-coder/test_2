import React from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function TenantSettings({ settings, numbers, webhook }) {
    const { data, setData, post, processing, errors, reset, recentlySuccessful } = useForm({
        ai_provider: settings.ai_provider || 'openai',
        ai_model: settings.ai_model || '',
        ai_system_prompt: settings.ai_system_prompt || '',
        ai_is_active: settings.ai_is_active || false,
        ai_human_escalation_enabled: settings.ai_human_escalation_enabled ?? true,
        ai_confidence_threshold: settings.ai_confidence_threshold || 0.70,
    });

    const numberForm = useForm({
        country_code: '91',
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

                {/* AI Configuration Form */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-5">
                    <h2 className="text-base font-bold text-gray-900 border-b border-gray-100 pb-3">AI Bot Configuration</h2>

                    <form onSubmit={handleSaveKeys} className="space-y-4">
                        <div className="pt-2">
                            
                            <div className="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">AI Provider</label>
                                    <select
                                        value={data.ai_provider}
                                        onChange={(e) => setData('ai_provider', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                    >
                                        <option value="openai">OpenAI (Direct LLM)</option>
                                        <option value="grok">Grok (xAI)</option>
                                        <option value="gemini">Gemini (Google AI)</option>
                                        <option value="flowise">Flowise (LangChain/RAG)</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">AI Model Name</label>
                                    <input
                                        type="text"
                                        value={data.ai_model}
                                        onChange={(e) => setData('ai_model', e.target.value)}
                                        placeholder={
                                            data.ai_provider === 'openai' ? 'gpt-4o-mini' : 
                                            data.ai_provider === 'grok' ? 'grok-2-mini' : 
                                            data.ai_provider === 'gemini' ? 'gemini-3.1-flash-lite' : 
                                            'model-name'
                                        }
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
                                    className={`w-full px-3 py-2 border rounded-xl text-xs outline-none transition-colors ${
                                        errors.ai_confidence_threshold 
                                            ? 'border-rose-500 focus:ring-rose-200' 
                                            : 'border-gray-200 focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884]'
                                    }`}
                                />
                                {errors.ai_confidence_threshold && <p className="text-[10px] text-rose-500 mt-1 font-semibold">{errors.ai_confidence_threshold}</p>}
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
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex items-center gap-2 px-5 py-2.5 bg-[#00a884] hover:bg-[#008f70] text-white rounded-xl text-xs font-semibold transition shadow-md shadow-emerald-500/20 disabled:opacity-50"
                            >
                                {processing && (
                                    <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                )}
                                {processing ? 'Saving...' : 'Save API Credentials'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* WhatsApp Numbers Registration */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-5">
                    <h2 className="text-base font-bold text-gray-900 border-b border-gray-100 pb-3">Registered WhatsApp Integrated Numbers</h2>

                    <form onSubmit={handleAddNumber} className="flex gap-3 items-start">
                        <div className="flex-none w-36">
                            <select
                                value={numberForm.data.country_code}
                                onChange={(e) => numberForm.setData('country_code', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-xl text-xs outline-none transition-colors border-gray-200 focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] bg-white`}
                            >
                                <option value="91">+91 (India)</option>
                                <option value="1">+1 (US/Canada)</option>
                                <option value="44">+44 (UK)</option>
                                <option value="61">+61 (Australia)</option>
                                <option value="">None (Raw)</option>
                            </select>
                        </div>
                        <div className="flex-1">
                            <input
                                type="text"
                                value={numberForm.data.integrated_number}
                                onChange={(e) => numberForm.setData('integrated_number', e.target.value)}
                                placeholder="e.g. 917425889008"
                                className={`w-full px-3 py-2 border rounded-xl text-xs outline-none transition-colors ${
                                    numberForm.errors.integrated_number 
                                        ? 'border-rose-500 focus:ring-rose-200' 
                                        : 'border-gray-200 focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884]'
                                }`}
                                required
                            />
                            {numberForm.errors.integrated_number && (
                                <p className="text-[10px] text-rose-500 mt-1 font-semibold">{numberForm.errors.integrated_number}</p>
                            )}
                        </div>
                        <button
                            type="submit"
                            disabled={numberForm.processing}
                            className="flex items-center gap-2 px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50"
                        >
                            {numberForm.processing && (
                                <svg className="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            )}
                            + Add Number
                        </button>
                    </form>

                    <div className="divide-y divide-gray-100 border border-gray-100 rounded-xl overflow-hidden">
                        {numbers.length === 0 ? (
                            <div className="p-4 text-center text-xs text-gray-400">No WhatsApp numbers registered yet.</div>
                        ) : (
                            numbers.map((num) => (
                                <div key={num.id} className="p-3.5 flex items-center justify-between text-xs font-semibold text-gray-800 bg-gray-50/50">
                                    <div className="flex items-center gap-3">
                                        <span className="font-mono">📱 +{num.integrated_number}</span>
                                        <span className="px-2 py-0.5 bg-emerald-50 text-[#00a884] rounded-md text-[10px] font-bold">Active</span>
                                    </div>
                                    <button 
                                        type="button"
                                        onClick={() => {
                                            if(confirm('Are you sure you want to remove this number?')) {
                                                router.delete(route('settings.tenant.numbers.destroy', num.integrated_number));
                                            }
                                        }}
                                        className="px-2 py-1 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-md text-[10px] font-bold transition-colors"
                                    >
                                        Remove
                                    </button>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
