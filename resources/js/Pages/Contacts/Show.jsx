import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import ConversationTimeline from '@/Components/ConversationTimeline';

export default function ContactsShow({ 
    contact, 
    conversation = null, 
    messages = [], 
    campaignHistory = [], 
    allTags = [], 
    teamMembers = [], 
    approvedTemplates = [] 
}) {
    const [activeTab, setActiveTab] = useState('timeline'); // 'timeline' | 'campaigns'
    const [isEditingCustomFields, setIsEditingCustomFields] = useState(false);
    const [newKey, setNewKey] = useState('');
    const [newValue, setNewValue] = useState('');
    
    // Quick Outbound Reply form
    const quickReplyForm = useForm({
        contact_ids: [contact.id],
        message_type: 'text',
        template_id: '',
        message_text: '',
    });

    // Contact Update form
    const profileForm = useForm({
        name: contact.name || '',
        assigned_user_id: contact.assigned_user_id || '',
        is_subscribed: contact.is_subscribed ?? true,
        custom_fields: contact.custom_fields || {},
    });

    const handleAssignUser = (userId) => {
        router.put(route('contacts.update', contact.id), {
            assigned_user_id: userId,
        }, { preserveScroll: true });
    };

    const handleToggleSubscription = () => {
        router.put(route('contacts.update', contact.id), {
            is_subscribed: !contact.is_subscribed,
        }, { preserveScroll: true });
    };

    const handleAddCustomField = (e) => {
        e.preventDefault();
        if (!newKey.trim()) return;
        
        const updatedFields = {
            ...profileForm.data.custom_fields,
            [newKey.trim()]: newValue.trim()
        };

        router.put(route('contacts.update', contact.id), {
            custom_fields: updatedFields
        }, {
            preserveScroll: true,
            onSuccess: () => {
                profileForm.setData('custom_fields', updatedFields);
                setNewKey('');
                setNewValue('');
            }
        });
    };

    const handleDeleteCustomField = (keyToDelete) => {
        const updatedFields = { ...profileForm.data.custom_fields };
        delete updatedFields[keyToDelete];

        router.put(route('contacts.update', contact.id), {
            custom_fields: updatedFields
        }, {
            preserveScroll: true,
            onSuccess: () => profileForm.setData('custom_fields', updatedFields)
        });
    };

    const handleSendQuickReply = (e) => {
        e.preventDefault();
        quickReplyForm.post(route('contacts.quick-send'), {
            preserveScroll: true,
            onSuccess: () => quickReplyForm.reset('message_text'),
        });
    };

    return (
        <AppLayout title={`Contact Profile - ${contact.name || contact.phone_number}`}>
            <Head title={`Contact: ${contact.name || contact.phone_number}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
                {/* Header Context Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-gray-100 shadow-xs">
                    <div className="flex items-center gap-4">
                        <Link 
                            href={route('contacts.index')}
                            className="p-2.5 bg-gray-50 text-gray-500 rounded-xl hover:bg-gray-100 hover:text-gray-900 transition"
                            title="Back to Contacts"
                        >
                            ← Back
                        </Link>
                        <div className="w-12 h-12 rounded-full bg-emerald-100 text-[#00a884] font-black text-xl flex items-center justify-center border-2 border-emerald-200">
                            {(contact.name || contact.phone_number).substring(0, 2).toUpperCase()}
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-extrabold text-gray-900">
                                    {contact.name || 'Unnamed Contact'}
                                </h1>
                                <button 
                                    onClick={handleToggleSubscription}
                                    className={`px-2.5 py-0.5 rounded-full text-xs font-bold transition ${
                                        contact.is_subscribed 
                                            ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' 
                                            : 'bg-rose-100 text-rose-800 hover:bg-rose-200'
                                    }`}
                                >
                                    {contact.is_subscribed ? '✓ Subscribed' : '✕ Opted Out'}
                                </button>
                            </div>
                            <p className="text-sm font-mono text-gray-500 mt-0.5">{contact.phone_number}</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <span className="text-xs text-gray-400">Created: {new Date(contact.created_at).toLocaleDateString()}</span>
                    </div>
                </div>

                {/* 2-Column CRM Layout */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    {/* LEFT COLUMN: Profile Attributes & Metadata */}
                    <div className="lg:col-span-1 space-y-6">
                        
                        {/* Team Assignment & Tags Card */}
                        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-xs space-y-5">
                            <h3 className="text-xs font-extrabold uppercase tracking-wider text-gray-400">Assignment & Tags</h3>
                            
                            {/* Team Member Assignment */}
                            <div>
                                <label className="block text-xs font-bold text-gray-700 mb-1.5">Assigned Agent</label>
                                <select
                                    value={contact.assigned_user_id || ''}
                                    onChange={(e) => handleAssignUser(e.target.value)}
                                    className="w-full text-sm rounded-xl border-gray-200 focus:ring-[#00a884] focus:border-[#00a884]"
                                >
                                    <option value="">Unassigned</option>
                                    {teamMembers.map(member => (
                                        <option key={member.id} value={member.id}>
                                            {member.name} ({member.email})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Applied Tags */}
                            <div>
                                <label className="block text-xs font-bold text-gray-700 mb-2">Applied Tags</label>
                                <div className="flex flex-wrap gap-1.5">
                                    {contact.contact_tags && contact.contact_tags.length > 0 ? (
                                        contact.contact_tags.map(tag => (
                                            <span key={tag.id} className="px-2.5 py-1 bg-purple-50 text-purple-700 border border-purple-200 text-xs font-bold rounded-lg">
                                                🏷️ {tag.name}
                                            </span>
                                        ))
                                    ) : (
                                        <span className="text-xs text-gray-400 italic">No tags assigned</span>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Custom Fields Card */}
                        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-xs space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-xs font-extrabold uppercase tracking-wider text-gray-400">Custom Attributes</h3>
                                <button 
                                    onClick={() => setIsEditingCustomFields(!isEditingCustomFields)}
                                    className="text-xs font-bold text-[#00a884] hover:underline"
                                >
                                    {isEditingCustomFields ? 'Done' : '+ Add Attribute'}
                                </button>
                            </div>

                            {/* Custom Fields List */}
                            <div className="space-y-2">
                                {contact.custom_fields && Object.keys(contact.custom_fields).length > 0 ? (
                                    Object.entries(contact.custom_fields).map(([key, val]) => (
                                        <div key={key} className="flex items-center justify-between p-2.5 bg-gray-50 rounded-xl text-xs">
                                            <div>
                                                <span className="font-bold text-gray-700 uppercase block text-[10px]">{key}</span>
                                                <span className="font-medium text-gray-900">{String(val)}</span>
                                            </div>
                                            {isEditingCustomFields && (
                                                <button 
                                                    onClick={() => handleDeleteCustomField(key)}
                                                    className="text-rose-500 hover:text-rose-700 font-bold px-1"
                                                >
                                                    ✕
                                                </button>
                                            )}
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-xs text-gray-400 italic text-center py-2">No custom attributes recorded.</p>
                                )}
                            </div>

                            {/* Add Custom Field Form */}
                            {isEditingCustomFields && (
                                <form onSubmit={handleAddCustomField} className="pt-3 border-t border-gray-100 space-y-2">
                                    <input 
                                        type="text"
                                        placeholder="Attribute Name (e.g. City)"
                                        value={newKey}
                                        onChange={e => setNewKey(e.target.value)}
                                        className="w-full text-xs rounded-lg border-gray-200"
                                    />
                                    <input 
                                        type="text"
                                        placeholder="Value (e.g. Mumbai)"
                                        value={newValue}
                                        onChange={e => setNewValue(e.target.value)}
                                        className="w-full text-xs rounded-lg border-gray-200"
                                    />
                                    <button 
                                        type="submit"
                                        disabled={!newKey.trim()}
                                        className="w-full py-1.5 bg-[#00a884] text-white text-xs font-bold rounded-lg hover:bg-emerald-700 transition disabled:opacity-50"
                                    >
                                        Save Attribute
                                    </button>
                                </form>
                            )}
                        </div>
                    </div>

                    {/* RIGHT COLUMN: Interaction Timeline & Campaign History */}
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-white rounded-2xl border border-gray-100 shadow-xs overflow-hidden">
                            
                            {/* Navigation Tabs */}
                            <div className="flex border-b border-gray-100 bg-gray-50/50 p-1.5 gap-1">
                                <button
                                    onClick={() => setActiveTab('timeline')}
                                    className={`flex-1 py-2.5 text-xs font-extrabold rounded-xl transition ${
                                        activeTab === 'timeline'
                                            ? 'bg-white text-gray-900 shadow-xs'
                                            : 'text-gray-500 hover:text-gray-900'
                                    }`}
                                >
                                    💬 Message & Bot Timeline ({messages.length})
                                </button>
                                <button
                                    onClick={() => setActiveTab('campaigns')}
                                    className={`flex-1 py-2.5 text-xs font-extrabold rounded-xl transition ${
                                        activeTab === 'campaigns'
                                            ? 'bg-white text-gray-900 shadow-xs'
                                            : 'text-gray-500 hover:text-gray-900'
                                    }`}
                                >
                                    📢 Broadcast History ({campaignHistory.length})
                                </button>
                            </div>

                            {/* TAB 1: Conversation & Bot Timeline */}
                            {activeTab === 'timeline' && (
                                <div className="p-6 space-y-4">
                                    {/* Reusable Conversation Timeline Component */}
                                    <ConversationTimeline 
                                        messages={messages} 
                                        customerName={contact.name || contact.phone_number} 
                                    />

                                    {/* Quick Message Reply Form */}
                                    <form onSubmit={handleSendQuickReply} className="pt-4 border-t border-gray-100 space-y-3">
                                        <div className="flex items-center justify-between">
                                            <label className="block text-xs font-bold text-gray-700">Quick Outbound Message</label>
                                            <span className="text-[10px] text-gray-400">Sends directly via WhatsApp API</span>
                                        </div>

                                        <div className="flex gap-2">
                                            <input 
                                                type="text"
                                                placeholder={`Type WhatsApp message to ${contact.name || contact.phone_number}...`}
                                                value={quickReplyForm.data.message_text}
                                                onChange={e => quickReplyForm.setData('message_text', e.target.value)}
                                                className="flex-1 text-sm rounded-xl border-gray-200 focus:ring-[#00a884] focus:border-[#00a884]"
                                            />
                                            <button
                                                type="submit"
                                                disabled={quickReplyForm.processing || !quickReplyForm.data.message_text.trim()}
                                                className="px-5 py-2 bg-[#00a884] text-white font-bold text-xs rounded-xl hover:bg-emerald-700 transition disabled:opacity-50"
                                            >
                                                {quickReplyForm.processing ? 'Sending...' : 'Send ➔'}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            )}

                            {/* TAB 2: Campaign Participation History */}
                            {activeTab === 'campaigns' && (
                                <div className="p-6">
                                    {campaignHistory.length > 0 ? (
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-xs">
                                                <thead className="bg-gray-50 text-gray-400 uppercase font-bold text-[10px]">
                                                    <tr>
                                                        <th className="p-3">Campaign Name</th>
                                                        <th className="p-3">Type</th>
                                                        <th className="p-3">Status</th>
                                                        <th className="p-3 text-right">Sent Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-100">
                                                    {campaignHistory.map(item => (
                                                        <tr key={item.id} className="hover:bg-gray-50/50 transition">
                                                            <td className="p-3 font-bold text-gray-900">
                                                                {item.campaign?.name || 'Quick Send Broadcast'}
                                                            </td>
                                                            <td className="p-3 font-medium text-gray-500 uppercase text-[10px]">
                                                                {item.campaign?.message_type || 'template'}
                                                            </td>
                                                            <td className="p-3">
                                                                <span className={`px-2 py-0.5 rounded-full font-bold text-[10px] ${
                                                                    item.status === 'delivered' || item.status === 'read'
                                                                        ? 'bg-emerald-100 text-emerald-800'
                                                                        : item.status === 'failed'
                                                                        ? 'bg-rose-100 text-rose-800'
                                                                        : 'bg-amber-100 text-amber-800'
                                                                }`}>
                                                                    {item.status || 'sent'}
                                                                </span>
                                                            </td>
                                                            <td className="p-3 text-right text-gray-400">
                                                                {new Date(item.created_at).toLocaleString()}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : (
                                        <div className="text-center py-12 text-gray-400 space-y-2">
                                            <div className="text-3xl">📢</div>
                                            <p className="text-xs font-bold text-gray-600">No campaigns sent to this contact yet</p>
                                        </div>
                                    )}
                                </div>
                            )}

                        </div>
                    </div>

                </div>
            </div>
        </AppLayout>
    );
}
