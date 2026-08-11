import React from 'react';

/**
 * Deterministic Variable Resolver
 * Precedence Rule:
 * 1. Named variables ({{name}}, {{city}}) resolve against sampleContact[key].
 * 2. Positional variables ({{1}}, {{2}}) resolve against variables[1]/variables[2]. If omitted, fallback to sample contact fields (1 -> name, 2 -> phone_number, 3 -> city).
 */
export function resolvePreviewText(text = '', variables = {}, sampleContact = {}) {
    if (!text) return '';

    let resolved = text;

    // 1. Resolve named variables e.g. {{name}}, {{city}}, {{phone_number}}
    Object.keys(sampleContact).forEach(key => {
        const regex = new RegExp(`{{\\s*${key}\\s*}}`, 'gi');
        if (sampleContact[key] !== undefined && sampleContact[key] !== null) {
            resolved = resolved.replace(regex, sampleContact[key]);
        }
    });

    // 2. Resolve positional variables e.g. {{1}}, {{2}}, {{3}}
    resolved = resolved.replace(/{{\s*(\d+)\s*}}/g, (match, indexStr) => {
        const index = parseInt(indexStr, 10);
        if (variables[index] !== undefined && variables[index] !== '') {
            return variables[index];
        }
        // Fallback positional map from sample contact
        const fallbackOrder = ['name', 'phone_number', 'city', 'company'];
        const fallbackKey = fallbackOrder[index - 1];
        if (fallbackKey && sampleContact[fallbackKey]) {
            return sampleContact[fallbackKey];
        }
        return match; // Keep {{1}} if unresolved
    });

    return resolved;
}

/**
 * Stateless WhatsApp Live Preview Component
 */
export default function CampaignMessagePreview({
    messageType = 'template', // 'template' | 'text'
    template = null,
    textContent = '',
    variables = {},
    mediaUrl = '',
    mediaType = 'image', // 'image' | 'video' | 'document' | 'audio'
    sampleContact = {
        name: 'John Doe',
        phone_number: '+919876543210',
        city: 'Mumbai',
    }
}) {
    // Resolve body text
    const rawText = messageType === 'template' ? (template?.body_text || template?.text || '') : textContent;
    const previewBody = resolvePreviewText(rawText, variables, sampleContact);

    // Media Renderer by Type
    const renderMediaPreview = () => {
        if (!mediaUrl) return null;

        switch (mediaType) {
            case 'video':
                return (
                    <div className="bg-black rounded-lg overflow-hidden relative max-h-48 flex items-center justify-center border border-black/10">
                        <video src={mediaUrl} controls className="max-h-48 w-full object-cover" />
                    </div>
                );
            case 'document':
                return (
                    <div className="flex items-center gap-3 p-3 bg-emerald-50 text-emerald-900 rounded-lg border border-emerald-200">
                        <span className="text-2xl">📄</span>
                        <div className="overflow-hidden flex-1">
                            <p className="text-xs font-bold truncate">Attachment_Document.pdf</p>
                            <p className="text-[10px] text-emerald-700">PDF Document</p>
                        </div>
                    </div>
                );
            case 'audio':
                return (
                    <div className="flex items-center gap-3 p-3 bg-gray-100 rounded-lg border border-gray-200">
                        <span className="text-xl">🎵</span>
                        <audio src={mediaUrl} controls className="w-full h-8" />
                    </div>
                );
            case 'image':
            default:
                return (
                    <div className="rounded-lg overflow-hidden border border-black/10 max-h-48">
                        <img src={mediaUrl} alt="Campaign Media" className="max-h-48 w-full object-cover" />
                    </div>
                );
        }
    };

    return (
        <div className="w-full max-w-sm mx-auto bg-gray-900 p-4 rounded-[40px] shadow-2xl border-4 border-gray-800 relative">
            {/* Phone Speaker Notch */}
            <div className="w-28 h-4 bg-gray-800 rounded-full mx-auto mb-3 flex items-center justify-center">
                <div className="w-12 h-1.5 bg-gray-700 rounded-full"></div>
            </div>

            {/* WhatsApp App Header */}
            <div className="bg-[#008069] text-white p-3 rounded-t-2xl flex items-center gap-3 shadow-xs">
                <div className="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center font-bold text-xs">
                    {(sampleContact.name || 'C').substring(0, 2).toUpperCase()}
                </div>
                <div className="flex-1 overflow-hidden">
                    <p className="text-xs font-bold truncate">{sampleContact.name || 'Sample Customer'}</p>
                    <p className="text-[9px] text-emerald-100 font-mono">{sampleContact.phone_number || '+919876543210'}</p>
                </div>
                <span className="text-xs">🔒</span>
            </div>

            {/* Chat Screen Background */}
            <div className="bg-[#efeae2] p-3 rounded-b-2xl min-h-[360px] max-h-[460px] overflow-y-auto space-y-2 flex flex-col justify-end">
                {/* Outbound Campaign Message Bubble */}
                <div className="bg-[#d9fdd3] text-gray-900 rounded-xl p-3 shadow-xs max-w-[92%] self-end rounded-tr-xs border border-emerald-200/50 space-y-2">
                    
                    {/* Header Media */}
                    {renderMediaPreview()}

                    {/* Resolved Text Body */}
                    {previewBody ? (
                        <p className="text-xs whitespace-pre-wrap leading-relaxed break-words font-normal">
                            {previewBody}
                        </p>
                    ) : (
                        <p className="text-xs italic text-gray-400">
                            {messageType === 'template' ? 'Select a template to preview...' : 'Type message text...'}
                        </p>
                    )}

                    {/* Buttons if template has interactive buttons */}
                    {template?.buttons && template.buttons.length > 0 && (
                        <div className="pt-2 border-t border-emerald-300/40 space-y-1">
                            {template.buttons.map((btn, i) => (
                                <div key={i} className="py-1.5 px-3 bg-white/80 text-[#00a884] text-xs font-bold rounded-lg text-center shadow-xs border border-emerald-100">
                                    {btn.text || btn.label}
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Timestamp Footer */}
                    <div className="flex items-center justify-end gap-1 text-[9px] text-emerald-800/60 pt-0.5">
                        <span>{new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                        <span className="text-blue-500 font-bold">✓✓</span>
                    </div>
                </div>
            </div>

            {/* Phone Home Bar */}
            <div className="w-20 h-1 bg-gray-700 rounded-full mx-auto mt-3"></div>
        </div>
    );
}
