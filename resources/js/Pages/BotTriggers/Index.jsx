import React, { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function BotTriggersIndex({ triggers }) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingTrigger, setEditingTrigger] = useState(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        keyword: '',
        match_type: 'contains',
        response_type: 'text',
        response_payload: { text: '', url: '', caption: '', filename: '', buttons: [] },
        priority: 0,
        is_active: true,
    });

    const openCreateModal = () => {
        setEditingTrigger(null);
        reset();
        setIsModalOpen(true);
    };

    const openEditModal = (trigger) => {
        setEditingTrigger(trigger);
        const payload = trigger.response_payload || {};
        setData({
            keyword: trigger.keyword,
            match_type: trigger.match_type,
            response_type: trigger.response_type,
            response_payload: {
                text: payload.text || payload.content || '',
                url: payload.url || payload.link || '',
                caption: payload.caption || '',
                filename: payload.filename || 'document.pdf',
                buttons: payload.buttons || [],
            },
            priority: trigger.priority,
            is_active: Boolean(trigger.is_active),
        });
        setIsModalOpen(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingTrigger) {
            put(route('bot-triggers.update', editingTrigger.id), {
                onSuccess: () => {
                    setIsModalOpen(false);
                    reset();
                },
            });
        } else {
            post(route('bot-triggers.store'), {
                onSuccess: () => {
                    setIsModalOpen(false);
                    reset();
                },
            });
        }
    };

    const toggleActive = (id) => {
        router.patch(route('bot-triggers.toggle', id));
    };

    const handleDelete = (id) => {
        if (confirm('Are you sure you want to delete this bot rule?')) {
            router.delete(route('bot-triggers.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Bot Auto-Responder Rules" />

            <div className="space-y-6">
                {/* Header Title Section */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs">
                    <div>
                        <h1 className="text-xl font-extrabold text-gray-900 tracking-tight flex items-center gap-2">
                            <span>🤖</span> Bot Auto-Responder Rules
                        </h1>
                        <p className="text-xs text-gray-500 mt-1">
                            Configure keyword triggers to automatically answer incoming customer WhatsApp messages.
                        </p>
                    </div>

                    <button
                        onClick={openCreateModal}
                        className="inline-flex items-center gap-2 bg-[#00a884] hover:bg-[#008f70] text-white px-4 py-2.5 rounded-xl font-semibold text-xs transition shadow-md shadow-emerald-500/20"
                    >
                        <span>➕</span> Create New Trigger Rule
                    </button>
                </div>

                {/* Triggers Table / List */}
                <div className="bg-white rounded-2xl border border-gray-200/80 shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs text-gray-600">
                            <thead className="bg-gray-50/80 text-gray-500 uppercase tracking-wider font-bold text-[10px] border-b border-gray-100">
                                <tr>
                                    <th className="px-6 py-4">Keyword Trigger</th>
                                    <th className="px-6 py-4">Match Type</th>
                                    <th className="px-6 py-4">Priority</th>
                                    <th className="px-6 py-4">Response Type</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 font-medium">
                                {triggers.length === 0 ? (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-12 text-center text-gray-400">
                                            No automated triggers created yet. Click <span className="font-bold text-gray-700">Create New Trigger Rule</span> to get started.
                                        </td>
                                    </tr>
                                ) : (
                                    triggers.map((trigger) => (
                                        <tr key={trigger.id} className="hover:bg-gray-50/50 transition">
                                            <td className="px-6 py-4 font-bold text-gray-900 flex items-center gap-2">
                                                <span className="px-2.5 py-1 bg-emerald-50 text-[#00a884] rounded-lg border border-emerald-200/60 font-mono">
                                                    "{trigger.keyword}"
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 capitalize">
                                                <span className="px-2 py-0.5 bg-gray-100 text-gray-700 rounded-md font-semibold text-[11px]">
                                                    {trigger.match_type.replace('_', ' ')}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 font-bold text-gray-800">
                                                <span className="px-2 py-0.5 bg-amber-50 text-amber-700 rounded-md font-mono border border-amber-200/60">
                                                    P-{trigger.priority}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 capitalize font-semibold text-gray-700">
                                                {trigger.response_type === 'text' && '💬 Text'}
                                                {trigger.response_type === 'interactive' && '👆 Interactive'}
                                                {trigger.response_type === 'image' && '🖼️ Image'}
                                                {trigger.response_type === 'document' && '📄 Document'}
                                            </td>
                                            <td className="px-6 py-4">
                                                <button
                                                    onClick={() => toggleActive(trigger.id)}
                                                    className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold transition ${
                                                        trigger.is_active
                                                            ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'
                                                            : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                                                    }`}
                                                >
                                                    <span className={`w-2 h-2 rounded-full ${trigger.is_active ? 'bg-emerald-500' : 'bg-gray-400'}`}></span>
                                                    {trigger.is_active ? 'Active' : 'Disabled'}
                                                </button>
                                            </td>
                                            <td className="px-6 py-4 text-right space-x-2">
                                                <button
                                                    onClick={() => openEditModal(trigger)}
                                                    className="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold transition"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(trigger.id)}
                                                    className="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-xs font-semibold transition border border-rose-200/60"
                                                >
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Create / Edit Modal */}
                {isModalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div className="fixed inset-0 bg-gray-900/50 backdrop-blur-xs" onClick={() => setIsModalOpen(false)}></div>

                        <div className="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 z-10 space-y-5 border border-gray-100">
                            <div className="flex items-center justify-between border-b border-gray-100 pb-3">
                                <h3 className="text-base font-bold text-gray-900">
                                    {editingTrigger ? 'Edit Bot Trigger Rule' : 'Create Bot Trigger Rule'}
                                </h3>
                                <button onClick={() => setIsModalOpen(false)} className="text-gray-400 hover:text-gray-600 font-bold">✕</button>
                            </div>

                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <label htmlFor="trigger_keyword" className="block text-xs font-bold text-gray-700 mb-1">Keyword</label>
                                    <input
                                        type="text"
                                        id="trigger_keyword"
                                        name="keyword"
                                        autoComplete="off"
                                        value={data.keyword}
                                        onChange={(e) => setData('keyword', e.target.value)}
                                        placeholder="e.g. HELP, PRICING, MENU"
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                        required
                                    />
                                    {errors.keyword && <p className="text-rose-500 text-[10px] mt-1">{errors.keyword}</p>}
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label htmlFor="match_type" className="block text-xs font-bold text-gray-700 mb-1">Match Type</label>
                                        <select
                                            id="match_type"
                                            name="match_type"
                                            value={data.match_type}
                                            onChange={(e) => setData('match_type', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                        >
                                            <option value="contains">Contains Keyword</option>
                                            <option value="exact">Exact Match</option>
                                            <option value="starts_with">Starts With</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label htmlFor="rule_priority" className="block text-xs font-bold text-gray-700 mb-1">Priority (Higher Wins)</label>
                                        <input
                                            type="number"
                                            id="rule_priority"
                                            name="priority"
                                            value={data.priority}
                                            onChange={(e) => setData('priority', parseInt(e.target.value) || 0)}
                                            className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                            min="0"
                                            max="999"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label htmlFor="response_type" className="block text-xs font-bold text-gray-700 mb-1">Response Type</label>
                                    <select
                                        id="response_type"
                                        name="response_type"
                                        value={data.response_type}
                                        onChange={(e) => setData('response_type', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                    >
                                        <option value="text">Text Response</option>
                                        <option value="interactive">Interactive (Buttons)</option>
                                        <option value="image">Image Response</option>
                                        <option value="document">Document PDF Response</option>
                                    </select>
                                </div>

                                {['text', 'interactive'].includes(data.response_type) && (
                                    <div className="space-y-4">
                                        <div>
                                            <label htmlFor="response_text" className="block text-xs font-bold text-gray-700 mb-1">
                                                Reply Message (Supports {'{customer_name}'}, {'{phone_number}'})
                                            </label>
                                            <textarea
                                                id="response_text"
                                                name="response_text"
                                                rows="3"
                                                value={data.response_payload.text}
                                                onChange={(e) => setData('response_payload', { ...data.response_payload, text: e.target.value })}
                                                placeholder="Hello {customer_name}! Welcome to our support..."
                                                className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                                required
                                            ></textarea>
                                            {errors['response_payload.text'] && <p className="text-rose-500 text-[10px] mt-1">{errors['response_payload.text']}</p>}
                                        </div>
                                        
                                        {data.response_type === 'interactive' && (
                                            <div>
                                                <div className="flex items-center justify-between mb-2">
                                                    <label className="block text-xs font-bold text-gray-700">Quick Reply Buttons (Max 3)</label>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            const currentButtons = data.response_payload.buttons || [];
                                                            if (currentButtons.length < 3) {
                                                                setData('response_payload', {
                                                                    ...data.response_payload,
                                                                    buttons: [...currentButtons, { type: 'reply', reply: { id: `btn_${Date.now()}`, title: '' } }]
                                                                });
                                                            }
                                                        }}
                                                        disabled={(data.response_payload.buttons || []).length >= 3}
                                                        className="text-[10px] bg-emerald-50 text-emerald-700 font-bold px-2 py-1 rounded hover:bg-emerald-100 disabled:opacity-50"
                                                    >
                                                        + Add Button
                                                    </button>
                                                </div>
                                                
                                                <div className="space-y-2">
                                                    {(data.response_payload.buttons || []).map((btn, index) => (
                                                        <div key={btn.reply.id || index} className="flex items-center gap-2">
                                                            <input
                                                                type="text"
                                                                value={btn.reply.title}
                                                                onChange={(e) => {
                                                                    const newBtns = [...data.response_payload.buttons];
                                                                    newBtns[index].reply.title = e.target.value.substring(0, 20); // max 20 chars
                                                                    setData('response_payload', { ...data.response_payload, buttons: newBtns });
                                                                }}
                                                                placeholder="Button Text"
                                                                className="flex-1 px-3 py-1.5 border border-gray-200 rounded-lg text-xs focus:ring-[#00a884] focus:border-[#00a884]"
                                                                required
                                                            />
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    const newBtns = data.response_payload.buttons.filter((_, i) => i !== index);
                                                                    setData('response_payload', { ...data.response_payload, buttons: newBtns });
                                                                }}
                                                                className="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg"
                                                            >
                                                                ✕
                                                            </button>
                                                        </div>
                                                    ))}
                                                    {(data.response_payload.buttons || []).length === 0 && (
                                                        <p className="text-xs text-gray-400 italic">No buttons added yet. Add at least one.</p>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {(data.response_type === 'image' || data.response_type === 'document') && (
                                    <div className="space-y-3">
                                        <div>
                                            <label htmlFor="response_url" className="block text-xs font-bold text-gray-700 mb-1">Media URL (HTTPS Required)</label>
                                            <input
                                                type="url"
                                                id="response_url"
                                                name="response_url"
                                                value={data.response_payload.url}
                                                onChange={(e) => setData('response_payload', { ...data.response_payload, url: e.target.value })}
                                                placeholder="https://example.com/catalog.pdf"
                                                className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                                required
                                            />
                                            {errors['response_payload.url'] && <p className="text-rose-500 text-[10px] mt-1">{errors['response_payload.url']}</p>}
                                        </div>

                                        {data.response_type === 'image' && (
                                            <div>
                                                <label htmlFor="response_caption" className="block text-xs font-bold text-gray-700 mb-1">Caption</label>
                                                <input
                                                    type="text"
                                                    id="response_caption"
                                                    name="response_caption"
                                                    value={data.response_payload.caption}
                                                    onChange={(e) => setData('response_payload', { ...data.response_payload, caption: e.target.value })}
                                                    placeholder="Check out our catalog!"
                                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                                />
                                            </div>
                                        )}
                                    </div>
                                )}

                                <div className="flex justify-end gap-2 pt-3 border-t border-gray-100">
                                    <button
                                        type="button"
                                        onClick={() => setIsModalOpen(false)}
                                        className="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="px-4 py-2 bg-[#00a884] hover:bg-[#008f70] text-white rounded-xl text-xs font-semibold transition shadow-md shadow-emerald-500/20 disabled:opacity-50"
                                    >
                                        {editingTrigger ? 'Save Changes' : 'Create Rule'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
