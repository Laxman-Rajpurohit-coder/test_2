import React, { useState, useMemo, useEffect, useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, Link, router } from '@inertiajs/react';
import CampaignMessagePreview from '@/Components/CampaignMessagePreview';
import axios from 'axios';

export default function Create({ auth, approvedTemplates = [], groups = [], tags = [] }) {
    // Mode Switcher: 'template' | 'text'
    const [mode, setMode] = useState('template');

    // Audience & Targeting State
    const [targetType, setTargetType] = useState('all'); // 'all' | 'tags' | 'groups'
    const [selectedTagIds, setSelectedTagIds] = useState([]);
    const [selectedGroupIds, setSelectedGroupIds] = useState([]);

    // Recipient Count State & Debounce
    const [recipientCount, setRecipientCount] = useState(0);
    const [excludedCount, setExcludedCount] = useState(0);
    const [isCountingRecipients, setIsCountingRecipients] = useState(false);
    const debounceTimerRef = useRef(null);

    // Media & Sample Contact State for Preview
    const [mediaUrl, setMediaUrl] = useState('');
    const [mediaType, setMediaType] = useState('image'); // 'image' | 'video' | 'document' | 'audio'
    const [sampleContact, setSampleContact] = useState({
        name: 'John Doe',
        phone_number: '+919876543210',
        city: 'Mumbai',
        plan: 'Enterprise',
    });

    // 1. Template Mode State Model
    const [templateState, setTemplateState] = useState({
        template_name: '',
        template_language: '',
        variables: {}, // { 1: 'John', 2: 'Mumbai' }
    });

    // 2. Freeform Text Mode State Model
    const [textState, setTextState] = useState({
        text_content: '',
    });

    // Campaign Header State
    const [campaignName, setCampaignName] = useState('');
    const [isScheduled, setIsScheduled] = useState(false);
    const [scheduledDate, setScheduledDate] = useState('');
    const [scheduledTime, setScheduledTime] = useState('');

    const mainForm = useForm({});

    // Fetch Debounced Recipient Count
    useEffect(() => {
        if (debounceTimerRef.current) clearTimeout(debounceTimerRef.current);

        setIsCountingRecipients(true);
        debounceTimerRef.current = setTimeout(() => {
            axios.post(route('campaigns.recipient-count'), {
                target_type: targetType,
                tag_ids: selectedTagIds,
                group_ids: selectedGroupIds,
            })
            .then(res => {
                setRecipientCount(res.data.recipient_count);
                setExcludedCount(res.data.excluded);
            })
            .catch(err => console.error("Recipient count error", err))
            .finally(() => setIsCountingRecipients(false));
        }, 300);

        return () => clearTimeout(debounceTimerRef.current);
    }, [targetType, selectedTagIds, selectedGroupIds]);

    // Active Template Helper
    const selectedTemplate = useMemo(() => {
        if (!templateState.template_name) return null;
        return approvedTemplates.find(t => t.name === templateState.template_name);
    }, [templateState.template_name, approvedTemplates]);

    // Extract Required Variable Count from Selected Template
    const templateVariableKeys = useMemo(() => {
        if (!selectedTemplate) return [];
        const keys = [];
        try {
            const components = typeof selectedTemplate.components === 'string'
                ? JSON.parse(selectedTemplate.components)
                : selectedTemplate.components;

            const body = components?.find(c => c.type === 'BODY' || c.type === 'body');
            if (body && body.text) {
                const matches = body.text.match(/\{\{(\d+)\}\}/g);
                if (matches) {
                    matches.forEach(m => {
                        const num = parseInt(m.replace(/[^0-9]/g, ''), 10);
                        if (!keys.includes(num)) keys.push(num);
                    });
                }
            }
        } catch (e) {
            console.error("Error parsing components for variables", e);
        }
        return keys.sort((a, b) => a - b);
    }, [selectedTemplate]);

    const handleTagToggle = (tagId) => {
        setSelectedTagIds(prev => 
            prev.includes(tagId) ? prev.filter(id => id !== tagId) : [...prev, tagId]
        );
    };

    const handleGroupToggle = (groupId) => {
        setSelectedGroupIds(prev => 
            prev.includes(groupId) ? prev.filter(id => id !== groupId) : [...prev, groupId]
        );
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        
        let scheduled_at = null;
        if (isScheduled && scheduledDate && scheduledTime) {
            scheduled_at = `${scheduledDate} ${scheduledTime}:00`;
        }

        const payload = {
            name: campaignName,
            message_type: mode,
            target_type: targetType,
            tag_ids: selectedTagIds,
            group_ids: selectedGroupIds,
            scheduled_at: scheduled_at,
            ...(mode === 'template' ? {
                template_name: templateState.template_name,
                template_language: templateState.template_language,
                template_variable_map: Object.values(templateState.variables),
            } : {
                text_content: textState.text_content,
                media_url: mediaUrl,
            })
        };

        router.post(route('campaigns.store'), payload);
    };

    return (
        <AppLayout title="Create Campaign">
            <Head title="Create Broadcast Campaign" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
                
                {/* Header Context Bar */}
                <div className="flex items-center justify-between bg-white p-5 rounded-2xl border border-gray-100 shadow-xs">
                    <div className="flex items-center gap-3">
                        <Link 
                            href={route('campaigns.index')}
                            className="p-2 bg-gray-50 text-gray-500 rounded-xl hover:bg-gray-100 hover:text-gray-900 transition"
                        >
                            ← Back
                        </Link>
                        <div>
                            <h1 className="text-xl font-extrabold text-gray-900">Create Broadcast Campaign</h1>
                            <p className="text-xs text-gray-500">Configure audience targeting, template variables, and live preview</p>
                        </div>
                    </div>
                </div>

                {/* 2-Column Split Screen Wizard */}
                <form onSubmit={handleSubmit} className="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    
                    {/* LEFT COLUMN: Campaign Configuration Form (7 cols) */}
                    <div className="lg:col-span-7 space-y-6">
                        
                        {/* Step 1: Campaign Details & Audience */}
                        <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-xs space-y-4">
                            <h2 className="text-sm font-extrabold uppercase tracking-wider text-gray-400">1. Campaign & Audience</h2>
                            
                            <div>
                                <label className="block text-xs font-bold text-gray-700 mb-1">Campaign Name</label>
                                <input 
                                    type="text"
                                    required
                                    placeholder="e.g. Diwali Offer Announcement 2026"
                                    value={campaignName}
                                    onChange={e => setCampaignName(e.target.value)}
                                    className="w-full text-sm rounded-xl border-gray-200 focus:ring-[#00a884] focus:border-[#00a884]"
                                />
                            </div>

                            {/* Audience Target Selector */}
                            <div>
                                <label className="block text-xs font-bold text-gray-700 mb-2">Target Audience</label>
                                <div className="grid grid-cols-3 gap-3 mb-3">
                                    <button
                                        type="button"
                                        onClick={() => setTargetType('all')}
                                        className={`p-3 rounded-xl border text-xs font-bold transition flex flex-col items-center gap-1 ${
                                            targetType === 'all'
                                                ? 'bg-emerald-50 text-[#00a884] border-[#00a884]'
                                                : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'
                                        }`}
                                    >
                                        <span className="text-base">👥</span>
                                        All Contacts
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => setTargetType('tags')}
                                        className={`p-3 rounded-xl border text-xs font-bold transition flex flex-col items-center gap-1 ${
                                            targetType === 'tags'
                                                ? 'bg-purple-50 text-purple-700 border-purple-500'
                                                : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'
                                        }`}
                                    >
                                        <span className="text-base">🏷️</span>
                                        Specific Tags
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => setTargetType('groups')}
                                        className={`p-3 rounded-xl border text-xs font-bold transition flex flex-col items-center gap-1 ${
                                            targetType === 'groups'
                                                ? 'bg-blue-50 text-blue-700 border-blue-500'
                                                : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'
                                        }`}
                                    >
                                        <span className="text-base">📁</span>
                                        Contact Groups
                                    </button>
                                </div>

                                {/* Tags Checkbox List */}
                                {targetType === 'tags' && (
                                    <div className="p-3 bg-purple-50/50 rounded-xl border border-purple-100 space-y-2 max-h-40 overflow-y-auto">
                                        <p className="text-xs font-bold text-purple-900 mb-1">Select Target Tags:</p>
                                        {tags.length > 0 ? tags.map(tag => (
                                            <label key={tag.id} className="flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-800">
                                                <input 
                                                    type="checkbox"
                                                    checked={selectedTagIds.includes(tag.id)}
                                                    onChange={() => handleTagToggle(tag.id)}
                                                    className="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                                                />
                                                {tag.name}
                                            </label>
                                        )) : (
                                            <p className="text-xs text-gray-400 italic">No tags created yet.</p>
                                        )}
                                    </div>
                                )}

                                {/* Groups Checkbox List */}
                                {targetType === 'groups' && (
                                    <div className="p-3 bg-blue-50/50 rounded-xl border border-blue-100 space-y-2 max-h-40 overflow-y-auto">
                                        <p className="text-xs font-bold text-blue-900 mb-1">Select Target Groups:</p>
                                        {groups.length > 0 ? groups.map(grp => (
                                            <label key={grp.id} className="flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-800">
                                                <input 
                                                    type="checkbox"
                                                    checked={selectedGroupIds.includes(grp.id)}
                                                    onChange={() => handleGroupToggle(grp.id)}
                                                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                />
                                                {grp.name}
                                            </label>
                                        )) : (
                                            <p className="text-xs text-gray-400 italic">No groups created yet.</p>
                                        )}
                                    </div>
                                )}

                                {/* Live Debounced Recipient Volume Counter Badge */}
                                <div className="mt-3 flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-200">
                                    <div className="flex items-center gap-2 text-xs">
                                        <span className="font-bold text-gray-700">Calculated Volume:</span>
                                        {isCountingRecipients ? (
                                            <span className="text-gray-400 font-italic">Calculating...</span>
                                        ) : (
                                            <span className="px-2.5 py-0.5 bg-[#00a884] text-white font-extrabold rounded-full text-xs">
                                                {recipientCount} Recipients
                                            </span>
                                        )}
                                    </div>
                                    {excludedCount > 0 && (
                                        <span className="text-[10px] text-rose-500 font-semibold">
                                            ({excludedCount} opted-out contacts excluded)
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Step 2: Message Type & Content Mode */}
                        <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-xs space-y-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-sm font-extrabold uppercase tracking-wider text-gray-400">2. Message Content Mode</h2>
                                
                                {/* Mode Switcher Radio Buttons */}
                                <div className="flex bg-gray-100 p-1 rounded-xl gap-1">
                                    <button
                                        type="button"
                                        onClick={() => setMode('template')}
                                        className={`px-3 py-1 text-xs font-bold rounded-lg transition ${
                                            mode === 'template' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500'
                                        }`}
                                    >
                                        WhatsApp Template
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setMode('text')}
                                        className={`px-3 py-1 text-xs font-bold rounded-lg transition ${
                                            mode === 'text' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500'
                                        }`}
                                    >
                                        Freeform Text
                                    </button>
                                </div>
                            </div>

                            {/* TEMPLATE MODE CONFIGURATION */}
                            {mode === 'template' ? (
                                <div className="space-y-4 pt-2">
                                    <div>
                                        <label className="block text-xs font-bold text-gray-700 mb-1">Select Approved Template</label>
                                        <select
                                            value={templateState.template_name}
                                            onChange={e => {
                                                const name = e.target.value;
                                                const t = approvedTemplates.find(item => item.name === name);
                                                setTemplateState({
                                                    template_name: name,
                                                    template_language: t ? t.language : '',
                                                    variables: {},
                                                });
                                            }}
                                            className="w-full text-sm rounded-xl border-gray-200 focus:ring-[#00a884] focus:border-[#00a884]"
                                        >
                                            <option value="">-- Choose WhatsApp Template --</option>
                                            {approvedTemplates.map(tmpl => (
                                                <option key={tmpl.id} value={tmpl.name}>
                                                    {tmpl.name} ({tmpl.language})
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {/* Template Positional Variable Mapper */}
                                    {templateVariableKeys.length > 0 && (
                                        <div className="p-4 bg-emerald-50/50 rounded-xl border border-emerald-100 space-y-3">
                                            <p className="text-xs font-bold text-emerald-900 uppercase tracking-wider">
                                                Map Template Variables
                                            </p>
                                            {templateVariableKeys.map(keyNum => (
                                                <div key={keyNum} className="flex items-center gap-3">
                                                    <span className="text-xs font-mono font-bold text-emerald-800 w-16">
                                                        {`{{${keyNum}}}`}:
                                                    </span>
                                                    <input 
                                                        type="text"
                                                        placeholder={`Sample value for {{${keyNum}}}...`}
                                                        value={templateState.variables[keyNum] || ''}
                                                        onChange={e => {
                                                            const val = e.target.value;
                                                            setTemplateState(prev => ({
                                                                ...prev,
                                                                variables: { ...prev.variables, [keyNum]: val }
                                                            }));
                                                        }}
                                                        className="flex-1 text-xs rounded-lg border-gray-200"
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ) : (
                                /* FREEFORM TEXT MODE CONFIGURATION */
                                <div className="space-y-4 pt-2">
                                    <div>
                                        <label className="block text-xs font-bold text-gray-700 mb-1">Message Text</label>
                                        <textarea
                                            rows={4}
                                            placeholder="Type your WhatsApp broadcast message. Supports variables like {{name}} or {{city}}..."
                                            value={textState.text_content}
                                            onChange={e => setTextState({ text_content: e.target.value })}
                                            className="w-full text-sm rounded-xl border-gray-200 focus:ring-[#00a884] focus:border-[#00a884]"
                                        />
                                    </div>

                                    {/* Media Attachment Selector */}
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-bold text-gray-700 mb-1">Media Type</label>
                                            <select
                                                value={mediaType}
                                                onChange={e => setMediaType(e.target.value)}
                                                className="w-full text-xs rounded-xl border-gray-200"
                                            >
                                                <option value="image">📷 Image</option>
                                                <option value="video">🎥 Video</option>
                                                <option value="document">📄 Document</option>
                                                <option value="audio">🎵 Audio</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-bold text-gray-700 mb-1">Media URL (Optional)</label>
                                            <input 
                                                type="url"
                                                placeholder="https://example.com/banner.png"
                                                value={mediaUrl}
                                                onChange={e => setMediaUrl(e.target.value)}
                                                className="w-full text-xs rounded-xl border-gray-200"
                                            />
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Submit Button */}
                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={!campaignName.trim() || recipientCount === 0}
                                className="px-6 py-3 bg-[#00a884] text-white font-extrabold text-sm rounded-xl hover:bg-emerald-700 transition shadow-md disabled:opacity-50"
                            >
                                Launch Broadcast Campaign ➔
                            </button>
                        </div>
                    </div>

                    {/* RIGHT COLUMN: Live Smartphone Preview (5 cols) */}
                    <div className="lg:col-span-5 space-y-4">
                        <div className="sticky top-6 bg-white p-6 rounded-2xl border border-gray-100 shadow-xs space-y-4 text-center">
                            <div className="flex items-center justify-between border-b border-gray-100 pb-3">
                                <h3 className="text-xs font-extrabold uppercase tracking-wider text-gray-400">Live WhatsApp Preview</h3>
                                <span className="text-[10px] font-bold px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-full">
                                    Real-time
                                </span>
                            </div>

                            {/* Stateless Campaign Message Preview Component */}
                            <CampaignMessagePreview
                                messageType={mode}
                                template={selectedTemplate}
                                textContent={textState.text_content}
                                variables={templateState.variables}
                                mediaUrl={mediaUrl}
                                mediaType={mediaType}
                                sampleContact={sampleContact}
                            />

                            {/* Sample Contact Customizer */}
                            <div className="pt-3 border-t border-gray-100 text-left space-y-2">
                                <p className="text-[10px] font-bold uppercase tracking-wider text-gray-400">Sample Contact Variables</p>
                                <div className="grid grid-cols-2 gap-2 text-xs">
                                    <input 
                                        type="text"
                                        placeholder="Sample Name"
                                        value={sampleContact.name}
                                        onChange={e => setSampleContact(prev => ({ ...prev, name: e.target.value }))}
                                        className="text-xs p-1.5 rounded-lg border-gray-200"
                                    />
                                    <input 
                                        type="text"
                                        placeholder="Sample City"
                                        value={sampleContact.city}
                                        onChange={e => setSampleContact(prev => ({ ...prev, city: e.target.value }))}
                                        className="text-xs p-1.5 rounded-lg border-gray-200"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </AppLayout>
    );
}
