import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Workspace({ flows, active_flow }) {
    const { errors } = usePage().props;

    // --- Master List State ---
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const { data: createData, setData: setCreateData, post: createPost, processing: createProcessing, errors: createErrors, reset: createReset } = useForm({
        name: '',
    });

    // --- Detail Canvas State ---
    const [nodes, setNodes] = useState([]);
    const [edges, setEdges] = useState([]);
    const [selectedNodeId, setSelectedNodeId] = useState(null);
    const [successMessage, setSuccessMessage] = useState(null);
    const [isSaving, setIsSaving] = useState(false);

    // Sync active flow data to state when URL changes
    useEffect(() => {
        if (active_flow) {
            const initialNodes = active_flow.graph?.nodes || [
                { id: 'node_start_1', type: 'message', data: { text: 'Welcome to our WhatsApp service!', is_start: true } }
            ];
            setNodes(initialNodes);
            setEdges(active_flow.graph?.edges || []);
            setSelectedNodeId(initialNodes[0]?.id || null);
        } else {
            setNodes([]);
            setEdges([]);
            setSelectedNodeId(null);
        }
    }, [active_flow?.id]);

    // 🛡️ Data Loss Prevention: Dirty State Guard
    useEffect(() => {
        const removeListener = router.on('before', (event) => {
            if (!active_flow) return;

            const currentGraph = JSON.stringify({ nodes, edges });
            const originalGraph = JSON.stringify(active_flow.graph);

            // Compare current state against the initial state we got from the server
            if (currentGraph !== originalGraph) {
                if (!confirm('You have unsaved changes. Discard them and switch flows?')) {
                    event.preventDefault(); // Block Inertia navigation
                }
            }
        });

        return () => removeListener();
    }, [nodes, edges, active_flow]);


    // --- Master List Handlers ---
    const handleCreate = (e) => {
        e.preventDefault();
        createPost(route('flows.store'), {
            onSuccess: () => {
                createReset();
                setIsCreateOpen(false);
            },
        });
    };

    const toggleActive = (flow) => {
        router.put(route('flows.update', flow.id), {
            is_active: !flow.is_active,
        });
    };

    const handleDelete = (id) => {
        if (confirm('Are you sure you want to delete this flow? Active customer sessions will be terminated.')) {
            router.delete(route('flows.destroy', id));
        }
    };


    // --- Detail Canvas Handlers ---
    const selectedNode = nodes.find(n => n.id === selectedNodeId);

    const handleSave = () => {
        if (!active_flow) return;
        setSuccessMessage(null);
        setIsSaving(true);

        router.put(route('flows.update', active_flow.id), {
            graph: { nodes, edges }
        }, {
            onSuccess: () => {
                setIsSaving(false);
                setSuccessMessage('Flow graph saved & validated successfully.');
                setTimeout(() => setSuccessMessage(null), 4000);
            },
            onError: () => {
                setIsSaving(false);
            }
        });
    };

    const addNode = (type) => {
        const id = `node_${type}_${Date.now()}`;
        let defaultData = {};

        switch (type) {
            case 'message': defaultData = { text: 'New WhatsApp message text' }; break;
            case 'question': defaultData = { text: 'What is your email?', variable_name: 'user_email' }; break;
            case 'condition': defaultData = { rules: [{ variable: 'user_email', operator: 'contains', value: '@', target_node_id: '' }] }; break;
            case 'api_call': defaultData = { url: 'https://api.github.com/users/octocat', method: 'GET', response_variable: 'user_data' }; break;
        }

        const newNode = { id, type, data: defaultData };
        setNodes(prev => [...prev, newNode]);
        setSelectedNodeId(id);

        if (selectedNodeId) {
            setEdges(prev => [...prev.filter(e => e.source !== selectedNodeId), { source: selectedNodeId, target: id }]);
        }
    };

    const updateSelectedNodeData = (key, value) => {
        setNodes(prev => prev.map(node => {
            if (node.id === selectedNodeId) {
                return { ...node, data: { ...node.data, [key]: value } };
            }
            return node;
        }));
    };

    const updateConditionRule = (index, field, value) => {
        setNodes(prev => prev.map(node => {
            if (node.id === selectedNodeId && node.type === 'condition') {
                const rules = [...(node.data.rules || [])];
                rules[index] = { ...rules[index], [field]: value };
                return { ...node, data: { ...node.data, rules } };
            }
            return node;
        }));
    };

    const addConditionRule = () => {
        if (!selectedNode || selectedNode.type !== 'condition') return;
        const currentRules = selectedNode.data.rules || [];
        updateSelectedNodeData('rules', [...currentRules, { variable: '', operator: 'equals', value: '', target_node_id: '' }]);
    };

    const removeConditionRule = (index) => {
        if (!selectedNode || selectedNode.type !== 'condition') return;
        updateSelectedNodeData('rules', (selectedNode.data.rules || []).filter((_, idx) => idx !== index));
    };

    const removeNode = (id) => {
        if (nodes.length <= 1) {
            alert('A flow must have at least one node.');
            return;
        }

        setNodes(prev => prev
            .filter(n => n.id !== id)
            .map(node => {
                if (node.type === 'condition' && node.data?.rules) {
                    const cleanedRules = node.data.rules.map(rule => rule.target_node_id === id ? { ...rule, target_node_id: '' } : rule);
                    return { ...node, data: { ...node.data, rules: cleanedRules } };
                }
                return node;
            })
        );
        setEdges(prev => prev.filter(e => e.source !== id && e.target !== id));

        if (selectedNodeId === id) {
            const remaining = nodes.filter(n => n.id !== id);
            setSelectedNodeId(remaining[0]?.id || null);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={active_flow ? `Editing - ${active_flow.name}` : "Flow Builder"} />

            {/* Header Bar */}
            <div className="h-16 bg-white border-b border-gray-200 px-6 flex items-center justify-between shrink-0">
                <div className="flex items-center">
                    <Link href={route('dashboard')} className="mr-4 p-2 -ml-2 rounded-lg text-gray-400 hover:text-gray-900 hover:bg-gray-100 transition-colors" title="Back to Dashboard">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 className="text-xl font-bold tracking-tight text-gray-900 flex items-center gap-2">
                        Flow Builder
                        {active_flow && (
                            <>
                                <span className="text-gray-300">/</span>
                                <span className="text-indigo-600">{active_flow.name}</span>
                                <span className={`w-2 h-2 rounded-full ${active_flow.is_active ? 'bg-emerald-400 animate-pulse' : 'bg-slate-400'}`} />
                            </>
                        )}
                    </h2>
                </div>
                
                <div className="flex gap-3">
                    <button
                        onClick={() => setIsCreateOpen(true)}
                        className="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-white bg-slate-800 rounded-lg shadow-sm hover:bg-slate-700 transition-all"
                    >
                        + Create Flow
                    </button>

                    {active_flow && (
                        <button
                            onClick={handleSave}
                            disabled={isSaving}
                            className="inline-flex items-center gap-2 px-4 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 rounded-lg shadow-sm transition-all"
                        >
                            {isSaving ? 'Saving...' : 'Save Flow'}
                        </button>
                    )}
                </div>
            </div>

            {/* Error & Success Banners */}
            {errors?.graph && (
                <div className="bg-rose-500/10 border-b border-rose-500/30 px-6 py-2 flex items-center gap-2 text-rose-600 text-xs font-bold shrink-0">
                    <span>Validation Error: {errors.graph}</span>
                </div>
            )}
            {successMessage && (
                <div className="bg-emerald-500/10 border-b border-emerald-500/30 px-6 py-2 flex items-center gap-2 text-emerald-600 text-xs font-bold shrink-0">
                    <span>✓ {successMessage}</span>
                </div>
            )}

            <div className="h-[calc(100vh-4rem)] flex overflow-hidden">
                
                {/* ⬅️ Left Sidebar: Flow Master List */}
                <div className="w-80 bg-white border-r border-gray-200 flex flex-col overflow-hidden">
                    <div className="p-4 border-b border-gray-100 bg-gray-50/50">
                        <input
                            type="text"
                            placeholder="Search flows..."
                            className="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                    
                    <div className="flex-1 overflow-y-auto divide-y divide-gray-100 p-2 space-y-1">
                        {flows.map((flow) => {
                            const isActiveView = active_flow?.id === flow.id;
                            return (
                                <div
                                    key={flow.id}
                                    className={`group rounded-xl transition-all relative flex flex-col ${isActiveView ? 'bg-indigo-50 border border-indigo-100 shadow-inner' : 'hover:bg-gray-50 border border-transparent'}`}
                                >
                                    {/* Link covers the whole card to make it clickable */}
                                    <Link href={route('flows.show', flow.id)} className="p-3 cursor-pointer">
                                        <div className="flex items-center justify-between mb-1">
                                            <h3 className={`text-sm font-bold truncate ${isActiveView ? 'text-indigo-900' : 'text-gray-900'}`}>
                                                {flow.name}
                                            </h3>
                                            <span className={`w-2 h-2 rounded-full ${flow.is_active ? 'bg-emerald-400' : 'bg-gray-300'}`} />
                                        </div>
                                        <div className="flex items-center justify-between text-[10px] font-medium text-gray-500">
                                            <span>{flow.active_sessions} active sessions</span>
                                            <span>{flow.created_at}</span>
                                        </div>
                                    </Link>
                                </div>
                            );
                        })}

                        {flows.length === 0 && (
                            <div className="p-8 text-center text-sm text-gray-400">
                                No flows created yet.
                            </div>
                        )}
                    </div>
                </div>

                {/* ➡️ Right Area: Detail Canvas or Empty State */}
                <div className="flex-1 bg-gray-50 flex flex-col">
                    {!active_flow ? (
                        <div className="flex-1 flex flex-col items-center justify-center text-gray-400">
                            <svg className="w-16 h-16 mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <p className="text-lg font-bold text-gray-500">Select a Flow to Edit</p>
                            <p className="text-sm mt-1">Or create a new one from the sidebar.</p>
                        </div>
                    ) : (
                        <div className="flex-1 flex overflow-hidden">
                            {/* Canvas Palette */}
                            <div className="w-48 bg-white border-r border-gray-200 p-4 flex flex-col gap-3">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Add Nodes</span>
                                <button onClick={() => addNode('message')} className="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-gray-700 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg transition-all text-left"><span className="w-2 h-2 rounded-full bg-indigo-400" />Message</button>
                                <button onClick={() => addNode('question')} className="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-gray-700 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg transition-all text-left"><span className="w-2 h-2 rounded-full bg-sky-400" />Question</button>
                                <button onClick={() => addNode('condition')} className="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-gray-700 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg transition-all text-left"><span className="w-2 h-2 rounded-full bg-amber-400" />Condition</button>
                                <button onClick={() => addNode('api_call')} className="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-gray-700 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg transition-all text-left"><span className="w-2 h-2 rounded-full bg-emerald-400" />API Call</button>
                            </div>

                            {/* Center Graph Canvas */}
                            <div className="flex-1 overflow-auto p-8">
                                <div className="max-w-xl mx-auto space-y-6">
                                    {nodes.map((node, index) => {
                                        const isSelected = node.id === selectedNodeId;
                                        return (
                                            <React.Fragment key={node.id}>
                                                <div
                                                    onClick={() => setSelectedNodeId(node.id)}
                                                    className={`cursor-pointer bg-white border rounded-2xl p-4 transition-all relative ${isSelected ? 'border-indigo-500 ring-2 ring-indigo-500/20 shadow-lg' : 'border-gray-200 hover:border-gray-300'}`}
                                                >
                                                    <div className="flex items-center justify-between mb-3">
                                                        <div className="flex items-center gap-2">
                                                            <span className={`px-2 py-0.5 text-[10px] font-bold uppercase rounded-md border ${
                                                                node.type === 'message' ? 'bg-indigo-50 text-indigo-600 border-indigo-200' :
                                                                node.type === 'question' ? 'bg-sky-50 text-sky-600 border-sky-200' :
                                                                node.type === 'condition' ? 'bg-amber-50 text-amber-600 border-amber-200' :
                                                                'bg-emerald-50 text-emerald-600 border-emerald-200'
                                                            }`}>{node.type}</span>
                                                            {node.data?.is_start && <span className="text-[10px] bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded">Start</span>}
                                                        </div>
                                                        <button onClick={(e) => { e.stopPropagation(); removeNode(node.id); }} className="text-gray-400 hover:text-rose-500 font-bold px-2 py-1 text-xs">✕</button>
                                                    </div>
                                                    <div className="text-xs text-gray-800 font-medium leading-relaxed">
                                                        {node.type === 'message' && (node.data.text || 'Empty message...')}
                                                        {node.type === 'question' && `Prompt: "${node.data.text}" ➔ Save to: {{${node.data.variable_name}}}`}
                                                        {node.type === 'condition' && (
                                                            <div className="space-y-1">
                                                                <div>Branching on rules ({node.data.rules?.length || 0}):</div>
                                                                {(node.data.rules || []).map((r, i) => (
                                                                    <div key={i} className="font-mono text-[10px] text-amber-700 pl-2 border-l border-amber-300">If {r.variable || 'var'} {r.operator} "{r.value}" ➔ [{r.target_node_id || 'None'}]</div>
                                                                ))}
                                                            </div>
                                                        )}
                                                        {node.type === 'api_call' && `${node.data.method || 'GET'} ${node.data.url} ➔ {{${node.data.response_variable}}}`}
                                                    </div>
                                                </div>
                                                {index < nodes.length - 1 && (
                                                    <div className="flex justify-center my-2">
                                                        <div className="w-0.5 h-6 bg-gray-200" />
                                                    </div>
                                                )}
                                            </React.Fragment>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Canvas Inspector */}
                            {selectedNode && (
                                <div className="w-72 bg-white border-l border-gray-200 p-4 overflow-y-auto space-y-5">
                                    <div>
                                        <h3 className="text-sm font-bold text-gray-900">Inspector</h3>
                                        <p className="text-[10px] text-gray-400 font-mono mt-1">{selectedNode.id}</p>
                                    </div>

                                    {selectedNode.type === 'message' && (
                                        <div>
                                            <label className="block text-xs font-bold text-gray-700 mb-1.5">Message Text Template</label>
                                            <textarea rows={4} value={selectedNode.data.text || ''} onChange={(e) => updateSelectedNodeData('text', e.target.value)} className="w-full border border-gray-200 rounded-lg p-2 text-xs focus:ring-indigo-500" />
                                        </div>
                                    )}

                                    {selectedNode.type === 'question' && (
                                        <div className="space-y-4">
                                            <div>
                                                <label className="block text-xs font-bold text-gray-700 mb-1.5">Question Prompt</label>
                                                <textarea rows={3} value={selectedNode.data.text || ''} onChange={(e) => updateSelectedNodeData('text', e.target.value)} className="w-full border border-gray-200 rounded-lg p-2 text-xs focus:ring-indigo-500" />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-gray-700 mb-1.5">Store Response Variable Name</label>
                                                <input type="text" value={selectedNode.data.variable_name || ''} onChange={(e) => updateSelectedNodeData('variable_name', e.target.value)} className="w-full border border-gray-200 rounded-lg p-2 text-xs focus:ring-indigo-500" />
                                            </div>
                                        </div>
                                    )}

                                    {selectedNode.type === 'condition' && (
                                        <div className="space-y-4">
                                            <div className="flex justify-between items-center"><span className="text-xs font-bold text-gray-700">Rules</span><button onClick={addConditionRule} className="text-[10px] text-indigo-600">+ Add</button></div>
                                            {(selectedNode.data.rules || []).map((rule, idx) => (
                                                <div key={idx} className="bg-gray-50 border border-gray-200 p-2 rounded-lg space-y-2">
                                                    <div className="flex justify-between items-center"><span className="text-[10px] text-gray-500">#{idx+1}</span><button onClick={() => removeConditionRule(idx)} className="text-[10px] text-rose-500">Remove</button></div>
                                                    <input type="text" placeholder="Variable" value={rule.variable || ''} onChange={(e) => updateConditionRule(idx, 'variable', e.target.value)} className="w-full border border-gray-200 rounded px-2 py-1 text-xs" />
                                                    <select value={rule.operator || 'equals'} onChange={(e) => updateConditionRule(idx, 'operator', e.target.value)} className="w-full border border-gray-200 rounded px-2 py-1 text-xs">
                                                        <option value="equals">equals</option>
                                                        <option value="contains">contains</option>
                                                    </select>
                                                    <input type="text" placeholder="Value" value={rule.value || ''} onChange={(e) => updateConditionRule(idx, 'value', e.target.value)} className="w-full border border-gray-200 rounded px-2 py-1 text-xs" />
                                                    <select value={rule.target_node_id || ''} onChange={(e) => updateConditionRule(idx, 'target_node_id', e.target.value)} className="w-full border border-gray-200 rounded px-2 py-1 text-xs font-mono text-amber-600">
                                                        <option value="">-- Target Node --</option>
                                                        {nodes.filter(n => n.id !== selectedNode.id).map(n => (<option key={n.id} value={n.id}>{n.id}</option>))}
                                                    </select>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {selectedNode.type === 'api_call' && (
                                        <div className="space-y-4">
                                            <div>
                                                <label className="block text-xs font-bold text-gray-700 mb-1.5">Target URL</label>
                                                <div className="flex gap-2">
                                                    <select value={selectedNode.data.method || 'GET'} onChange={(e) => updateSelectedNodeData('method', e.target.value)} className="border border-gray-200 rounded-lg px-2 text-xs"><option>GET</option><option>POST</option></select>
                                                    <input type="url" value={selectedNode.data.url || ''} onChange={(e) => updateSelectedNodeData('url', e.target.value)} className="flex-1 border border-gray-200 rounded-lg p-2 text-xs" />
                                                </div>
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-gray-700 mb-1.5">Response Variable</label>
                                                <input type="text" value={selectedNode.data.response_variable || ''} onChange={(e) => updateSelectedNodeData('response_variable', e.target.value)} className="w-full border border-gray-200 rounded-lg p-2 text-xs" />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {/* Create Flow Modal */}
            {isCreateOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
                    <div className="bg-white rounded-2xl w-full max-w-md p-6 space-y-5 shadow-2xl">
                        <div className="flex items-center justify-between"><h3 className="text-lg font-bold">New Flow</h3><button onClick={() => setIsCreateOpen(false)}>✕</button></div>
                        <form onSubmit={handleCreate} className="space-y-4">
                            <input type="text" value={createData.name} onChange={(e) => setCreateData('name', e.target.value)} placeholder="Flow Name" className="w-full border border-gray-200 rounded-lg p-3 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" required />
                            <div className="flex justify-end gap-3"><button type="button" onClick={() => setIsCreateOpen(false)} className="px-4 py-2 text-xs font-bold text-gray-600 bg-gray-100 rounded-lg">Cancel</button><button type="submit" disabled={createProcessing} className="px-4 py-2 text-xs font-bold text-white bg-indigo-600 rounded-lg">{createProcessing ? 'Wait...' : 'Create'}</button></div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
