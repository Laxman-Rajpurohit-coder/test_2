import React, { useState, useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, Link } from '@inertiajs/react';

export default function Create({ auth, approvedTemplates, groups, tags }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        message_type: 'template', // text or template
        template_name: '',
        template_language: '',
        template_variable_map: [],
        text_content: '',
        target_type: 'all', // all, group, tag
        target_id: '',
        is_scheduled: false,
        scheduled_date: '',
        scheduled_time: '',
        scheduled_at: null, // combined for backend
    });

    // Extract variables from template body (e.g., {{1}}, {{2}})
    const selectedTemplate = useMemo(() => {
        if (!data.template_name) return null;
        return approvedTemplates.find(t => t.name === data.template_name);
    }, [data.template_name, approvedTemplates]);

    const requiredVariablesCount = useMemo(() => {
        if (!selectedTemplate) return 0;
        
        let count = 0;
        try {
            const components = typeof selectedTemplate.components === 'string' 
                ? JSON.parse(selectedTemplate.components) 
                : selectedTemplate.components;
                
            const body = components.find(c => c.type === 'BODY' || c.type === 'body');
            if (body && body.text) {
                const matches = body.text.match(/\{\{(\d+)\}\}/g);
                if (matches) {
                    const numbers = matches.map(m => parseInt(m.replace(/[^0-9]/g, '')));
                    count = Math.max(...numbers);
                }
            }
        } catch (e) {
            console.error("Error parsing components for variables", e);
        }
        return count;
    }, [selectedTemplate]);

    // Handle template selection
    const handleTemplateChange = (e) => {
        const name = e.target.value;
        const template = approvedTemplates.find(t => t.name === name);
        setData(data => ({
            ...data,
            template_name: name,
            template_language: template ? template.language : '',
            template_variable_map: [], // reset mapping
        }));
    };

    // Time generation for 15-min intervals
    const timeOptions = useMemo(() => {
        const options = [];
        for (let h = 0; h < 24; h++) {
            for (let m = 0; m < 60; m += 15) {
                const hour = h.toString().padStart(2, '0');
                const min = m.toString().padStart(2, '0');
                options.push(`${hour}:${min}`);
            }
        }
        return options;
    }, []);

    const submit = (e) => {
        e.preventDefault();
        
        // Combine date and time if scheduled
        let submitData = { ...data };
        if (data.is_scheduled && data.scheduled_date && data.scheduled_time) {
            submitData.scheduled_at = `${data.scheduled_date} ${data.scheduled_time}:00`;
        } else {
            submitData.scheduled_at = null;
        }

        post(route('campaigns.store'), {
            data: submitData,
            onSuccess: () => {
                // Redirects automatically via backend
            }
        });
    };

    return (
        <AppLayout
            user={auth.user}
            header={
                <div className="flex items-center space-x-4">
                    <Link href={route('campaigns.index')} className="text-gray-500 hover:text-gray-700">
                        &larr; Back
                    </Link>
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">Create Campaign</h2>
                </div>
            }
        >
            <Head title="Create Campaign" />

            <div className="py-12">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white shadow overflow-hidden sm:rounded-lg">
                        <form onSubmit={submit} className="p-8 space-y-8">
                            
                            {/* Campaign Details */}
                            <div>
                                <h3 className="text-lg font-medium leading-6 text-gray-900 border-b pb-2 mb-4">1. Campaign Details</h3>
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <div className="col-span-2">
                                        <label className="block text-sm font-medium text-gray-700">Campaign Name</label>
                                        <input
                                            type="text"
                                            required
                                            value={data.name}
                                            onChange={e => setData('name', e.target.value)}
                                            placeholder="e.g. Summer Sale Broadcast"
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                                    </div>
                                </div>
                            </div>

                            {/* Audience Targeting */}
                            <div>
                                <h3 className="text-lg font-medium leading-6 text-gray-900 border-b pb-2 mb-4">2. Audience Targeting</h3>
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Send To</label>
                                        <select
                                            value={data.target_type}
                                            onChange={e => setData('target_type', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        >
                                            <option value="all">All Contacts</option>
                                            <option value="group">Specific Group</option>
                                            <option value="tag">Specific Tag</option>
                                        </select>
                                    </div>

                                    {data.target_type === 'group' && (
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Select Group</label>
                                            <select
                                                required
                                                value={data.target_id}
                                                onChange={e => setData('target_id', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            >
                                                <option value="">-- Choose Group --</option>
                                                {groups.map(g => <option key={g.id} value={g.id}>{g.name}</option>)}
                                            </select>
                                        </div>
                                    )}

                                    {data.target_type === 'tag' && (
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Select Tag</label>
                                            <select
                                                required
                                                value={data.target_id}
                                                onChange={e => setData('target_id', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            >
                                                <option value="">-- Choose Tag --</option>
                                                {tags.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                                            </select>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Message Content */}
                            <div>
                                <h3 className="text-lg font-medium leading-6 text-gray-900 border-b pb-2 mb-4">3. Message Content</h3>
                                
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Message Type</label>
                                    <div className="flex items-center space-x-4">
                                        <label className="flex items-center">
                                            <input type="radio" className="text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-gray-300" 
                                                checked={data.message_type === 'template'}
                                                onChange={() => setData('message_type', 'template')}
                                            />
                                            <span className="ml-2 text-sm text-gray-700">Approved Template</span>
                                        </label>
                                        <label className="flex items-center">
                                            <input type="radio" className="text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-gray-300" 
                                                checked={data.message_type === 'text'}
                                                onChange={() => setData('message_type', 'text')}
                                            />
                                            <span className="ml-2 text-sm text-gray-700">Free Text (24h Window Only)</span>
                                        </label>
                                    </div>
                                </div>

                                {data.message_type === 'text' ? (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Message Text</label>
                                        <textarea
                                            required
                                            rows="4"
                                            value={data.text_content}
                                            onChange={e => setData('text_content', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        ></textarea>
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Select Template</label>
                                            <select
                                                required
                                                value={data.template_name}
                                                onChange={handleTemplateChange}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            >
                                                <option value="">-- Choose Template --</option>
                                                {approvedTemplates.map(t => (
                                                    <option key={t.id} value={t.name}>{t.name} ({t.language})</option>
                                                ))}
                                            </select>
                                        </div>

                                        {requiredVariablesCount > 0 && (
                                            <div className="bg-gray-50 p-4 rounded-md border border-gray-200">
                                                <h4 className="text-sm font-medium text-gray-700 mb-3">Map Template Variables</h4>
                                                <p className="text-xs text-gray-500 mb-4">Choose which contact fields should be injected into the template variables.</p>
                                                
                                                <div className="space-y-3">
                                                    {Array.from({ length: requiredVariablesCount }).map((_, idx) => (
                                                        <div key={idx} className="flex items-center space-x-3">
                                                            <span className="text-sm font-medium text-gray-600 bg-gray-200 px-2 py-1 rounded">
                                                                {'{{' + (idx + 1) + '}}'}
                                                            </span>
                                                            <span className="text-gray-400">=</span>
                                                            <select
                                                                required
                                                                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                                value={data.template_variable_map[idx] || ''}
                                                                onChange={(e) => {
                                                                    const newMap = [...data.template_variable_map];
                                                                    newMap[idx] = e.target.value;
                                                                    setData('template_variable_map', newMap);
                                                                }}
                                                            >
                                                                <option value="">-- Map to Field --</option>
                                                                <option value="first_name">First Name</option>
                                                                <option value="last_name">Last Name</option>
                                                                <option value="email">Email</option>
                                                                <option value="phone_number">Phone Number</option>
                                                                {/* You could optionally inject custom_fields keys here if you load them */}
                                                            </select>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>

                            {/* Scheduling */}
                            <div>
                                <h3 className="text-lg font-medium leading-6 text-gray-900 border-b pb-2 mb-4">4. Schedule</h3>
                                <div className="flex items-center mb-4">
                                    <input
                                        type="checkbox"
                                        id="is_scheduled"
                                        checked={data.is_scheduled}
                                        onChange={e => setData('is_scheduled', e.target.checked)}
                                        className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                    />
                                    <label htmlFor="is_scheduled" className="ml-2 block text-sm text-gray-900">
                                        Schedule for a future date and time
                                    </label>
                                </div>

                                {data.is_scheduled && (
                                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 bg-indigo-50 p-4 rounded-md">
                                        <div>
                                            <label className="block text-sm font-medium text-indigo-900">Date</label>
                                            <input
                                                type="date"
                                                required
                                                min={new Date().toISOString().split('T')[0]}
                                                value={data.scheduled_date}
                                                onChange={e => setData('scheduled_date', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-indigo-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-indigo-900">Time (15-min intervals)</label>
                                            <select
                                                required
                                                value={data.scheduled_time}
                                                onChange={e => setData('scheduled_time', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-indigo-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            >
                                                <option value="">-- Select Time --</option>
                                                {timeOptions.map(t => <option key={t} value={t}>{t}</option>)}
                                            </select>
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="pt-5 border-t flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                                >
                                    {processing ? 'Processing...' : (data.is_scheduled ? 'Schedule Campaign' : 'Send Immediately')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
