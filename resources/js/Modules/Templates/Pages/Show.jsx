import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PhonePreview from '../Components/PhonePreview';
import StatusBadge from '../Components/StatusBadge';

export default function Show({ auth, template }) {
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this template? It will be deleted from MSG91 and may break active campaigns using it.')) {
            setIsDeleting(true);
            router.delete(route('templates.destroy', template.id), {
                onFinish: () => setIsDeleting(false)
            });
        }
    };

    return (
        <AppLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <div className="flex items-center space-x-4">
                        <Link href={route('templates.index')} className="text-gray-500 hover:text-gray-700">
                            &larr; Back
                        </Link>
                        <h2 className="font-semibold text-xl text-gray-800 leading-tight">Template Details</h2>
                    </div>
                    <div className="flex space-x-3">
                        <button
                            onClick={handleDelete}
                            disabled={isDeleting}
                            className="inline-flex items-center px-4 py-2 bg-white border border-red-300 rounded-md font-semibold text-xs text-red-700 uppercase tracking-widest shadow-sm hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                        >
                            {isDeleting ? 'Deleting...' : 'Delete'}
                        </button>
                        {template.status === 'approved' && (
                            <Link
                                href={route('campaigns.create', { template: template.id })}
                                className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            >
                                Use in Campaign
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={template.name} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 flex flex-col md:flex-row gap-8">
                    
                    {/* Details Side */}
                    <div className="w-full md:w-3/5 space-y-6">
                        
                        {template.status === 'rejected' && template.rejection_reason && (
                            <div className="bg-red-50 border-l-4 border-red-400 p-4">
                                <div className="flex">
                                    <div className="flex-shrink-0">
                                        <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                        </svg>
                                    </div>
                                    <div className="ml-3">
                                        <h3 className="text-sm font-medium text-red-800">Template Rejected</h3>
                                        <div className="mt-2 text-sm text-red-700">
                                            <p>{template.rejection_reason}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="bg-white shadow overflow-hidden sm:rounded-lg">
                            <div className="px-4 py-5 sm:px-6 flex justify-between items-center">
                                <div>
                                    <h3 className="text-lg leading-6 font-medium text-gray-900">
                                        {template.name}
                                    </h3>
                                    <p className="mt-1 max-w-2xl text-sm text-gray-500">
                                        Template metadata and configuration.
                                    </p>
                                </div>
                                <StatusBadge status={template.status} />
                            </div>
                            <div className="border-t border-gray-200">
                                <dl>
                                    <div className="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt className="text-sm font-medium text-gray-500">Language</dt>
                                        <dd className="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 uppercase">{template.language}</dd>
                                    </div>
                                    <div className="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt className="text-sm font-medium text-gray-500">Category</dt>
                                        <dd className="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 uppercase">{template.category}</dd>
                                    </div>
                                    <div className="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt className="text-sm font-medium text-gray-500">Last Synced</dt>
                                        <dd className="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                            {template.synced_at ? new Date(template.synced_at).toLocaleString() : 'Never'}
                                        </dd>
                                    </div>
                                    <div className="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                        <dt className="text-sm font-medium text-gray-500">Created At</dt>
                                        <dd className="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                            {new Date(template.created_at).toLocaleString()}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        {/* Raw Components JSON for debugging/advanced view */}
                        <div className="bg-white shadow overflow-hidden sm:rounded-lg">
                            <div className="px-4 py-5 sm:px-6">
                                <h3 className="text-lg leading-6 font-medium text-gray-900">
                                    Raw Components
                                </h3>
                            </div>
                            <div className="border-t border-gray-200 p-4 bg-gray-900">
                                <pre className="text-green-400 text-xs overflow-x-auto">
                                    {JSON.stringify(template.components, null, 2)}
                                </pre>
                            </div>
                        </div>
                    </div>

                    {/* Preview Side */}
                    <div className="w-full md:w-2/5">
                        <div className="sticky top-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4 text-center">Preview</h3>
                            <PhonePreview components={template.components} />
                        </div>
                    </div>

                </div>
            </div>
        </AppLayout>
    );
}
