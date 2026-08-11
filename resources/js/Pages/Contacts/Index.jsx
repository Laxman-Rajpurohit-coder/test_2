import React, { useState } from 'react';
import { Head, useForm, usePage, Link, router } from '@inertiajs/react';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import TemplateSelector from '@/Components/TemplateSelector';

class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null, errorInfo: null };
  }
  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }
  componentDidCatch(error, errorInfo) {
    this.setState({ errorInfo });
    console.error("Caught by ErrorBoundary:", error, errorInfo);
  }
  render() {
    if (this.state.hasError) {
      return (
        <div style={{ padding: '20px', background: '#fee2e2', color: '#991b1b', borderRadius: '8px', margin: '20px' }}>
          <h2 style={{ fontSize: '20px', fontWeight: 'bold' }}>A fatal React error occurred! Please copy this to Gemini:</h2>
          <pre style={{ marginTop: '10px', background: 'white', padding: '10px', overflowX: 'auto' }}>
            {this.state.error && this.state.error.toString()}
          </pre>
          <pre style={{ marginTop: '10px', background: 'white', padding: '10px', overflowX: 'auto', fontSize: '12px' }}>
            {this.state.errorInfo && this.state.errorInfo.componentStack}
          </pre>
        </div>
      );
    }
    return this.props.children;
  }
}

