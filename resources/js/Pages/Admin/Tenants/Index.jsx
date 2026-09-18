import React, { useState } from 'react';
import { Head, Link, useForm, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import SecondaryButton from '@/Components/SecondaryButton';

export default function TenantIndex({ auth, tenants, webhook, billing }) {
    const { flash } = usePage().props;
    const [invitingTenant, setInvitingTenant] = useState(null);
    const [isCreatingTenant, setIsCreatingTenant] = useState(false);

    const [balancingTenant, setBalancingTenant] = useState(null);
    const { data: balanceData, setData: setBalanceData, post: postBalance, processing: processingBalance, errors: balanceErrors, reset: resetBalance, clearErrors: clearBalanceErrors } = useForm({
        amount: '',
        payment_reference: '',
        notes: '',
        auto_activate: true,
    });

    const [ledgerTenant, setLedgerTenant] = useState(null);
    const [ledgerTransactions, setLedgerTransactions] = useState([]);
    const [ledgerLoading, setLedgerLoading] = useState(false);

    const { data: billingData, setData: setBillingData, post: postBilling, processing: processingBilling } = useForm({
        rate_unit: billing?.rate_unit || 1000,
        currency: billing?.currency || 'INR',
        base_message_rate: billing?.base_message_rate || 0.25,
        utility_template_rate: billing?.utility_template_rate || 0.35,
        marketing_template_rate: billing?.marketing_template_rate || 0.78,
        authentication_template_rate: billing?.authentication_template_rate || 0.30,
        service_message_rate: billing?.service_message_rate || 0.25,
    });

    const submitBilling = (e) => {
        e.preventDefault();
        postBilling(route('admin.billing.update'));
    };

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

        axios.get(`/admin/tenants/${tenant.id}/credentials`)
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
                console.error('Error fetching credentials:', error);
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

    const toggleTenantStatus = (tenant) => {
        const isSuspending = tenant.status === 'active';
        const confirmMsg = isSuspending
            ? `Are you sure you want to suspend "${tenant.name}"? All users under this tenant will immediately be locked out from logging in or making requests.`
            : `Are you sure you want to reactivate "${tenant.name}"? Users will be allowed to log in and use the platform again.`;

        if (window.confirm(confirmMsg)) {
            router.patch(route('admin.tenants.status', tenant.id), {
                status: isSuspending ? 'suspended' : 'active',
            }, {
                preserveScroll: true,
            });
        }
    };

    const openBalanceModal = (tenant) => {
        setBalancingTenant(tenant);
        resetBalance();
        clearBalanceErrors();
    };

    const closeBalanceModal = () => {
        setBalancingTenant(null);
        resetBalance();
        clearBalanceErrors();
    };

    const submitBalance = (e) => {
        e.preventDefault();
        postBalance(route('admin.tenants.balance.add', balancingTenant.id), {
            onSuccess: () => closeBalanceModal(),
        });
    };

    const openLedgerModal = (tenant) => {
        setLedgerTenant(tenant);
        setLedgerLoading(true);
        setLedgerTransactions([]);
        axios.get(route('admin.tenants.transactions', tenant.id))
            .then(response => {
                setLedgerTransactions(response.data.transactions || []);
            })
            .catch(err => {
                console.error('Failed to load transactions:', err);
            })
            .finally(() => {
                setLedgerLoading(false);
            });
    };

    const closeLedgerModal = () => {
        setLedgerTenant(null);
        setLedgerTransactions([]);
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Tenant Management & Billing Panel</h2>}
        >
            <Head title="Super Admin Dashboard" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    
                    <div className="flex justify-between items-center mb-6">
                        <div className="text-sm font-semibold text-gray-600">
                            Impersonation: User switching enabled
                        </div>
                        <a 
                            href={route('admin.logout')} 
                            className="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 transition duration-150"
                        >
                            Log Out
                        </a>
                    </div>

                    {flash.success && (
                        <div className="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative font-semibold">
                            ✓ {flash.success}
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

                    {/* Global Admin Billing Rates Configuration */}
                    <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-4 mb-6">
                        <div className="flex items-center justify-between border-b border-gray-100 pb-3">
                            <div>
                                <h2 className="text-base font-bold text-gray-900 flex items-center gap-2">
                                    ⚙️ Global Admin Billing Rates
                                </h2>
                                <p className="text-xs text-gray-500">Configure global rates per 1,000 / 10,000 messages and template type pricing.</p>
                            </div>
                            <span className="px-2.5 py-1 text-xs font-bold bg-indigo-50 text-indigo-700 rounded-full border border-indigo-200">
                                Super Admin Panel
                            </span>
                        </div>

                        <form onSubmit={submitBilling} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label className="block text-xs font-extrabold text-gray-800 uppercase tracking-wider mb-1.5">Rate Base Unit</label>
                                <select
                                    value={billingData.rate_unit}
                                    onChange={(e) => setBillingData('rate_unit', parseInt(e.target.value))}
                                    className="w-full bg-white border border-gray-300 rounded-xl text-sm font-bold text-gray-900 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 shadow-xs px-3.5 py-2.5"
                                >
                                    <option value={1000}>Per 1,000 Messages</option>
                                    <option value={10000}>Per 10,000 Messages</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-extrabold text-gray-800 uppercase tracking-wider mb-1.5">Base Message Rate (₹)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={billingData.base_message_rate}
                                    onChange={(e) => setBillingData('base_message_rate', parseFloat(e.target.value))}
                                    className="w-full bg-white border border-gray-300 rounded-xl text-sm font-bold text-gray-900 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 shadow-xs px-3.5 py-2.5"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-extrabold text-gray-800 uppercase tracking-wider mb-1.5">Utility Template Rate (₹)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={billingData.utility_template_rate}
                                    onChange={(e) => setBillingData('utility_template_rate', parseFloat(e.target.value))}
                                    className="w-full bg-white border border-gray-300 rounded-xl text-sm font-bold text-gray-900 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 shadow-xs px-3.5 py-2.5"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-extrabold text-gray-800 uppercase tracking-wider mb-1.5">Marketing Template Rate (₹)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={billingData.marketing_template_rate}
                                    onChange={(e) => setBillingData('marketing_template_rate', parseFloat(e.target.value))}
                                    className="w-full bg-white border border-gray-300 rounded-xl text-sm font-bold text-gray-900 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 shadow-xs px-3.5 py-2.5"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-extrabold text-gray-800 uppercase tracking-wider mb-1.5">Authentication Template Rate (₹)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={billingData.authentication_template_rate}
                                    onChange={(e) => setBillingData('authentication_template_rate', parseFloat(e.target.value))}
                                    className="w-full bg-white border border-gray-300 rounded-xl text-sm font-bold text-gray-900 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 shadow-xs px-3.5 py-2.5"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-extrabold text-gray-800 uppercase tracking-wider mb-1.5">Service / Session Rate (₹)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={billingData.service_message_rate}
                                    onChange={(e) => setBillingData('service_message_rate', parseFloat(e.target.value))}
                                    className="w-full bg-white border border-gray-300 rounded-xl text-sm font-bold text-gray-900 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20 shadow-xs px-3.5 py-2.5"
                                    required
                                />
                            </div>

                            <div className="sm:col-span-2 lg:col-span-3 flex justify-end pt-2">
                                <PrimaryButton disabled={processingBilling}>
                                    Save Billing Rates
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                    
                    {/* Tenants Table & Tenant-Wise Billing Usage Breakdown */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 bg-white border-b border-gray-200">
                            <div className="flex justify-between items-center mb-4">
                                <div>
                                    <h3 className="text-lg font-bold text-gray-900">Tenant-Wise Billing & Management</h3>
                                    <p className="text-xs text-gray-500">Monitor message usage counts and calculated billing details per organization.</p>
                                </div>
                                <PrimaryButton onClick={() => openCreateModal()}>
                                    Create Tenant
                                </PrimaryButton>
                            </div>
                            
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tenant Organization</th>
                                            <th className="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Wallet Balance</th>
                                            <th className="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Total Messages</th>
                                            <th className="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Template Breakdown</th>
                                            <th className="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Est. Billing Cost</th>
                                            <th className="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Created</th>
                                            <th className="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {tenants.map(tenant => (
                                            <tr key={tenant.id} className="hover:bg-gray-50/60 transition-colors">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-bold text-gray-900">{tenant.name}</div>
                                                    <div className="text-xs text-gray-500">Slug: {tenant.slug} (ID: {tenant.id})</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`px-2 py-0.5 inline-flex text-xs leading-5 font-bold rounded-full ${tenant.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                        {tenant.status}
                                                    </span>
                                                </td>
                                                {/* Wallet Balance */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center gap-2">
                                                        <span className={`px-2.5 py-1 inline-flex items-center gap-1 text-xs font-mono font-extrabold rounded-lg border ${
                                                            parseFloat(tenant.balance || 0) > 0
                                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                                : 'bg-rose-50 text-rose-700 border-rose-200'
                                                        }`}>
                                                            <span>₹{parseFloat(tenant.balance || 0).toFixed(2)}</span>
                                                        </span>
                                                        <button
                                                            onClick={() => openLedgerModal(tenant)}
                                                            title="View Transaction History"
                                                            className="text-xs p-1 text-gray-400 hover:text-indigo-600 rounded hover:bg-gray-100 transition"
                                                        >
                                                            📜
                                                        </button>
                                                    </div>
                                                </td>
                                                {/* Tenant-Wise Message Count */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="text-sm font-extrabold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-100">
                                                        {tenant.total_messages || 0} Msgs
                                                    </span>
                                                </td>
                                                {/* Tenant-Wise Category Breakdown */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex flex-col gap-1 text-[11px]">
                                                        <span className="text-purple-700 font-semibold">📢 Mktg: {tenant.marketing_count || 0}</span>
                                                        <span className="text-blue-700 font-semibold">⚙️ Util: {tenant.utility_count || 0}</span>
                                                        <span className="text-emerald-700 font-semibold">🔒 Auth: {tenant.auth_count || 0}</span>
                                                    </div>
                                                </td>
                                                {/* Tenant-Wise Billing Cost */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="text-sm font-mono font-extrabold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-100">
                                                        ₹{parseFloat(tenant.total_cost || 0).toFixed(4)}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-xs text-gray-500 font-medium">
                                                    {new Date(tenant.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-xs font-bold">
                                                    <div className="flex items-center justify-end space-x-2">
                                                        <button 
                                                            onClick={() => openBalanceModal(tenant)}
                                                            className="text-emerald-600 hover:text-emerald-900 font-semibold"
                                                            title="Add Balance upon receiving payment"
                                                        >
                                                            + Balance
                                                        </button>
                                                        <span className="text-gray-300">|</span>
                                                        <button 
                                                            onClick={() => openInviteModal(tenant)}
                                                            className="text-purple-600 hover:text-purple-900 font-semibold"
                                                        >
                                                            Invite
                                                        </button>
                                                        <span className="text-gray-300">|</span>
                                                        <button 
                                                            onClick={() => router.post(`/admin/impersonate/${tenant.id}`)}
                                                            className="text-indigo-600 hover:text-indigo-900 font-semibold"
                                                        >
                                                            Impersonate
                                                        </button>
                                                        <span className="text-gray-300">|</span>
                                                        <button 
                                                            onClick={() => openCredentialsModal(tenant)}
                                                            className="text-amber-600 hover:text-amber-900 font-semibold"
                                                        >
                                                            Credentials
                                                        </button>
                                                        <span className="text-gray-300">|</span>
                                                        <button 
                                                            onClick={() => openFeaturesModal(tenant)}
                                                            className="text-teal-600 hover:text-teal-900 font-semibold"
                                                        >
                                                            Features
                                                        </button>
                                                        <span className="text-gray-300">|</span>
                                                        <Link 
                                                            href={`/admin/tenants/${tenant.id}/stats`}
                                                            className="text-blue-600 hover:text-blue-900 font-semibold"
                                                        >
                                                            Stats
                                                        </Link>
                                                        <span className="text-gray-300">|</span>
                                                        <button 
                                                            onClick={() => toggleTenantStatus(tenant)}
                                                            className={`font-semibold ${
                                                                tenant.status === 'active' 
                                                                    ? 'text-rose-600 hover:text-rose-900' 
                                                                    : 'text-emerald-600 hover:text-emerald-900'
                                                            }`}
                                                        >
                                                            {tenant.status === 'active' ? 'Suspend' : 'Activate'}
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {/* Create Tenant Modal */}
            <Modal show={isCreatingTenant} onClose={closeCreateModal}>
                <form onSubmit={submitCreate} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        Create New Tenant
                    </h2>

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
                            placeholder="Acme Corp"
                        />
                        <InputError message={createErrors.name} className="mt-2" />
                    </div>

                    <div className="mt-4">
                        <InputLabel htmlFor="slug" value="Tenant Slug" />
                        <TextInput
                            id="slug"
                            type="text"
                            name="slug"
                            value={createData.slug}
                            onChange={(e) => setCreateData('slug', e.target.value)}
                            className="mt-1 block w-full"
                            placeholder="acme-corp"
                        />
                        <InputError message={createErrors.slug} className="mt-2" />
                    </div>

                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={closeCreateModal}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton className="ms-3" disabled={processingCreate}>
                            Create Tenant
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Invite User Modal */}
            <Modal show={!!invitingTenant} onClose={closeInviteModal}>
                <form onSubmit={sendInvite} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        Invite Owner to {invitingTenant?.name}
                    </h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Generates a registration link. The user who registers using this link will become the Owner of this tenant.
                    </p>

                    <div className="mt-6">
                        <InputLabel htmlFor="invite_email" value="Owner Email Address" />
                        <TextInput
                            id="invite_email"
                            type="email"
                            name="email"
                            value={inviteData.email}
                            onChange={(e) => setInviteData('email', e.target.value)}
                            className="mt-1 block w-full"
                            isFocused
                            placeholder="owner@example.com"
                        />
                        <InputError message={inviteErrors.email} className="mt-2" />
                    </div>

                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={closeInviteModal}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton className="ms-3" disabled={processingInvite}>
                            Send Invite
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Feature Toggles Modal */}
            <Modal show={!!editingFeaturesTenant} onClose={closeFeaturesModal}>
                <form onSubmit={submitFeatures} className="p-6 space-y-4">
                    <h2 className="text-lg font-medium text-gray-900">
                        Manage Feature Toggles for {editingFeaturesTenant?.name}
                    </h2>

                    <div className="space-y-3 pt-2">
                        {[
                            { key: 'bot_auto_responder', label: 'Bot Auto-Responder & Keywords' },
                            { key: 'flow_builder', label: 'Visual Flow Builder' },
                            { key: 'ai_bot_llm', label: 'AI Bot Agent Automation' },
                            { key: 'contacts_bulk_messaging', label: 'Contacts & Bulk Campaigns' },
                            { key: 'template_management', label: 'Template Management' },
                        ].map((feat) => {
                            const isChecked = Boolean(featureData.features?.[feat.key]);
                            return (
                                <label key={feat.key} className="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-100 transition">
                                    <span className="text-sm font-semibold text-gray-800">{feat.label}</span>
                                    <input
                                        type="checkbox"
                                        checked={isChecked}
                                        onChange={(e) => {
                                            setFeatureData('features', {
                                                ...featureData.features,
                                                [feat.key]: e.target.checked
                                            });
                                        }}
                                        className="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500"
                                    />
                                </label>
                            );
                        })}
                    </div>

                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={closeFeaturesModal}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton className="ms-3" disabled={processingFeatures}>
                            Save Features
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Tenant Credentials Modal */}
            <Modal show={!!editingCredentialsTenant} onClose={closeCredentialsModal}>
                <form onSubmit={submitCredentials} className="p-6 space-y-4">
                    <h2 className="text-lg font-medium text-gray-900">
                        Manage API Credentials for {editingCredentialsTenant?.name}
                    </h2>

                    <div>
                        <InputLabel htmlFor="msg91_auth_key" value="MSG91 Auth Key" />
                        <TextInput
                            id="msg91_auth_key"
                            type="password"
                            value={credentialData.msg91_auth_key}
                            onChange={(e) => setCredentialData('msg91_auth_key', e.target.value)}
                            className="mt-1 block w-full font-mono text-sm"
                            placeholder="Leave blank to keep existing key"
                        />
                        <InputError message={credentialErrors.msg91_auth_key} className="mt-2" />
                    </div>

                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={closeCredentialsModal}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton className="ms-3" disabled={processingCredentials}>
                            Save Credentials
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Add Balance Modal */}
            <Modal show={!!balancingTenant} onClose={closeBalanceModal}>
                <form onSubmit={submitBalance} className="p-6">
                    <div className="flex items-center justify-between border-b border-gray-100 pb-3 mb-4">
                        <div>
                            <h2 className="text-lg font-bold text-gray-900">
                                Add Balance (Top-Up)
                            </h2>
                            <p className="text-xs text-gray-500">
                                Organization: <strong className="text-gray-800">{balancingTenant?.name}</strong>
                            </p>
                        </div>
                        <div className="text-right">
                            <span className="text-[10px] uppercase font-bold text-gray-400 block">Current Balance</span>
                            <span className={`text-sm font-mono font-extrabold ${parseFloat(balancingTenant?.balance || 0) > 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
                                ₹{parseFloat(balancingTenant?.balance || 0).toFixed(2)}
                            </span>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="balance_amount" value="Payment Amount Received (₹)" />
                            <TextInput
                                id="balance_amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                name="amount"
                                value={balanceData.amount}
                                onChange={(e) => setBalanceData('amount', e.target.value)}
                                className="mt-1 block w-full text-base font-bold font-mono"
                                isFocused
                                placeholder="e.g. 500.00"
                            />
                            <InputError message={balanceErrors.amount} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="payment_reference" value="Payment Reference / Transaction ID" />
                            <TextInput
                                id="payment_reference"
                                type="text"
                                name="payment_reference"
                                value={balanceData.payment_reference}
                                onChange={(e) => setBalanceData('payment_reference', e.target.value)}
                                className="mt-1 block w-full font-mono text-sm"
                                placeholder="e.g. UPI-20260918-0912 / Bank Wire / Cash"
                            />
                            <InputError message={balanceErrors.payment_reference} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="balance_notes" value="Notes / Internal Memo" />
                            <textarea
                                id="balance_notes"
                                rows={2}
                                value={balanceData.notes}
                                onChange={(e) => setBalanceData('notes', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                placeholder="e.g. 5,000 WhatsApp credits recharge received via UPI"
                            />
                            <InputError message={balanceErrors.notes} className="mt-2" />
                        </div>

                        <label className="flex items-center gap-2 p-3 bg-gray-50 rounded-xl border border-gray-200 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={balanceData.auto_activate}
                                onChange={(e) => setBalanceData('auto_activate', e.target.checked)}
                                className="w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500"
                            />
                            <span className="text-xs font-semibold text-gray-700">
                                Automatically reactivate tenant if currently suspended (Recommended)
                            </span>
                        </label>
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <SecondaryButton onClick={closeBalanceModal}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton className="bg-emerald-600 hover:bg-emerald-700" disabled={processingBalance}>
                            {processingBalance ? 'Adding...' : 'Confirm & Add Balance'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Tenant Balance Ledger Modal */}
            <Modal show={!!ledgerTenant} onClose={closeLedgerModal} maxWidth="2xl">
                <div className="p-6">
                    <div className="flex items-center justify-between border-b border-gray-100 pb-3 mb-4">
                        <div>
                            <h2 className="text-lg font-bold text-gray-900">
                                Transaction History & Balance Ledger
                            </h2>
                            <p className="text-xs text-gray-500">
                                Organization: <strong className="text-gray-800">{ledgerTenant?.name}</strong>
                            </p>
                        </div>
                        <div className="text-right">
                            <span className="text-[10px] uppercase font-bold text-gray-400 block">Current Balance</span>
                            <span className={`text-base font-mono font-extrabold ${parseFloat(ledgerTenant?.balance || 0) > 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
                                ₹{parseFloat(ledgerTenant?.balance || 0).toFixed(2)}
                            </span>
                        </div>
                    </div>

                    {ledgerLoading ? (
                        <div className="py-12 text-center text-gray-400 text-sm">
                            Loading transaction ledger...
                        </div>
                    ) : ledgerTransactions.length === 0 ? (
                        <div className="py-12 text-center text-gray-400 text-sm">
                            No balance transactions recorded yet for this organization.
                        </div>
                    ) : (
                        <div className="max-h-96 overflow-y-auto overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-xs">
                                <thead className="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-bold text-gray-500 uppercase">Date</th>
                                        <th className="px-3 py-2 text-left font-bold text-gray-500 uppercase">Type</th>
                                        <th className="px-3 py-2 text-right font-bold text-gray-500 uppercase">Amount</th>
                                        <th className="px-3 py-2 text-right font-bold text-gray-500 uppercase">Balance After</th>
                                        <th className="px-3 py-2 text-left font-bold text-gray-500 uppercase">Ref / Notes</th>
                                        <th className="px-3 py-2 text-left font-bold text-gray-500 uppercase">Admin</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {ledgerTransactions.map((tx) => (
                                        <tr key={tx.id} className="hover:bg-gray-50/60">
                                            <td className="px-3 py-2.5 whitespace-nowrap text-gray-500 font-mono">
                                                {new Date(tx.created_at).toLocaleString()}
                                            </td>
                                            <td className="px-3 py-2.5 whitespace-nowrap">
                                                <span className={`px-2 py-0.5 rounded-full font-bold uppercase text-[10px] ${
                                                    tx.type === 'credit' 
                                                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' 
                                                        : 'bg-rose-50 text-rose-700 border border-rose-200'
                                                }`}>
                                                    {tx.type}
                                                </span>
                                            </td>
                                            <td className={`px-3 py-2.5 whitespace-nowrap text-right font-mono font-bold ${
                                                tx.type === 'credit' ? 'text-emerald-600' : 'text-rose-600'
                                            }`}>
                                                {tx.type === 'credit' ? '+' : '-'}₹{parseFloat(tx.amount || 0).toFixed(4)}
                                            </td>
                                            <td className="px-3 py-2.5 whitespace-nowrap text-right font-mono font-semibold text-gray-700">
                                                ₹{parseFloat(tx.balance_after || 0).toFixed(4)}
                                            </td>
                                            <td className="px-3 py-2.5 max-w-xs truncate text-gray-600">
                                                {tx.payment_reference && (
                                                    <div className="font-mono text-[11px] text-indigo-600 font-semibold">{tx.payment_reference}</div>
                                                )}
                                                {tx.description && (
                                                    <div className="text-[11px] text-gray-500">{tx.description}</div>
                                                )}
                                            </td>
                                            <td className="px-3 py-2.5 whitespace-nowrap text-gray-500 text-[11px]">
                                                {tx.admin_user?.name || 'System'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={closeLedgerModal}>
                            Close
                        </SecondaryButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
