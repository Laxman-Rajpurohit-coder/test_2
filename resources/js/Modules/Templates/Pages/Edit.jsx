import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import PhonePreview from '../Components/PhonePreview';
import VariableHelper from '../Components/VariableHelper';

export default function Edit({ auth, template }) {
    const { data, setData, post, processing, errors } = useForm({
        name: template.name + '_copy',
        language: template.language || 'en',
        category: template.category || 'MARKETING',
        components: typeof template.components === 'string' 
            ? JSON.parse(template.components) 
            : (template.components || [{ type: 'BODY', format: 'TEXT', text: '' }])
    });

    const [step, setStep] = useState(1);

    const updateComponent = (type, field, value) => {
        const newComponents = [...data.components];
        const existingIndex = newComponents.findIndex(c => c.type === type);
        
        if (existingIndex >= 0) {
            newComponents[existingIndex][field] = value;
            // If text is cleared for optional components, remove them
            if ((type === 'HEADER' || type === 'FOOTER') && field === 'text' && value.trim() === '') {
                newComponents.splice(existingIndex, 1);
            }
        } else {
            newComponents.push({ type, format: 'TEXT', [field]: value });
        }
        
        setData('components', newComponents);
    };

    const getComponentData = (type, field, defaultValue = '') => {
        const comp = data.components.find(c => c.type === type);
        return comp ? (comp[field] || defaultValue) : defaultValue;
    };

    const handleVariableInsert = (variable) => {
        const currentText = getComponentData('BODY', 'text');
        updateComponent('BODY', 'text', currentText + (currentText.endsWith(' ') || currentText === '' ? '' : ' ') + variable);
    };

    const getButtons = () => {
        const comp = data.components.find(c => c.type === 'BUTTONS');
        return comp && comp.buttons ? comp.buttons : [];
    };

    const addQuickReply = () => {
        const buttons = [...getButtons()];
        if (buttons.length >= 3) return;
        buttons.push({ type: 'QUICK_REPLY', text: '' });
        updateComponent('BUTTONS', 'buttons', buttons);
    };

    const updateQuickReply = (index, text) => {
        const buttons = [...getButtons()];
        buttons[index].text = text;
        updateComponent('BUTTONS', 'buttons', buttons);
    };

    const removeQuickReply = (index) => {
        const buttons = [...getButtons()];
        buttons.splice(index, 1);
        if (buttons.length === 0) {
            const newComponents = data.components.filter(c => c.type !== 'BUTTONS');
            setData('components', newComponents);
        } else {
            updateComponent('BUTTONS', 'buttons', buttons);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('templates.store'));
    };

    return (
        <AppLayout
            user={auth.user}
            header={
                <div className="flex items-center space-x-4">
                    <button
                        type="button"
                        onClick={() => window.history.length > 1 ? window.history.back() : router.visit(route('templates.index'))}
                        className="text-gray-500 hover:text-gray-700 font-medium text-sm flex items-center gap-1"
                    >
                        &larr; Back
                    </button>
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">Duplicate & Submit as New</h2>
                </div>
            }
        >
            <Head title="Duplicate Template" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 flex flex-col md:flex-row gap-8">
                    
                    {/* Form Side */}
                    <div className="w-full md:w-3/5 bg-white shadow-sm rounded-lg overflow-hidden">
                        
                        {/* Progress Bar */}
                        <div className="border-b border-gray-200 bg-gray-50 px-6 py-3 flex items-center justify-between text-sm">
                            <span className={`font-medium ${step >= 1 ? 'text-indigo-600' : 'text-gray-400'}`}>1. Basic Info</span>
                            <span className="text-gray-300">❯</span>
                            <span className={`font-medium ${step >= 2 ? 'text-indigo-600' : 'text-gray-400'}`}>2. Components</span>
                            <span className="text-gray-300">❯</span>
                            <span className={`font-medium ${step >= 3 ? 'text-indigo-600' : 'text-gray-400'}`}>3. Review</span>
                        </div>

                        <div className="p-6">
                            <form onSubmit={submit}>
                                
                                {/* STEP 1 */}
                                {step === 1 && (
                                    <div className="space-y-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Template Name</label>
                                            <input
                                                type="text"
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                value={data.name}
                                                onChange={e => setData('name', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'))}
                                                placeholder="e.g. summer_sale_promo"
                                                required
                                            />
                                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                                            <p className="mt-1 text-xs text-gray-500">Lowercase letters, numbers, and underscores only.</p>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Category</label>
                                            <select
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                value={data.category}
                                                onChange={e => setData('category', e.target.value)}
                                            >
                                                <option value="MARKETING">Marketing (Promos, updates, offers)</option>
                                                <option value="UTILITY">Utility (Order updates, receipts, alerts)</option>
                                                <option value="AUTHENTICATION">Authentication (OTPs, codes)</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Language</label>
                                            <select
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                value={data.language}
                                                onChange={e => setData('language', e.target.value)}
                                            >
                                                <option value="en">English (en)</option>
                                                <option value="en_US">English (US)</option>
                                                <option value="en_GB">English (UK)</option>
                                                <option value="hi">Hindi (hi)</option>
                                                <option value="es">Spanish (es)</option>
                                                <option value="fr">French (fr)</option>
                                                <option value="pt_BR">Portuguese (BR)</option>
                                            </select>
                                        </div>
                                    </div>
                                )}

                                {/* STEP 2 */}
                                {step === 2 && (
                                    <div className="space-y-8">
                                        
                                        {/* Header */}
                                        <div className="bg-gray-50 p-4 rounded-md border border-gray-200">
                                            <h4 className="font-medium text-gray-900 mb-2">Header <span className="text-gray-400 font-normal text-sm">(Optional)</span></h4>
                                            <input
                                                type="text"
                                                className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                value={getComponentData('HEADER', 'text')}
                                                onChange={e => updateComponent('HEADER', 'text', e.target.value)}
                                                placeholder="Short header text (max 60 chars)"
                                                maxLength={60}
                                            />
                                        </div>

                                        {/* Body */}
                                        <div className="bg-white p-4 rounded-md border border-gray-300 shadow-sm relative">
                                            <h4 className="font-medium text-gray-900 mb-2">Body <span className="text-red-500">*</span></h4>
                                            <textarea
                                                rows="5"
                                                className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                value={getComponentData('BODY', 'text')}
                                                onChange={e => updateComponent('BODY', 'text', e.target.value)}
                                                placeholder="Message body. Use variables like {{1}} for dynamic content."
                                                required
                                            ></textarea>
                                            
                                            <VariableHelper text={getComponentData('BODY', 'text')} onInsert={handleVariableInsert} />
                                        </div>

                                        {/* Footer */}
                                        <div className="bg-gray-50 p-4 rounded-md border border-gray-200">
                                            <h4 className="font-medium text-gray-900 mb-2">Footer <span className="text-gray-400 font-normal text-sm">(Optional)</span></h4>
                                            <input
                                                type="text"
                                                className="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                value={getComponentData('FOOTER', 'text')}
                                                onChange={e => updateComponent('FOOTER', 'text', e.target.value)}
                                                placeholder="Small gray text at the bottom (max 60 chars)"
                                                maxLength={60}
                                            />
                                        </div>

                                        {/* Quick Replies */}
                                        <div className="bg-gray-50 p-4 rounded-md border border-gray-200">
                                            <div className="flex justify-between items-center mb-3">
                                                <h4 className="font-medium text-gray-900">Quick Reply Buttons <span className="text-gray-400 font-normal text-sm">(Optional)</span></h4>
                                                <button 
                                                    type="button" 
                                                    onClick={addQuickReply}
                                                    disabled={getButtons().length >= 3}
                                                    className="text-xs font-bold text-indigo-600 hover:text-indigo-800 disabled:opacity-50"
                                                >
                                                    + Add Button
                                                </button>
                                            </div>
                                            
                                            {getButtons().length === 0 ? (
                                                <div className="text-sm text-gray-400 italic text-center py-2 bg-white rounded border border-dashed border-gray-200">No buttons added</div>
                                            ) : (
                                                <div className="space-y-2">
                                                    {getButtons().map((btn, index) => (
                                                        <div key={index} className="flex gap-2 items-center">
                                                            <div className="bg-gray-100 text-gray-500 font-mono text-xs px-2 py-2 rounded border border-gray-200 shrink-0">
                                                                Btn {index + 1}
                                                            </div>
                                                            <input
                                                                type="text"
                                                                value={btn.text}
                                                                onChange={e => updateQuickReply(index, e.target.value)}
                                                                placeholder="Button text (max 25 chars)"
                                                                maxLength={25}
                                                                required
                                                                className="flex-1 border border-gray-300 rounded-lg p-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                                            />
                                                            <button 
                                                                type="button" 
                                                                onClick={() => removeQuickReply(index)}
                                                                className="text-rose-500 hover:text-rose-700 p-2"
                                                            >
                                                                ✕
                                                            </button>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>

                                    </div>
                                )}

                                {/* STEP 3 */}
                                {step === 3 && (
                                    <div className="space-y-4">
                                        <div className="bg-blue-50 border-l-4 border-blue-400 p-4">
                                            <div className="flex">
                                                <div className="flex-shrink-0">
                                                    <svg className="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                                                    </svg>
                                                </div>
                                                <div className="ml-3">
                                                    <p className="text-sm text-blue-700">
                                                        Please review your template in the preview pane. Once submitted, Meta typically reviews templates within 24-48 hours.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div className="bg-gray-50 p-4 rounded border text-sm text-gray-700">
                                            <p><strong>Name:</strong> {data.name}</p>
                                            <p><strong>Category:</strong> {data.category}</p>
                                            <p><strong>Language:</strong> {data.language}</p>
                                        </div>
                                        {errors.components && <p className="mt-1 text-sm text-red-600">{errors.components}</p>}
                                    </div>
                                )}

                                {/* Navigation Buttons */}
                                <div className="mt-8 pt-5 border-t border-gray-200 flex justify-between">
                                    {step > 1 ? (
                                        <button
                                            type="button"
                                            onClick={() => setStep(step - 1)}
                                            className="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        >
                                            Previous
                                        </button>
                                    ) : (
                                        <div></div>
                                    )}
                                    
                                    {step < 3 ? (
                                        <button
                                            type="button"
                                            onClick={() => setStep(step + 1)}
                                            disabled={step === 1 && !data.name}
                                            className="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                                        >
                                            Next Step
                                        </button>
                                    ) : (
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
                                        >
                                            {processing ? 'Submitting...' : 'Submit for Approval'}
                                        </button>
                                    )}
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Preview Side */}
                    <div className="w-full md:w-2/5">
                        <div className="sticky top-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4 text-center">Live Preview</h3>
                            <PhonePreview components={data.components} />
                        </div>
                    </div>

                </div>
            </div>
        </AppLayout>
    );
}
