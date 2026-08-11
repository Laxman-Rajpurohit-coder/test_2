import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Settings({ setting }) {
    const { data, setData, put, processing, errors } = useForm({
        provider: setting.provider || 'openai',
        api_key: '',
        model_or_chatflow_id: setting.model_or_chatflow_id || 'gpt-4o-mini',
        system_prompt: setting.system_prompt || 'You are a helpful customer support assistant for WhatsApp.',
        is_active: setting.is_active ?? false,
        human_escalation_enabled: setting.human_escalation_enabled ?? true,
    });

    const [successMsg, setSuccessMsg] = useState(null);

    const handleSubmit = (e) => {
        e.preventDefault();
        setSuccessMsg(null);
        put(route('ai-bot.update'), {
            onSuccess: () => {
                setSuccessMsg('AI Bot settings saved successfully.');
                setTimeout(() => setSuccessMsg(null), 4000);
            }
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-slate-100">
                            AI Bot Agent Settings
                        </h2>
                        <p className="text-sm text-slate-400">
                            Configure LLM automation (OpenAI / Flowise) for fallback customer messaging (Priority 90)
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setData('is_active', !data.is_active)}
                            className={`px-3 py-1.5 text-xs font-semibold rounded-full border transition-all ${
                                data.is_active
                                    ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
                                    : 'bg-slate-800 text-slate-400 border-slate-700'
                            }`}
                        >
                            {data.is_active ? '🤖 Bot Active' : '⏸️ Bot Disabled'}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="AI Bot Settings" />

            <div className="py-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                {successMsg && (
                    <div className="mb-6 bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-xl text-sm font-medium">
                        ✓ {successMsg}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-6 shadow-xl">
                    {/* Provider Select */}
                    <div>
                        <label className="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">
                            AI Provider Engine
                        </label>
                        <div className="grid grid-cols-3 gap-4">
                            <button
                                type="button"
                                onClick={() => setData('provider', 'openai')}
                                className={`p-4 rounded-xl border text-left transition-all ${
                                    data.provider === 'openai'
                                        ? 'bg-indigo-500/10 border-indigo-500/50 ring-2 ring-indigo-500/20'
                                        : 'bg-slate-950 border-slate-800 hover:border-slate-700'
                                }`}
                            >
                                <div className="text-sm font-bold text-slate-100 mb-1">OpenAI API</div>
                                <div className="text-xs text-slate-400">Direct integration with GPT-4o, GPT-4o-mini, or custom models</div>
                                <div className="text-sm font-bold text-slate-100 mb-1">OpenAI</div>
                                <div className="text-xs text-slate-400">GPT-4o, GPT-4o-mini</div>
                            </button>

                            <button
                                type="button"
                                onClick={() => setData('provider', 'flowise')}
                                className={`p-4 rounded-xl border text-left transition-all ${
                                    data.provider === 'flowise'
                                        ? 'bg-sky-500/10 border-sky-500/50 ring-2 ring-sky-500/20'
                                        : 'bg-slate-950 border-slate-800 hover:border-slate-700'
                                }`}
                            >
                                <div className="text-sm font-bold text-slate-100 mb-1">Flowise</div>
                                <div className="text-xs text-slate-400">Custom workflows</div>
                            </button>

                            <button
                                type="button"
                                onClick={() => setData('provider', 'grok')}
                                className={`p-4 rounded-xl border text-left transition-all ${
                                    data.provider === 'grok'
                                        ? 'bg-emerald-500/10 border-emerald-500/50 ring-2 ring-emerald-500/20'
                                        : 'bg-slate-950 border-slate-800 hover:border-slate-700'
                                }`}
                            >
                                <div className="text-sm font-bold text-slate-100 mb-1">Grok</div>
                                <div className="text-xs text-slate-400">Grok API</div>
                            </button>

                            <button
                                type="button"
                                onClick={() => setData('provider', 'gemini')}
                                className={`p-4 rounded-xl border text-left transition-all ${
                                    data.provider === 'gemini'
                                        ? 'bg-purple-500/10 border-purple-500/50 ring-2 ring-purple-500/20'
                                        : 'bg-slate-950 border-slate-800 hover:border-slate-700'
                                }`}
                            >
                                <div className="text-sm font-bold text-slate-100 mb-1">Gemini</div>
                                <div className="text-xs text-slate-400">Google Gemini</div>
                            </button>
                        </div>
                    </div>

                    {/* API Key */}
                    <div>
                        <div className="flex items-center justify-between mb-1.5">
                            <label className="block text-xs font-medium text-slate-300">
                                {data.provider === 'openai' ? 'OpenAI Secret API Key' : data.provider === 'grok' ? 'Grok API Key' : data.provider === 'gemini' ? 'Gemini API Key' : 'Flowise API Key'}
                            </label>
                            {setting.api_key_configured && (
                                <span className="text-[11px] text-emerald-400 font-semibold">
                                    ✓ Secret Key Encrypted & Configured
                                </span>
                            )}
                        </div>
                        <input
                            type="password"
                            value={data.api_key}
                            onChange={(e) => setData('api_key', e.target.value)}
                            placeholder={setting.api_key_configured ? '••••••••••••••••••••••••••••' : 'sk-...'}
                            className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-indigo-500"
                        />
                        <p className="text-[11px] text-slate-500 mt-1">
                            Keys are strongly encrypted using AES-256-GCM before storage in database.
                        </p>
                        {errors.api_key && <p className="text-xs text-rose-400 mt-1">{errors.api_key}</p>}
                    </div>

                    {/* Model or Chatflow ID */}
                    <div>
                        <label className="block text-xs font-medium text-slate-300 mb-1.5">
                            {data.provider === 'openai' ? 'OpenAI Model Identifier' : data.provider === 'grok' ? 'Grok Model Identifier' : data.provider === 'gemini' ? 'Gemini Model Identifier' : 'Flowise Chatflow UUID'}
                        </label>
                        <input
                            type="text"
                            value={data.model_or_chatflow_id}
                            onChange={(e) => setData('model_or_chatflow_id', e.target.value)}
                            placeholder={data.provider === 'openai' ? 'gpt-4o-mini' : data.provider === 'grok' ? 'grok-beta' : data.provider === 'gemini' ? 'gemini-3.1-flash-lite' : '4b21c43f...'}
                            className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-indigo-500 font-mono"
                            required
                        />
                        {errors.model_or_chatflow_id && <p className="text-xs text-rose-400 mt-1">{errors.model_or_chatflow_id}</p>}
                    </div>

                    {/* System Prompt */}
                    {(data.provider === 'openai' || data.provider === 'grok' || data.provider === 'gemini') && (
                        <div>
                            <label className="block text-xs font-medium text-slate-300 mb-1.5">
                                System Persona & Instruction Prompt
                            </label>
                            <textarea
                                rows={4}
                                value={data.system_prompt}
                                onChange={(e) => setData('system_prompt', e.target.value)}
                                placeholder="You are a helpful customer support agent..."
                                className="w-full bg-slate-950 border border-slate-800 rounded-lg p-3 text-sm text-slate-200 focus:outline-none focus:border-indigo-500"
                            />
                            {errors.system_prompt && <p className="text-xs text-rose-400 mt-1">{errors.system_prompt}</p>}
                        </div>
                    )}

                    {/* Human Escalation Guard Toggle */}
                    <div className="pt-4 border-t border-slate-800 flex items-center justify-between">
                        <div>
                            <h4 className="text-sm font-semibold text-slate-200">Human Escalation Guard</h4>
                            <p className="text-xs text-slate-400">
                                Automatically pause AI bot and notify human agents when customer types "agent", "human", or "support"
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setData('human_escalation_enabled', !data.human_escalation_enabled)}
                            className={`w-11 h-6 flex items-center rounded-full p-1 transition-all ${
                                data.human_escalation_enabled ? 'bg-indigo-600' : 'bg-slate-800'
                            }`}
                        >
                            <div
                                className={`w-4 h-4 rounded-full bg-white transition-all transform ${
                                    data.human_escalation_enabled ? 'translate-x-5' : 'translate-x-0'
                                }`}
                            />
                        </button>
                    </div>

                    {/* Submit Button */}
                    <div className="pt-4 border-t border-slate-800 flex justify-end">
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-6 py-2.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 rounded-lg shadow-lg shadow-indigo-500/20 transition-all"
                        >
                            {processing ? 'Saving Settings...' : 'Save AI Bot Configuration'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
