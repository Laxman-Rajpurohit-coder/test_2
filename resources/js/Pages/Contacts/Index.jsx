import React, { useState } from 'react';
import { Head, useForm, usePage, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';

export default function ContactsIndex({ contacts }) {
    const { tenant_features } = usePage().props;
    const [importModalOpen, setImportModalOpen] = useState(false);
    
    const { data, setData, post, processing, errors, reset } = useForm({
        file: null,
    });

    const handleImport = (e) => {
        e.preventDefault();
        post(route('contacts.import'), {
            onSuccess: () => {
                setImportModalOpen(false);
                reset();
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Contacts" />
            
            <div className="flex items-center justify-between mb-6">
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
                <div className="flex gap-3">
                    <button 
                        onClick={() => setImportModalOpen(true)}
                        className="px-4 py-2 bg-emerald-50 text-[#00a884] rounded-lg font-bold hover:bg-emerald-100 transition border border-emerald-200"
                    >
                        Import CSV
                    </button>
                    <Link
                        href={route('campaigns.index')}
                        className="px-4 py-2 bg-[#00a884] text-white rounded-lg font-bold hover:bg-[#009071] shadow-sm transition"
                    >
                        Go to Campaigns
                    </Link>
                </div>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-gray-200/60 overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50/50">
                        <tr>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Phone Number</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Name</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Email</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Custom Fields</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Created</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {contacts && contacts.data && contacts.data.length > 0 ? (
                            contacts.data.map((contact) => (
                                <tr key={contact.id} className="hover:bg-gray-50 transition">
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                        {contact.phone_number}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {contact.name || '-'}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {contact.email || '-'}
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-500">
                                        <div className="flex flex-wrap gap-1">
                                            {contact.custom_fields && Object.entries(contact.custom_fields).map(([k, v]) => (
                                                <span key={k} className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                    {k}: {v}
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {new Date(contact.created_at).toLocaleDateString()}
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan="5" className="px-6 py-12 text-center">
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
        </AppLayout>
    );
}
