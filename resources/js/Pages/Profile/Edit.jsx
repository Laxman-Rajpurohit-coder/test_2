import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AppLayout
            header={
                <div className="flex flex-col gap-1">
                    <h2 className="text-xl font-bold tracking-tight text-gray-900">
                        Profile & Account Settings
                    </h2>
                    <p className="text-xs text-gray-500">
                        Manage your account profile information, password, and security preferences.
                    </p>
                </div>
            }
        >
            <Head title="Profile" />

            <div className="max-w-4xl space-y-6">
                <div className="bg-white p-6 shadow-sm border border-gray-200/80 rounded-2xl">
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                        className="max-w-xl"
                    />
                </div>

                <div className="bg-white p-6 shadow-sm border border-gray-200/80 rounded-2xl">
                    <UpdatePasswordForm className="max-w-xl" />
                </div>

                <div className="bg-white p-6 shadow-sm border border-gray-200/80 rounded-2xl">
                    <DeleteUserForm className="max-w-xl" />
                </div>
            </div>
        </AppLayout>
    );
}
