import React, { useState } from 'react';
import { Head, Link, useForm, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import SecondaryButton from '@/Components/SecondaryButton';

/**
 * Render the admin page for managing tenants.
 * @param {Object} auth - Authentication data used to identify the current user.
 * @param {Array} tenants - Tenants displayed in the management table.
 * @return {JSX.Element} The rendered tenant management page.
 */
export default function TenantIndex({ auth, tenants, webhook }) {
    const { flash } = usePage().props;
    const [invitingTenant, setInvitingTenant] = useState(null);
    const [isCreatingTenant, setIsCreatingTenant] = useState(false);

    const { data: inviteData, setData: setInviteData, post: postInvite, processing: processingInvite, errors: inviteErrors, reset: resetInvite, clearErrors: clearInviteErrors } = useForm({
        email: '',
    });

    const { data: createData, setData: setCreateData, post: postCreate, processing: processingCreate, errors: createErrors, reset: resetCreate, clearErrors: clearCreateErrors } = useForm({
        name: '',
        slug: '',
    });

    const { data: featureData, setData: setFeatureData, patch: patchFeatures, processing: processingFeatures, reset: resetFeatures } = useForm({
        features: {},
    });
    const [editingFeaturesTenant, setEditingFeaturesTenant] = useState(null);

    const [editingCredentialsTenant, setEditingCredentialsTenant] = useState(null);
    const { data: credentialData, setData: setCredentialData, patch: patchCredentials, processing: processingCredentials, errors: credentialErrors, reset: resetCredentials, clearErrors: clearCredentialErrors } = useForm({
        msg91_auth_key: '',
        openai_api_key: '',
        grok_api_key: '',
        gemini_api_key: '',
        flowise_endpoint: '',
    });

    const openCreateModal = () => {
        setIsCreatingTenant(true);
        resetCreate();
        clearCreateErrors();
    };

    const closeCreateModal = () => {
        setIsCreatingTenant(false);
        resetCreate();
        clearCreateErrors();
    };

    const submitCreate = (e) => {
        e.preventDefault();
        postCreate(route('admin.tenants.store'), {
            onSuccess: () => closeCreateModal(),
        });
    };

    const openInviteModal = (tenant) => {
        setInvitingTenant(tenant);
        resetInvite();
        clearInviteErrors();
    };

    const closeInviteModal = () => {
        setInvitingTenant(null);
        resetInvite();
        clearInviteErrors();
    };

    const sendInvite = (e) => {
        e.preventDefault();
        postInvite(`/admin/tenants/${invitingTenant.id}/invites`, {
            onSuccess: () => closeInviteModal(),
        });
    };

    const openFeaturesModal = (tenant) => {
        setEditingFeaturesTenant(tenant);
        setFeatureData('features', tenant.features || {});
    };

    const closeFeaturesModal = () => {
        setEditingFeaturesTenant(null);
        resetFeatures();
    };

    const submitFeatures = (e) => {
        e.preventDefault();
        patchFeatures(route('admin.tenants.features', editingFeaturesTenant.id), {
            onSuccess: () => closeFeaturesModal(),
        });
    };

    const openCredentialsModal = (tenant) => {
        setEditingCredentialsTenant(tenant);
        resetCredentials();
        clearCredentialErrors();
        // Fetch current masked credentials
        window.axios.get(route('admin.tenants.credentials.show', tenant.id))
            .then(response => {
                setCredentialData({
                    msg91_auth_key: response.data.msg91_auth_key || '',
                    openai_api_key: response.data.openai_api_key || '',
                    grok_api_key: response.data.grok_api_key || '',
                    gemini_api_key: response.data.gemini_api_key || '',
                    flowise_endpoint: response.data.flowise_endpoint || '',
                });
            })
            .catch(error => {
                console.error("Failed to fetch credentials", error);
            });
    };

    const closeCredentialsModal = () => {
        setEditingCredentialsTenant(null);
        resetCredentials();
        clearCredentialErrors();
    };

    const submitCredentials = (e) => {
        e.preventDefault();
        patchCredentials(route('admin.tenants.credentials.update', editingCredentialsTenant.id), {
            onSuccess: () => closeCredentialsModal(),
        });
    };

    return (
        <AuthenticatedLayout
            user={auth?.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Admin - Tenants</h2>}
        >
            <Head title="Admin - Tenants" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Admin Header with Logout */}
                    <div className="flex justify-between items-center mb-6 bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs">
                        <div className="text-gray-900 font-bold text-lg">Admin Control Panel</div>
                        <a
                            href={route('admin.logout')}
                            onClick={() => localStorage.removeItem('is_logged_in')}
                            className="inline-flex items-center px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl transition shadow-sm"
                        >
                            Log Out
                        </a>
                    </div>


                    {flash.success && (
                        <div className="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                            {flash.success}
                        </div>
                    )}

                    {/* Webhook Info */}
                    <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-4 mb-6">

                        <h2 className="text-base font-bold text-gray-900 border-b border-gray-100 pb-3">Webhook Configuration</h2>
                        <p className="text-xs text-gray-500">Copy this URL and Secret into your MSG91 dashboard to receive inbound messages and delivery receipts.</p>
                        <div className="space-y-3">
                            <div>
                                <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Webhook URL</label>
                                <div className="flex items-center">
                                    <code className="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-700 font-mono break-all select-all">
                                        {webhook?.url}
                                    </code>
                                </div>
                            </div>
                            <div>
                                <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Webhook Secret (Header: X-MSG91-Secret)</label>
                                <div className="flex items-center">
                                    <code className="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-700 font-mono select-all">
                                        {webhook?.secret || 'Not configured on server'}
                                    </code>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 bg-white border-b border-gray-200">
                            <div className="flex justify-between items-center mb-4">
                                <h3 className="text-lg font-medium text-gray-900">All Tenants</h3>
                                <PrimaryButton onClick={() => openCreateModal()}>
                                    Create Tenant
                                </PrimaryButton>
                            </div>
                            
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {tenants.map(tenant => (
                                            <tr key={tenant.id}>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-medium text-gray-900">{tenant.name}</div>
                                                    <div className="text-sm text-gray-500">Slug: {tenant.slug}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${tenant.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                        {tenant.status}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {new Date(tenant.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <div className="flex items-center justify-end space-x-3">
                                                        <button 
                                                            onClick={() => openInviteModal(tenant)}
                                                            className="text-purple-600 hover:text-purple-900 text-sm font-medium"
                                                        >
                                                            Invite User
                                                        </button>
                                                        <span className="text-gray-300">|</span>
                                                        <button 
                                                            onClick={() => router.post(`/admin/impersonate/${tenant.id}`)}
                                                            className="text-indigo-600 hover:text-indigo-900 text-sm font-medium"
                                                        >
                                                            Impersonate
                                                        </button>
                                                        <span className="text-gray-300">|</span>
                                                        <Link 
                                                            href={`/admin/tenants/${tenant.id}/stats`}
                                                            className="text-blue-600 hover:text-blue-900 text-sm font-medium"
                                                        >
                                                            Stats
                                                        </Link>
                                                        <span className="text-gray-300">|</span>
                                                        <button 
                                                            onClick={() => router.patch(`/admin/tenants/${tenant.id}/status`, { status: tenant.status === 'active' ? 'suspended' : 'active' })}
                                                            className={`${tenant.status === 'active' ? 'text-orange-600 hover:text-orange-900' : 'text-green-600 hover:text-green-900'} text-sm font-medium`}
                                                        >
                                                            {tenant.status === 'active' ? 'Suspend' : 'Activate'}
                                                        </button>
                                                        <span className="text-gray-300">|</span>
                                                        <button 
                                                            onClick={() => openFeaturesModal(tenant)}
                                                            className="text-teal-600 hover:text-teal-900 text-sm font-medium"
                                                        >
                                                            Features
                                                        </button>
                                                        <span className="text-gray-300">|</span>
                                                        <button 
                                                            onClick={() => openCredentialsModal(tenant)}
                                                            className="text-gray-600 hover:text-gray-900 text-sm font-medium"
                                                        >
                                                            Credentials
                                                        </button>
                                                        <span className="text-gray-300">|</span>
                                                        <button 
                                                            onClick={() => {
                                                                if(window.confirm('Are you sure you want to delete this tenant?')) {
                                                                    router.delete(`/admin/tenants/${tenant.id}`);
                                                                }
                                                            }}
                                                            className="text-red-600 hover:text-red-900 text-sm font-medium"
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                        {tenants.length === 0 && (
                                            <tr>
                                                <td colSpan="4" className="px-6 py-4 text-center text-sm text-gray-500">
                                                    No tenants found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <Modal show={invitingTenant !== null} onClose={closeInviteModal}>
                <form onSubmit={sendInvite} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        Invite User to {invitingTenant?.name}
                    </h2>

                    <p className="mt-1 text-sm text-gray-600">
                        Generate a secure registration link for a new user. The user will be automatically assigned to this workspace.
                    </p>

                    <div className="mt-6">
                        <InputLabel htmlFor="email" value="Email Address" />

                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={inviteData.email}
                            onChange={(e) => setInviteData('email', e.target.value)}
                            className="mt-1 block w-full"
                            isFocused
                            required
                        />

                        <InputError message={inviteErrors.email} className="mt-2" />
                    </div>

                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={closeInviteModal}>Cancel</SecondaryButton>

                        <PrimaryButton className="ml-3" disabled={processingInvite}>
                            Generate Invite Link
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            <Modal show={isCreatingTenant} onClose={closeCreateModal}>
                <form onSubmit={submitCreate} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        Create New Tenant
                    </h2>

                    <p className="mt-1 text-sm text-gray-600">
                        Create a fresh, isolated workspace for a new customer.
                    </p>

                    <div className="mt-6">
                        <InputLabel htmlFor="name" value="Tenant Name" />

                        <TextInput
                            id="name"
                            type="text"
                            name="name"
                            value={createData.name}
                            onChange={(e) => setCreateData('name', e.target.value)}
                            className="mt-1 block w-full"
                            isFocused
                            required
                        />

                        <InputError message={createErrors.name} className="mt-2" />
                    </div>

                    <div className="mt-6">
                        <InputLabel htmlFor="slug" value="Tenant Slug (Unique ID)" />

                        <TextInput
                            id="slug"
                            type="text"
                            name="slug"
                            value={createData.slug}
                            onChange={(e) => setCreateData('slug', e.target.value)}
                            className="mt-1 block w-full"
                            required
                        />

                        <InputError message={createErrors.slug} className="mt-2" />
                    </div>

                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={closeCreateModal}>Cancel</SecondaryButton>

                        <PrimaryButton className="ml-3" disabled={processingCreate}>
                            Create Tenant
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            <Modal show={editingFeaturesTenant !== null} onClose={closeFeaturesModal}>
                <form onSubmit={submitFeatures} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        Manage Features for {editingFeaturesTenant?.name}
                    </h2>

                    <p className="mt-1 text-sm text-gray-600 mb-6">
                        Toggle specific SAAS features on or off for this tenant.
                    </p>

                    <div className="space-y-4">
                        <label className="flex items-center">
                            <input
                                type="checkbox"
                                checked={featureData.features?.bot_auto_responder || false}
                                onChange={(e) => setFeatureData('features', { ...featureData.features, bot_auto_responder: e.target.checked })}
                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                            />
                            <span className="ml-2 text-sm text-gray-900 font-medium">Bot Auto-Responder</span>
                        </label>
                        <p className="ml-6 text-xs text-gray-500 -mt-3">Unlocks the Flow Builder and automated trigger replies.</p>

                        <label className="flex items-center">
                            <input
                                type="checkbox"
                                checked={featureData.features?.contacts_bulk_messaging || false}
                                onChange={(e) => setFeatureData('features', { ...featureData.features, contacts_bulk_messaging: e.target.checked })}
                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                            />
                            <span className="ml-2 text-sm text-gray-900 font-medium">Contacts & Bulk Messaging</span>
                        </label>
                        <p className="ml-6 text-xs text-gray-500 -mt-3">Allows uploading CSV contacts and sending bulk broadcast campaigns.</p>

                        <label className="flex items-center">
                            <input
                                type="checkbox"
                                checked={featureData.features?.template_management || false}
                                onChange={(e) => setFeatureData('features', { ...featureData.features, template_management: e.target.checked })}
                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                            />
                            <span className="ml-2 text-sm text-gray-900 font-medium">WhatsApp Templates</span>
                        </label>
                        <p className="ml-6 text-xs text-gray-500 -mt-3">Manage and sync pre-approved WhatsApp templates.</p>
                    </div>

                    <div className="mt-8 flex justify-end">
                        <SecondaryButton onClick={closeFeaturesModal}>Cancel</SecondaryButton>

                        <PrimaryButton className="ml-3" disabled={processingFeatures}>
                            Save Features
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            <Modal show={editingCredentialsTenant !== null} onClose={closeCredentialsModal}>
                <form onSubmit={submitCredentials} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 mb-4">
                        API Credentials for {editingCredentialsTenant?.name}
                    </h2>

                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="msg91_auth_key" value="MSG91 Auth Key" />
                            <TextInput
                                id="msg91_auth_key"
                                type="text"
                                className="mt-1 block w-full"
                                value={credentialData.msg91_auth_key}
                                onChange={(e) => setCredentialData('msg91_auth_key', e.target.value)}
                            />
                            <InputError message={credentialErrors.msg91_auth_key} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="openai_api_key" value="OpenAI API Key" />
                            <TextInput
                                id="openai_api_key"
                                type="text"
                                className="mt-1 block w-full"
                                value={credentialData.openai_api_key}
                                onChange={(e) => setCredentialData('openai_api_key', e.target.value)}
                            />
                            <InputError message={credentialErrors.openai_api_key} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="grok_api_key" value="Grok API Key" />
                            <TextInput
                                id="grok_api_key"
                                type="text"
                                className="mt-1 block w-full"
                                value={credentialData.grok_api_key}
                                onChange={(e) => setCredentialData('grok_api_key', e.target.value)}
                            />
                            <InputError message={credentialErrors.grok_api_key} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="gemini_api_key" value="Gemini API Key" />
                            <TextInput
                                id="gemini_api_key"
                                type="text"
                                className="mt-1 block w-full"
                                value={credentialData.gemini_api_key}
                                onChange={(e) => setCredentialData('gemini_api_key', e.target.value)}
                            />
                            <InputError message={credentialErrors.gemini_api_key} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="flowise_endpoint" value="Flowise Endpoint URL" />
                            <TextInput
                                id="flowise_endpoint"
                                type="text"
                                className="mt-1 block w-full"
                                value={credentialData.flowise_endpoint}
                                onChange={(e) => setCredentialData('flowise_endpoint', e.target.value)}
                            />
                            <InputError message={credentialErrors.flowise_endpoint} className="mt-2" />
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={closeCredentialsModal}>Cancel</SecondaryButton>
                        <PrimaryButton className="ml-3" disabled={processingCredentials}>
                            Save Credentials
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