export default function ContactsIndex({ contacts, teamMembers = [], allTags = [], approvedTemplates = [], availableContactFields = [] }) {
    const { tenant_features, auth } = usePage().props;
    const isOwner = auth?.user?.role === 'owner';
    const [selectedContacts, setSelectedContacts] = useState([]);
    const [assignDropdownOpen, setAssignDropdownOpen] = useState(false);
    const [tagModalOpen, setTagModalOpen] = useState(false);
    const [tagIds, setTagIds] = useState([]);
    const [tagMode, setTagMode] = useState('add');
    const [importModalOpen, setImportModalOpen] = useState(false);
    const [addContactModalOpen, setAddContactModalOpen] = useState(false);
    const [globalAssignModalOpen, setGlobalAssignModalOpen] = useState(false);
    const [quickSendModalOpen, setQuickSendModalOpen] = useState(false);
    const [newTagName, setNewTagName] = useState('');
    const [isCreatingTag, setIsCreatingTag] = useState(false);
    const [isSubmittingTag, setIsSubmittingTag] = useState(false);
    const [selectedTags, setSelectedTags] = useState([]);

    const handleCreateTag = async (e) => {
        e.preventDefault();
        if (!newTagName.trim()) return;

        setIsCreatingTag(true);
        try {
            const res = await axios.post(route('contact-tags.store'), { name: newTagName.trim() });
            const createdTag = res.data;
            if (createdTag && createdTag.id) {
                setTagIds(prev => prev.includes(createdTag.id) ? prev : [...prev, createdTag.id]);
            }
            setNewTagName('');
            router.visit(route('contacts.index'), { preserveScroll: true });
        } catch (err) {
            console.error('Failed to create tag:', err);
        } finally {
            setIsCreatingTag(false);
        }
    };
    
    const { data, setData, post, processing, errors, reset } = useForm({
        file: null,
        assigned_user_id: '',
    });

    const addContactForm = useForm({
        name: '',
        phone_number: '',
        assigned_user_id: '',
    });

    const [qsContactIds, setQsContactIds] = useState([]);
    const [qsMessageType, setQsMessageType] = useState('template');
    const [qsTemplateName, setQsTemplateName] = useState('');
    const [qsTemplateLanguage, setQsTemplateLanguage] = useState('');
    const [qsTemplateVariables, setQsTemplateVariables] = useState({});
    const [qsSelectedTemplate, setQsSelectedTemplate] = useState(null);
    const [qsTextContent, setQsTextContent] = useState('');
    const [qsIsSubmitting, setQsIsSubmitting] = useState(false);
    const [qsErrors, setQsErrors] = useState({});

    const handleTemplateSelect = (e) => {
        const tplName = e.target.value;
        if (!tplName) {
            setQsSelectedTemplate(null);
            setQsTemplateName('');
            setQsTemplateLanguage('');
            setQsTemplateVariables({});
            return;
        }
        const tpl = (approvedTemplates || []).find(t => t.name === tplName);
        setQsSelectedTemplate(tpl);
        setQsTemplateName(tpl.name);
        setQsTemplateLanguage(tpl.language || 'en');
        
        const variables = {};
        try {
            const components = typeof tpl.components === 'string' ? JSON.parse(tpl.components) : (tpl.components || []);
            const body = components.find(c => c.type === 'BODY' || c.type === 'body');
            if (body && body.text) {
                const matches = body.text.match(/\{\{(\d+)\}\}/g);
                if (matches) {
                    const numbers = matches.map(m => parseInt(m.replace(/[^0-9]/g, '')));
                    const maxCount = Math.max(...numbers);
                    for (let i = 1; i <= maxCount; i++) {
                        variables[i] = '';
                    }
                }
            }
        } catch (err) {}
        setQsTemplateVariables(variables);
    };

    const handleAddContact = (e) => {
        e.preventDefault();
        addContactForm.post(route('contacts.store'), {
            onSuccess: () => {
                setAddContactModalOpen(false);
                addContactForm.reset();
            },
        });
    };

    const handleImport = (e) => {
        e.preventDefault();
        post(route('contacts.import'), {
            onSuccess: () => {
                setImportModalOpen(false);
                reset();
            },
        });
    };

    const toggleAll = (e) => {
        if (e.target.checked) {
            setSelectedContacts(contacts.data.map(c => c.id));
        } else {
            setSelectedContacts([]);
        }
    };

    const toggleContact = (id) => {
        setSelectedContacts(prev => 
            prev.includes(id) ? prev.filter(cId => cId !== id) : [...prev, id]
        );
    };

    const handleAssign = (userId) => {
        if (selectedContacts.length === 0) return;
        
        router.post(route('contacts.bulk-assign'), {
            contact_ids: selectedContacts,
            assigned_user_id: userId
        }, {
            onSuccess: () => {
                setSelectedContacts([]);
                setAssignDropdownOpen(false);
            }
        });
    };

    const handleGlobalAssign = (userId) => {
        router.post(route('contacts.bulk-assign-all'), {
            assigned_user_id: userId
        }, {
            onSuccess: () => {
                setGlobalAssignModalOpen(false);
            }
        });
    };

    const handleQuickSend = (e) => {
        e.preventDefault();
        const idsToSend = qsContactIds && qsContactIds.length > 0
            ? qsContactIds
            : selectedContacts;

        if (!idsToSend || idsToSend.length === 0) {
            alert('Please select at least one contact before sending.');
            return;
        }

        setQsIsSubmitting(true);
        setQsErrors({});
        
        router.post(route('contacts.quick-send'), {
            contact_ids: idsToSend,
            message_type: qsMessageType,
            template_name: qsTemplateName,
            template_language: qsTemplateLanguage,
            template_variable_map: qsTemplateVariables,
            text_content: qsTextContent
        }, {
            onSuccess: () => {
                setQuickSendModalOpen(false);
                setQsContactIds([]);
                setQsMessageType('template');
                setQsTemplateName('');
                setQsTemplateLanguage('');
                setQsTemplateVariables({});
                setQsSelectedTemplate(null);
                setQsTextContent('');
                setSelectedContacts([]);
            },
            onError: (errors) => {
                console.error('Validation errors:', errors);
                setQsErrors(errors);
            },
            onFinish: () => {
                setQsIsSubmitting(false);
            },
            preserveScroll: true
        });
    };

    const handleBulkTag = (e) => {
        e.preventDefault();
        const idsToTag = selectedContacts;

        if (!idsToTag || idsToTag.length === 0) {
            alert('Please select at least one contact.');
            return;
        }

        if (!tagIds || tagIds.length === 0) {
            alert('Please select at least one tag.');
            return;
        }

        setIsSubmittingTag(true);
        
        router.post(route('contacts.bulk-tag'), {
            contact_ids: idsToTag,
            tag_ids: tagIds,
            mode: tagMode
        }, {
            onSuccess: () => {
                setTagModalOpen(false);
                setTagIds([]);
                setTagMode('add');
                setSelectedContacts([]);
            },
            onError: (errs) => {
                console.error("Bulk Tag Errors:", errs);
                alert("Validation failed when modifying tags.");
            },
            onFinish: () => {
                setIsSubmittingTag(false);
            },
            preserveScroll: true
        });
    };

    const handleRemoveTag = (contactId, tagId) => {
        if (!confirm('Are you sure you want to remove this tag?')) return;
        
        router.post(route('contacts.bulk-tag'), {
            contact_ids: [contactId],
            tag_ids: [tagId],
            mode: 'remove'
        }, {
            preserveScroll: true
        });
    };

    const openQuickSend = (contactId = null) => {
        const targetIds = typeof contactId === 'string' ? [contactId] : selectedContacts;
        setQsContactIds(targetIds);
        setQsMessageType('template');
        setQsTemplateName('');
        setQsTemplateLanguage('');
        setQsTemplateVariables({});
        setQsSelectedTemplate(null);
        setQsTextContent('');
        setQsErrors({});
        setQuickSendModalOpen(true);
    };

    const openTagModal = () => {
        setTagIds([]);
        setTagModalOpen(true);
    };

    return (
        <ErrorBoundary>
        <AppLayout>
            <Head title="Contacts" />
            
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div className="flex items-center">
                    <Link href={route('dashboard')} className="mr-4 p-2 -ml-2 rounded-lg text-gray-400 hover:text-gray-900 hover:bg-gray-100 transition-colors" title="Back to Dashboard">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Contacts</h1>
                        <p className="text-sm text-gray-500 mt-1">Manage your contacts and custom fields.</p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-3 items-center">
                    {selectedContacts.length > 0 && (
                        <>
                            <button
                                onClick={openQuickSend}
                                className="px-4 py-2 bg-[#25D366] text-white rounded-lg font-bold shadow-sm transition hover:bg-[#1DA851] flex items-center gap-2"
                            >
                                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                                Quick Message ({selectedContacts.length})
                            </button>
                            <button
                                onClick={openTagModal}
                                className="px-4 py-2 bg-purple-50 text-purple-700 rounded-lg font-bold hover:bg-purple-100 transition border border-purple-200"
                            >
                                Tag Contacts
                            </button>
                        </>
                    )}
                    {isOwner && selectedContacts.length > 0 && (
                        <div className="relative">
                            <button
                                onClick={() => setAssignDropdownOpen(!assignDropdownOpen)}
                                className="px-4 py-2 bg-white text-gray-700 rounded-lg font-bold shadow-sm transition border border-gray-200 hover:bg-gray-50 flex items-center gap-2"
                            >
                                Assign to...
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                            
                            {assignDropdownOpen && (
                                <div className="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-100 z-10 py-1">
                                    <button 
                                        onClick={() => handleAssign(null)}
                                        className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                                    >
                                        <em>Unassign</em>
                                    </button>
                                    <div className="border-t border-gray-100 my-1"></div>
                                    {teamMembers.map(member => (
                                        <button 
                                            key={member.id}
                                            onClick={() => handleAssign(member.id)}
                                            className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition font-medium"
                                        >
                                            {member.name}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                    {isOwner && (
                        <button
                            onClick={() => setGlobalAssignModalOpen(true)}
                            className="px-3.5 py-2 bg-indigo-50 text-indigo-700 rounded-lg font-semibold hover:bg-indigo-100 transition border border-indigo-200/80 text-xs"
                        >
                            Assign All DB Contacts
                        </button>
                    )}
                    <button 
                        onClick={() => setImportModalOpen(true)}
                        className="px-4 py-2 bg-white text-gray-700 rounded-lg font-bold shadow-sm transition border border-gray-200 hover:bg-gray-50 text-sm"
                    >
                        Import CSV
                    </button>
                    <button 
                        onClick={() => setAddContactModalOpen(true)}
                        className="px-4 py-2 bg-[#00a884] text-white rounded-lg font-bold hover:bg-[#009071] shadow-sm transition text-sm"
                    >
                        + Add Contact
                    </button>
                </div>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-gray-200/60 overflow-x-auto">
                <table className="w-full min-w-[800px] divide-y divide-gray-200">
                    <thead className="bg-gray-50/50">
                        <tr>
                            <th className="px-6 py-4 w-10">
                                <input 
                                    type="checkbox" 
                                    onChange={toggleAll}
                                    checked={contacts?.data?.length > 0 && selectedContacts.length === contacts.data.length}
                                    className="rounded border-gray-300 text-[#00a884] focus:ring-[#00a884]"
                                />
                            </th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Phone Number</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Name</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Tags</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Email</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Custom Fields</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Created</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {contacts && contacts.data && contacts.data.length > 0 ? (
                            contacts.data.map((contact) => (
                                <tr key={contact.id} className="hover:bg-gray-50 transition">
                                    <td className="px-6 py-4">
                                        <input 
                                            type="checkbox"
                                            checked={selectedContacts.includes(contact.id)}
                                            onChange={() => toggleContact(contact.id)}
                                            className="rounded border-gray-300 text-[#00a884] focus:ring-[#00a884]"
                                        />
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-semibold">
                                        <Link href={route('contacts.show', contact.id)} className="text-[#00a884] hover:underline">
                                            {contact.phone_number}
                                        </Link>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        <Link href={route('contacts.show', contact.id)} className="hover:text-gray-900 font-medium hover:underline">
                                            {contact.name || '-'}
                                        </Link>
                                    </td>
                                    <td className="px-6 py-4 text-sm">
                                        <div className="flex flex-wrap gap-1">
                                            {contact.contact_tags && contact.contact_tags.length > 0 ? contact.contact_tags.map(tag => (
                                                <span key={tag.id} className="group inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 transition-colors hover:bg-purple-200">
                                                    <span>{tag.name}</span>
                                                    <button
                                                        onClick={() => handleRemoveTag(contact.id, tag.id)}
                                                        className="text-purple-400 hover:text-purple-900 focus:outline-none transition-colors opacity-0 group-hover:opacity-100"
                                                        title="Remove tag"
                                                    >
                                                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                </span>
                                            )) : '-'}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {contact.email || '-'}
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-500 whitespace-nowrap overflow-hidden">
                                        {(() => {
                                            if (!contact.custom_fields) return '-';
                                            const entries = Object.entries(contact.custom_fields);
                                            if (entries.length === 0) return '-';
                                            
                                            const visibleEntries = entries.slice(0, 2);
                                            const remainingCount = entries.length - 2;
                                            const allTooltip = entries.map(([k, v]) => `${k}: ${v}`).join('\n');

                                            return (
                                                <div className="flex items-center gap-1.5 overflow-hidden" title={allTooltip}>
                                                    {visibleEntries.map(([k, v]) => (
                                                        <span key={k} className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 max-w-[140px] truncate">
                                                            {k}: {v}
                                                        </span>
                                                    ))}
                                                    {remainingCount > 0 && (
                                                        <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-gray-200 text-gray-700 shrink-0 cursor-help">
                                                            +{remainingCount} more
                                                        </span>
                                                    )}
                                                </div>
                                            );
                                        })()}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {new Date(contact.created_at).toLocaleDateString()}
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan="7" className="px-6 py-12 text-center">
                                    <div className="flex flex-col items-center justify-center space-y-3">
                                        <div className="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center border border-gray-100">
                                            <span className="text-2xl">📇</span>
                                        </div>
                                        <h3 className="text-sm font-bold text-gray-900">No contacts found</h3>
                                        <p className="text-xs text-gray-500 max-w-sm">
                                            Import a CSV file to add contacts and start sending bulk messaging campaigns.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {contacts && contacts.links && (
                <div className="mt-4 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div className="text-xs font-medium text-gray-500">
                        Showing <span className="font-bold text-gray-900">{(contacts.current_page - 1) * contacts.per_page + 1}</span> to <span className="font-bold text-gray-900">{Math.min(contacts.current_page * contacts.per_page, contacts.total)}</span> of <span className="font-bold text-gray-900">{contacts.total}</span> entries
                    </div>
                    <Pagination links={contacts.links} />
                </div>
            )}

            {/* Import Modal */}
            {importModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" onClick={() => setImportModalOpen(false)}></div>
                    <div className="relative bg-white rounded-2xl p-6 w-full max-w-md shadow-xl border border-gray-100">
                        <h2 className="text-xl font-bold text-gray-900 mb-4">Import Contacts</h2>
                        
                        <div className="mb-4 text-sm text-gray-600 bg-blue-50 p-3 rounded-lg border border-blue-100">
                            <p><strong>Requirements:</strong></p>
                            <ul className="list-disc ml-5 mt-1 space-y-1">
                                <li>CSV format only</li>
                                <li>Must include a <code>phone_number</code> header</li>
                                <li>Optional: <code>name</code>, <code>email</code></li>
                                <li>All other headers become custom fields</li>
                            </ul>
                        </div>

                        <form onSubmit={handleImport} className="space-y-4">
                            <div>
                                <label className="block text-sm font-bold text-gray-700 mb-1">Select CSV File</label>
                                <input 
                                    type="file" 
                                    accept=".csv"
                                    onChange={e => setData('file', e.target.files[0])}
                                    className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-[#00a884] hover:file:bg-emerald-100 transition"
                                />
                                {errors.file && <div className="text-rose-600 text-xs mt-1 font-semibold">{errors.file}</div>}
                            </div>

                            {isOwner && (
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-1">Assign Imported Contacts To (Optional)</label>
                                    <select
                                        value={data.assigned_user_id}
                                        onChange={e => setData('assigned_user_id', e.target.value)}
                                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00a884] focus:ring-[#00a884] text-sm"
                                    >
                                        <option value="">-- No Assignment (Unassigned) --</option>
                                        {teamMembers.map(member => (
                                            <option key={member.id} value={member.id}>
                                                {member.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.assigned_user_id && <div className="text-rose-600 text-xs mt-1 font-semibold">{errors.assigned_user_id}</div>}
                                </div>
                            )}
                            
                            <div className="flex justify-end gap-2 mt-6">
                                <button
                                    type="button"
                                    onClick={() => setImportModalOpen(false)}
                                    className="px-4 py-2 text-sm font-bold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing || !data.file}
                                    className="px-4 py-2 text-sm font-bold text-white bg-[#00a884] rounded-lg hover:bg-[#009071] transition disabled:opacity-50"
                                >
                                    {processing ? 'Importing...' : 'Start Import'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Global Assign Modal */}
            {globalAssignModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" onClick={() => setGlobalAssignModalOpen(false)}></div>
                    <div className="relative bg-white rounded-2xl p-6 w-full max-w-md shadow-xl border border-gray-100">
                        <h2 className="text-xl font-bold text-gray-900 mb-4">Assign Entire Database</h2>
                        <p className="text-sm text-gray-600 mb-4">
                            This will re-assign <strong>ALL</strong> contacts currently stored in your organization's database.
                        </p>
                        <div className="space-y-2">
                            <button 
                                onClick={() => handleGlobalAssign(null)}
                                className="w-full text-left px-4 py-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition font-medium text-gray-700"
                            >
                                Unassign Everyone
                            </button>
                            {teamMembers.map(member => (
                                <button 
                                    key={member.id}
                                    onClick={() => handleGlobalAssign(member.id)}
                                    className="w-full text-left px-4 py-3 rounded-lg border border-gray-200 hover:bg-indigo-50 transition font-bold text-indigo-700"
                                >
                                    Assign all to {member.name}
                                </button>
                            ))}
                        </div>
                        <div className="flex justify-end mt-4">
                            <button
                                type="button"
                                onClick={() => setGlobalAssignModalOpen(false)}
                                className="px-4 py-2 text-sm font-bold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Add Contact Modal */}
            {addContactModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" onClick={() => setAddContactModalOpen(false)}></div>
                    <div className="relative bg-white rounded-2xl p-6 w-full max-w-md shadow-xl border border-gray-100">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-xl font-bold text-gray-900">Add New Contact</h2>
                            <button onClick={() => setAddContactModalOpen(false)} className="text-gray-400 hover:text-gray-600">✕</button>
                        </div>
                        <p className="text-sm text-gray-500 mb-4">
                            Enter the contact details below. Phone numbers will be automatically formatted to standard rules.
                        </p>
                        
                        <form onSubmit={handleAddContact} className="space-y-4">
                            <div>
                                <label className="block text-sm font-bold text-gray-700 mb-1">Name (Optional)</label>
                                <input
                                    type="text"
                                    value={addContactForm.data.name}
                                    onChange={e => addContactForm.setData('name', e.target.value)}
                                    placeholder="e.g. John Doe"
                                    className="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#00a884] focus:border-transparent outline-none transition"
                                />
                                {addContactForm.errors.name && <p className="text-sm text-red-600 mt-1">{addContactForm.errors.name}</p>}
                            </div>
                            
                            <div>
                                <label className="block text-sm font-bold text-gray-700 mb-1">Phone Number <span className="text-red-500">*</span></label>
                                <input
                                    type="text"
                                    value={addContactForm.data.phone_number}
                                    onChange={e => addContactForm.setData('phone_number', e.target.value)}
                                    placeholder="e.g. 9876543210"
                                    required
                                    className="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#00a884] focus:border-transparent outline-none transition"
                                />
                                {addContactForm.errors.phone_number && <p className="text-sm text-red-600 mt-1">{addContactForm.errors.phone_number}</p>}
                            </div>

                            {isOwner && teamMembers.length > 0 && (
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-1">Assign To (Optional)</label>
                                    <select
                                        value={addContactForm.data.assigned_user_id}
                                        onChange={e => addContactForm.setData('assigned_user_id', e.target.value)}
                                        className="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#00a884] focus:border-transparent outline-none transition bg-white"
                                    >
                                        <option value="">-- Unassigned --</option>
                                        {teamMembers.map(member => (
                                            <option key={member.id} value={member.id}>{member.name}</option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            <div className="flex justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
                                <button
                                    type="button"
                                    onClick={() => setAddContactModalOpen(false)}
                                    className="px-4 py-2 text-sm font-bold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={addContactForm.processing}
                                    className="px-4 py-2 text-sm font-bold text-white bg-[#00a884] rounded-lg hover:bg-[#009071] transition disabled:opacity-50"
                                >
                                    {addContactForm.processing ? 'Saving...' : 'Save Contact'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
            {/* Tag Contacts Modal */}
            {tagModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" onClick={() => setTagModalOpen(false)}></div>
                    <div className="relative bg-white rounded-2xl p-6 w-full max-w-md shadow-xl border border-gray-100">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-xl font-bold text-gray-900">Tag Contacts ({selectedContacts.length})</h2>
                            <button onClick={() => setTagModalOpen(false)} className="text-gray-400 hover:text-gray-600">✕</button>
                        </div>
                        
                        <form onSubmit={handleBulkTag} className="space-y-4">
                            <div className="bg-purple-50/50 p-3 rounded-xl border border-purple-100 mb-4">
                                <label className="block text-xs font-bold text-purple-900 uppercase tracking-wider mb-2">
                                    Create New Tag
                                </label>
                                <div className="flex gap-2">
                                    <input 
                                        type="text"
                                        placeholder="Tag name (e.g. VIP, Lead)..."
                                        value={newTagName}
                                        onChange={(e) => setNewTagName(e.target.value)}
                                        className="flex-1 rounded-lg border-gray-300 text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"
                                    />
                                    <button
                                        type="button"
                                        onClick={handleCreateTag}
                                        disabled={isCreatingTag || !newTagName.trim()}
                                        className="px-3.5 py-2 bg-purple-600 text-white font-bold text-sm rounded-lg hover:bg-purple-700 transition disabled:opacity-50"
                                    >
                                        {isCreatingTag ? 'Creating...' : '+ Create'}
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label className="block text-sm font-bold text-gray-700 mb-2">Action</label>
                                <div className="flex gap-4">
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="tag_mode" value="add" checked={tagMode === 'add'} onChange={() => setTagMode('add')} className="text-indigo-600 focus:ring-indigo-600" />
                                        <span className="text-sm font-medium">Add Tags</span>
                                    </label>
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="tag_mode" value="remove" checked={tagMode === 'remove'} onChange={() => setTagMode('remove')} className="text-red-600 focus:ring-red-600" />
                                        <span className="text-sm font-medium">Remove Tags</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-bold text-gray-700 mb-2">Select Tags to {tagMode === 'add' ? 'Add' : 'Remove'}</label>
                                <div className="space-y-2 max-h-48 overflow-y-auto p-1">
                                    {allTags.length > 0 ? allTags.map(tag => (
                                        <label key={tag.id} className="flex items-center gap-3 p-2 rounded hover:bg-gray-50 cursor-pointer border border-transparent hover:border-gray-200 transition">
                                            <input 
                                                type="checkbox"
                                                checked={tagIds.includes(tag.id)}
                                                onChange={(e) => {
                                                    if (e.target.checked) {
                                                        setTagIds([...tagIds, tag.id]);
                                                    } else {
                                                        setTagIds(tagIds.filter(id => id !== tag.id));
                                                    }
                                                }}
                                                className="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                                            />
                                            <span className="text-sm font-semibold text-gray-800">{tag.name}</span>
                                        </label>
                                    )) : (
                                        <p className="text-sm text-gray-500 italic text-center py-4">No tags created yet.</p>
                                    )}
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
                                <button
                                    type="button"
                                    onClick={() => setTagModalOpen(false)}
                                    className="px-4 py-2 text-sm font-bold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmittingTag || tagIds.length === 0}
                                    className="px-4 py-2 text-sm font-bold text-white bg-purple-600 rounded-lg hover:bg-purple-700 transition disabled:opacity-50"
                                >
                                    {isSubmittingTag ? 'Applying...' : 'Apply Change'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Quick Send Modal */}
            {quickSendModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" onClick={() => setQuickSendModalOpen(false)}></div>
                    <div className="relative bg-white rounded-2xl p-6 w-full max-w-md shadow-xl border border-gray-100">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-xl font-bold text-gray-900 flex items-center gap-2">
                                <svg className="w-5 h-5 text-[#25D366]" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                                Quick Message ({selectedContacts.length} recipients)
                            </h2>
                            <button onClick={() => setQuickSendModalOpen(false)} className="text-gray-400 hover:text-gray-600">✕</button>
                        </div>
                        
                        <form onSubmit={handleQuickSend} className="space-y-4">
                            <div>
                                <label className="block text-sm font-bold text-gray-700 mb-1">Message Type</label>
                                <div className="flex gap-4">
                                    <label className="flex items-center gap-2">
                                        <input 
                                            type="radio" 
                                            name="message_type"
                                            value="template"
                                            checked={qsMessageType === 'template'}
                                            onChange={e => setQsMessageType(e.target.value)}
                                            className="text-[#00a884] focus:ring-[#00a884]"
                                        />
                                        <span className="text-sm font-medium">WhatsApp Template</span>
                                    </label>
                                    <label className="flex items-center gap-2">
                                        <input 
                                            type="radio" 
                                            name="message_type"
                                            value="text"
                                            checked={qsMessageType === 'text'}
                                            onChange={e => setQsMessageType(e.target.value)}
                                            className="text-[#00a884] focus:ring-[#00a884]"
                                        />
                                        <span className="text-sm font-medium">Freeform Text</span>
                                    </label>
                                </div>
                            </div>

                            {qsMessageType === 'template' ? (
                                <div>
                                    <TemplateSelector
                                        approvedTemplates={approvedTemplates}
                                        selectedTemplate={qsSelectedTemplate}
                                        templateVariables={qsTemplateVariables}
                                        onTemplateSelect={handleTemplateSelect}
                                        onVariableChange={(num, value) => setQsTemplateVariables({...qsTemplateVariables, [num]: value})}
                                        mode="dynamic"
                                        availableContactFields={availableContactFields}
                                    />
                                    {qsErrors.template_name && <p className="text-sm text-red-600 mt-1">{qsErrors.template_name}</p>}
                                    {qsErrors.template_variable_map && <p className="text-sm text-red-600 mt-1">{qsErrors.template_variable_map}</p>}
                                </div>
                            ) : (
                                <div>
                                    <label className="block text-sm font-bold text-gray-700 mb-1">Message Content</label>
                                    <textarea
                                        value={qsTextContent}
                                        onChange={e => setQsTextContent(e.target.value)}
                                        rows="4"
                                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#00a884] focus:ring-[#00a884] text-sm"
                                        placeholder="Type your message here..."
                                        required
                                    ></textarea>
                                    <p className="text-xs text-gray-500 mt-1 italic">Note: Freeform text will only be delivered if the contact has interacted with you in the last 24 hours.</p>
                                    {qsErrors.text_content && <p className="text-sm text-red-600 mt-1">{qsErrors.text_content}</p>}
                                </div>
                            )}

                            <div className="flex justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
                                <button
                                    type="button"
                                    onClick={() => setQuickSendModalOpen(false)}
                                    className="px-4 py-2 text-sm font-bold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={qsIsSubmitting || (qsMessageType === 'template' && !qsTemplateName) || (qsMessageType === 'text' && !qsTextContent)}
                                    className="px-4 py-2 text-sm font-bold text-white bg-[#25D366] rounded-lg hover:bg-[#1DA851] transition disabled:opacity-50 flex items-center gap-2"
                                >
                                    {qsIsSubmitting ? 'Sending...' : 'Send Now'}
                                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
        </ErrorBoundary>
    );
}
