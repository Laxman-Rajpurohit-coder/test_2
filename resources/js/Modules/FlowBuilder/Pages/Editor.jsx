import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Editor({ flow }) {
    const { errors } = usePage().props;

    const [nodes, setNodes] = useState(flow.graph?.nodes || [
        {
            id: 'node_start_1',
            type: 'message',
            data: { text: 'Welcome to our WhatsApp service!', is_start: true }
        }
    ]);
    const [edges, setEdges] = useState(flow.graph?.edges || []);
    const [selectedNodeId, setSelectedNodeId] = useState(nodes[0]?.id || null);
    const [successMessage, setSuccessMessage] = useState(null);
    const [isSaving, setIsSaving] = useState(false);

    const selectedNode = nodes.find(n => n.id === selectedNodeId);

    const handleSave = () => {
        setSuccessMessage(null);
        setIsSaving(true);

        // 🛡️ Proper Inertia PUT payload transmission
        router.put(route('flows.update', flow.id), {
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
            case 'message':
                defaultData = { text: 'New WhatsApp message text' };
                break;
            case 'question':
                defaultData = { text: 'What is your email?', variable_name: 'user_email' };
                break;
            case 'condition':
                defaultData = {
                    rules: [
                        { variable: 'user_email', operator: 'contains', value: '@', target_node_id: '' }
                    ]
                };
                break;
            case 'api_call':
                defaultData = {
                    url: 'https://api.github.com/users/octocat',
                    method: 'GET',
                    response_variable: 'user_data'
                };
                break;
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
                return {
                    ...node,
                    data: { ...node.data, [key]: value }
                };
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
        const newRule = { variable: '', operator: 'equals', value: '', target_node_id: '' };
        updateSelectedNodeData('rules', [...currentRules, newRule]);
    };

    const removeConditionRule = (index) => {
        if (!selectedNode || selectedNode.type !== 'condition') return;
        const currentRules = selectedNode.data.rules || [];
        updateSelectedNodeData('rules', currentRules.filter((_, idx) => idx !== index));
    };

    const removeNode = (id) => {
        if (nodes.length <= 1) {
            alert('A flow must have at least one node.');
            return;
        }

        // 1. Remove Node & Associated Edges
        setNodes(prev => prev
            .filter(n => n.id !== id)
            // 2. Clean up dangling references in condition rules
            .map(node => {
                if (node.type === 'condition' && node.data?.rules) {
                    const cleanedRules = node.data.rules.map(rule => {
                        if (rule.target_node_id === id) {
                            return { ...rule, target_node_id: '' }; // Clear reference
                        }
                        return rule;
                    });
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
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('flows.index')}
                            className="p-1.5 rounded-lg text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition-colors"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                            </svg>
                        </Link>
                        <div>
                            <h2 className="text-xl font-bold text-gray-900 flex items-center gap-2">
                                {flow.name}
                                <span className={`w-2 h-2 rounded-full ${flow.is_active ? 'bg-emerald-400 animate-pulse' : 'bg-slate-600'}`} />
                            </h2>
                            <p className="text-xs text-gray-500">Visual Flow Graph Editor</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            onClick={handleSave}
                            disabled={isSaving}
                            className="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 rounded-lg shadow-sm transition-all"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                            {isSaving ? 'Validating & Saving...' : 'Save Flow'}
                        </button>
                    </div>
                </div>
            }
        >
            <Head title={`Editing Flow - ${flow.name}`} />

            <div className="h-[calc(100vh-8rem)] flex flex-col">
                {/* 🛡️ Save-Time Validation / SSRF Error Banner */}
                {errors?.graph && (
                    <div className="bg-rose-500/10 border-b border-rose-500/30 px-6 py-3 flex items-center justify-between text-rose-300 text-sm font-medium">
                        <div className="flex items-center gap-2">
                            <svg className="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>Validation Error: {errors.graph}</span>
                        </div>
                    </div>
                )}

                {successMessage && (
                    <div className="bg-emerald-500/10 border-b border-emerald-500/30 px-6 py-3 text-emerald-300 text-sm font-medium flex items-center gap-2">
                        <span>✓ {successMessage}</span>
                    </div>
                )}

                <div className="flex-1 flex overflow-hidden">
                    {/* Left Canvas Toolbar / Palette */}
                    <div className="w-56 bg-white border-r border-gray-200 p-4 flex flex-col gap-4">
                        <span className="text-xs font-bold uppercase tracking-wider text-gray-500">
                            Add Nodes
                        </span>

                        <div className="space-y-2">
                            <button
                                onClick={() => addNode('message')}
                                className="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-gray-800 bg-gray-100/80 hover:bg-gray-100 border border-gray-300/60 rounded-xl transition-all text-left"
                            >
                                <span className="w-2 h-2 rounded-full bg-indigo-400" />
                                Message Node
                            </button>

                            <button
                                onClick={() => addNode('question')}
                                className="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-gray-800 bg-gray-100/80 hover:bg-gray-100 border border-gray-300/60 rounded-xl transition-all text-left"
                            >
                                <span className="w-2 h-2 rounded-full bg-sky-400" />
                                Question Node
                            </button>

                            <button
                                onClick={() => addNode('condition')}
                                className="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-gray-800 bg-gray-100/80 hover:bg-gray-100 border border-gray-300/60 rounded-xl transition-all text-left"
                            >
                                <span className="w-2 h-2 rounded-full bg-amber-400" />
                                Condition Node
                            </button>

                            <button
                                onClick={() => addNode('api_call')}
                                className="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-gray-800 bg-gray-100/80 hover:bg-gray-100 border border-gray-300/60 rounded-xl transition-all text-left"
                            >
                                <span className="w-2 h-2 rounded-full bg-emerald-400" />
                                REST API Node
                            </button>
                        </div>
                    </div>

                    {/* Middle Interactive Canvas */}
                    <div className="flex-1 bg-gray-50 p-6 overflow-auto space-y-4">
                        <div className="flex items-center justify-between text-xs text-gray-500 mb-2">
                            <span>Flow Sequence Graph</span>
                            <span>Click a node to configure properties</span>
                        </div>

                        <div className="space-y-4 max-w-2xl mx-auto">
                            {nodes.map((node, index) => {
                                const isSelected = node.id === selectedNodeId;

                                return (
                                    <React.Fragment key={node.id}>
                                        <div
                                            onClick={() => setSelectedNodeId(node.id)}
                                            className={`cursor-pointer bg-white border rounded-2xl p-4 transition-all relative ${
                                                isSelected
                                                    ? 'border-indigo-500 ring-2 ring-indigo-500/20 shadow-lg shadow-indigo-500/10'
                                                    : 'border-gray-200 hover:border-gray-300'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between mb-2">
                                                <div className="flex items-center gap-2">
                                                    <span className={`px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-md border ${
                                                        node.type === 'message' ? 'bg-indigo-500/10 text-indigo-600 border-indigo-500/20' :
                                                        node.type === 'question' ? 'bg-sky-500/10 text-sky-600 border-sky-500/20' :
                                                        node.type === 'condition' ? 'bg-amber-500/10 text-amber-600 border-amber-500/20' :
                                                        'bg-emerald-500/10 text-emerald-600 border-emerald-500/20'
                                                    }`}>
                                                        {node.type}
                                                    </span>
                                                    <span className="text-xs font-mono text-gray-500">{node.id}</span>
                                                    {node.data?.is_start && (
                                                        <span className="text-[10px] bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded">Start</span>
                                                    )}
                                                </div>

                                                <button
                                                    onClick={(e) => { e.stopPropagation(); removeNode(node.id); }}
                                                    className="text-gray-400 hover:text-rose-600 p-1 text-xs"
                                                >
                                                    ✕
                                                </button>
                                            </div>

                                            <div className="text-sm text-gray-800 font-medium">
                                                {node.type === 'message' && (node.data.text || 'Empty message')}
                                                {node.type === 'question' && `Prompt: "${node.data.text}" ➔ Save to: {{${node.data.variable_name}}}`}
                                                {node.type === 'condition' && (
                                                    <div className="space-y-1">
                                                        <div>Branching on rules ({node.data.rules?.length || 0}):</div>
                                                        {(node.data.rules || []).map((r, i) => (
                                                            <div key={i} className="text-xs font-mono text-amber-600/90 pl-2 border-l border-amber-500/30">
                                                                If {r.variable || 'var'} {r.operator} "{r.value}" ➔ Target: [{r.target_node_id || 'None'}]
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                                {node.type === 'api_call' && `${node.data.method || 'GET'} ${node.data.url} ➔ {{${node.data.response_variable}}}`}
                                            </div>
                                        </div>

                                        {index < nodes.length - 1 && (
                                            <div className="flex justify-center my-1">
                                                <div className="w-0.5 h-6 bg-gray-100 flex items-center justify-center">
                                                    <span className="text-gray-300 text-xs">↓</span>
                                                </div>
                                            </div>
                                        )}
                                    </React.Fragment>
                                );
                            })}
                        </div>
                    </div>

                    {/* Right Inspector Side-Panel */}
                    {selectedNode && (
                        <div className="w-80 bg-white border-l border-gray-200 p-5 overflow-y-auto space-y-5">
                            <div>
                                <h3 className="text-sm font-bold text-gray-900">Node Inspector</h3>
                                <p className="text-xs text-gray-500 font-mono">{selectedNode.id}</p>
                            </div>

                            {/* Message Config */}
                            {selectedNode.type === 'message' && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1.5">
                                        Message Text Template
                                    </label>
                                    <textarea
                                        rows={4}
                                        value={selectedNode.data.text || ''}
                                        onChange={(e) => updateSelectedNodeData('text', e.target.value)}
                                        placeholder="e.g. Hello {{session.variables.user_name}}!"
                                        className="w-full bg-gray-50 border border-gray-200 rounded-lg p-2.5 text-xs text-gray-800 focus:outline-none focus:border-indigo-500"
                                    />
                                </div>
                            )}

                            {/* Question Config */}
                            {selectedNode.type === 'question' && (
                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 mb-1.5">
                                            Question Prompt
                                        </label>
                                        <textarea
                                            rows={3}
                                            value={selectedNode.data.text || ''}
                                            onChange={(e) => updateSelectedNodeData('text', e.target.value)}
                                            placeholder="What is your email?"
                                            className="w-full bg-gray-50 border border-gray-200 rounded-lg p-2.5 text-xs text-gray-800 focus:outline-none focus:border-indigo-500"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 mb-1.5">
                                            Store Response Variable Name
                                        </label>
                                        <input
                                            type="text"
                                            value={selectedNode.data.variable_name || ''}
                                            onChange={(e) => updateSelectedNodeData('variable_name', e.target.value)}
                                            placeholder="user_email"
                                            className="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-800 focus:outline-none focus:border-indigo-500"
                                        />
                                    </div>
                                </div>
                            )}

                            {/* Condition Config */}
                            {selectedNode.type === 'condition' && (
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-semibold text-gray-700">Branching Rules</span>
                                        <button
                                            type="button"
                                            onClick={addConditionRule}
                                            className="text-[11px] font-semibold text-indigo-600 hover:text-indigo-300"
                                        >
                                            + Add Rule
                                        </button>
                                    </div>

                                    {(selectedNode.data.rules || []).map((rule, idx) => (
                                        <div key={idx} className="bg-gray-50 border border-gray-200 rounded-xl p-3 space-y-2 relative">
                                            <div className="flex items-center justify-between text-[11px] font-medium text-gray-500">
                                                <span>Rule #{idx + 1}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => removeConditionRule(idx)}
                                                    className="text-gray-400 hover:text-rose-600"
                                                >
                                                    Remove
                                                </button>
                                            </div>

                                            <input
                                                type="text"
                                                value={rule.variable || ''}
                                                onChange={(e) => updateConditionRule(idx, 'variable', e.target.value)}
                                                placeholder="Variable name (e.g. user_email)"
                                                className="w-full bg-white border border-gray-200 rounded px-2 py-1 text-xs text-gray-800"
                                            />
                                            <select
                                                value={rule.operator || 'equals'}
                                                onChange={(e) => updateConditionRule(idx, 'operator', e.target.value)}
                                                className="w-full bg-white border border-gray-200 rounded px-2 py-1 text-xs text-gray-800"
                                            >
                                                <option value="equals">equals</option>
                                                <option value="contains">contains</option>
                                                <option value="greater_than">greater_than</option>
                                            </select>
                                            <input
                                                type="text"
                                                value={rule.value || ''}
                                                onChange={(e) => updateConditionRule(idx, 'value', e.target.value)}
                                                placeholder="Expected value"
                                                className="w-full bg-white border border-gray-200 rounded px-2 py-1 text-xs text-gray-800"
                                            />

                                            {/* 🎯 Target Node ID Select Dropdown */}
                                            <div>
                                                <label className="block text-[10px] text-gray-500 mb-1">Target Branch Node</label>
                                                <select
                                                    value={rule.target_node_id || ''}
                                                    onChange={(e) => updateConditionRule(idx, 'target_node_id', e.target.value)}
                                                    className="w-full bg-white border border-gray-200 rounded px-2 py-1 text-xs text-amber-300 font-mono"
                                                >
                                                    <option value="">-- Select Target Node --</option>
                                                    {nodes.filter(n => n.id !== selectedNode.id).map(n => (
                                                        <option key={n.id} value={n.id}>
                                                            [{n.type.toUpperCase()}] {n.id} - {n.data.text || n.data.url || n.id}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {/* REST API Call Config */}
                            {selectedNode.type === 'api_call' && (
                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 mb-1.5">
                                            HTTP Method & Target URL
                                        </label>
                                        <div className="flex gap-2">
                                            <select
                                                value={selectedNode.data.method || 'GET'}
                                                onChange={(e) => updateSelectedNodeData('method', e.target.value)}
                                                className="bg-gray-50 border border-gray-200 rounded-lg px-2 py-2 text-xs text-gray-800"
                                            >
                                                <option value="GET">GET</option>
                                                <option value="POST">POST</option>
                                            </select>
                                            <input
                                                type="url"
                                                value={selectedNode.data.url || ''}
                                                onChange={(e) => updateSelectedNodeData('url', e.target.value)}
                                                placeholder="https://api.example.com/data"
                                                className="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-800 focus:outline-none focus:border-indigo-500"
                                            />
                                        </div>
                                        <p className="text-[10px] text-gray-400 mt-1">
                                            Internal IPs and localhost hostnames are strictly blocked for SSRF security.
                                        </p>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 mb-1.5">
                                            Response Variable Name
                                        </label>
                                        <input
                                            type="text"
                                            value={selectedNode.data.response_variable || 'api_response'}
                                            onChange={(e) => updateSelectedNodeData('response_variable', e.target.value)}
                                            placeholder="api_response"
                                            className="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-800 focus:outline-none focus:border-indigo-500"
                                        />
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
