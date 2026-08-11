import React from 'react';

export default function TemplateSelector({ 
    approvedTemplates, 
    selectedTemplate, 
    templateVariables, 
    onTemplateSelect, 
    onVariableChange, 
    mode = 'static', // 'static' or 'dynamic'
    availableContactFields = []
}) {
    return (
        <div className="space-y-4 animate-fade-in">
            <div>
                <label className="block text-sm font-medium text-[#8696a0] mb-1">Select Template</label>
                <select 
                    className="w-full bg-[#2a3942] border border-[#3b4a54] text-[#e9edef] rounded-lg px-3 py-2 outline-none focus:border-[#00a884] focus:ring-1 focus:ring-[#00a884]"
                    onChange={onTemplateSelect}
                    value={selectedTemplate ? selectedTemplate.name : ""}
                >
                    <option value="" disabled>-- Choose a template --</option>
                    {(approvedTemplates || []).map(t => (
                        <option key={t.id} value={t.name}>{t.name} ({t.language})</option>
                    ))}
                </select>
            </div>

            {selectedTemplate && (
                <div className="space-y-4 animate-fade-in">
                    <div className="bg-[#202c33] p-3 rounded-lg border border-[#222d34]">
                        <div className="text-xs text-[#00a884] font-medium mb-2">PREVIEW</div>
                        <div className="text-sm text-[#e9edef] whitespace-pre-wrap">
                            {(() => {
                                try {
                                    const components = typeof selectedTemplate.components === 'string' ? JSON.parse(selectedTemplate.components) : (selectedTemplate.components || []);
                                    const body = components.find(c => c.type === 'BODY' || c.type === 'body');
                                    if (body && body.text) {
                                        return body.text.replace(/\{\{(\d+)\}\}/g, (match, p1) => {
                                            return templateVariables[p1] ? `[${templateVariables[p1]}]` : match;
                                        });
                                    }
                                    return 'No text body found.';
                                } catch(e) { return 'Error rendering preview'; }
                            })()}
                        </div>
                    </div>

                    {Object.keys(templateVariables).length > 0 && (
                        <div>
                            <label className="block text-sm font-medium text-[#8696a0] mb-1">
                                {mode === 'dynamic' ? 'Map Variables to Contact Fields' : 'Fill Variables'}
                            </label>
                            {mode === 'dynamic' && (
                                <p className="text-xs text-[#8696a0] mb-3">
                                    Select the contact field to dynamically fill this variable for each recipient.
                                </p>
                            )}
                            <div className="space-y-2">
                                {Object.keys(templateVariables).sort((a,b) => parseInt(a) - parseInt(b)).map(num => (
                                    <div key={num} className="flex items-center gap-2">
                                        <span className="text-[#00a884] font-mono text-xs bg-[#2a3942] px-2 py-1 rounded">{'{{' + num + '}}'}</span>
                                        
                                        {mode === 'dynamic' ? (
                                            <select
                                                value={templateVariables[num]}
                                                onChange={(e) => onVariableChange(num, e.target.value)}
                                                className="flex-1 bg-[#2a3942] border border-[#3b4a54] text-[#e9edef] rounded-lg px-3 py-1.5 text-sm outline-none focus:border-[#00a884]"
                                                required
                                            >
                                                <option value="" disabled>-- Select a field --</option>
                                                {availableContactFields.map(field => (
                                                    <option key={field} value={field}>{field}</option>
                                                ))}
                                            </select>
                                        ) : (
                                            <input 
                                                type="text"
                                                value={templateVariables[num]}
                                                onChange={(e) => onVariableChange(num, e.target.value)}
                                                placeholder={`Value for {{${num}}}`}
                                                className="flex-1 bg-[#2a3942] border border-[#3b4a54] text-[#e9edef] rounded-lg px-3 py-1.5 text-sm outline-none focus:border-[#00a884]"
                                                required
                                            />
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
