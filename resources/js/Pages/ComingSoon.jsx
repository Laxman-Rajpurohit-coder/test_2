import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function ComingSoon() {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-gray-900">
                            Coming Soon
                        </h2>
                    </div>
                </div>
            }
        >
            <Head title="Coming Soon" />

            <div className="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-gray-100 flex flex-col items-center justify-center p-16 text-center space-y-6">
                    <div className="w-24 h-24 bg-emerald-50 text-[#00a884] rounded-full flex items-center justify-center mb-4">
                        <svg className="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 className="text-2xl font-extrabold text-gray-900 tracking-tight">Feature in Development</h3>
                    <p className="text-base text-gray-500 max-w-md">
                        We're working hard to bring you this feature. Stay tuned for future updates to the MSG91 WhatsApp Pro platform!
                    </p>
                    <Link
                        href="/dashboard"
                        className="mt-4 px-6 py-2.5 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 transition"
                    >
                        Return to Dashboard
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
