import React, { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout';

export default function TenantSettings({ tenantId, settings, numbers, meta, public_api_key_preview, new_public_api_key }) {
    const [photoPreview, setPhotoPreview] = useState(null);
    const [isLoadingProfile, setIsLoadingProfile] = useState(false);
    const [profileLoaded, setProfileLoaded] = useState(false);
    const [profileError, setProfileError] = useState(null);

    const { data, setData, post, processing, errors, reset, recentlySuccessful } = useForm({
        ai_provider: settings.ai_provider || 'openai',
        ai_model: settings.ai_model || '',
        ai_system_prompt: settings.ai_system_prompt || '',
        ai_is_active: settings.ai_is_active || false,
        ai_human_escalation_enabled: settings.ai_human_escalation_enabled ?? true,
        ai_confidence_threshold: settings.ai_confidence_threshold || 0.70,
    });

    const metaForm = useForm({
        meta_phone_number_id: settings.meta_phone_number_id || '',
        meta_waba_id: settings.meta_waba_id || '',
        meta_access_token: '',
        facebook_page_id: settings.facebook_page_id || '',
        instagram_account_id: settings.instagram_account_id || '',
    });

    const profileForm = useForm({
        about: '',
        description: '',
        address: '',
        email: '',
        vertical: 'OTHER',
        websites: ['', ''],
        profile_picture_url: '',
        profile_picture: null,
    });

    const widgetForm = useForm({
        widget_title: settings.widget_title || 'Chat with us on WhatsApp',
        widget_welcome_msg: settings.widget_welcome_msg || 'Hi there! How can we help you today?',
        widget_color: settings.widget_color || '#00a884',
        widget_position: settings.widget_position || 'bottom-right',
        widget_auto_redirect_wa: settings.widget_auto_redirect_wa ?? true,
        widget_target_phone: settings.widget_target_phone || (numbers && numbers.length > 0 ? numbers[0].integrated_number : ''),
    });

    const numberForm = useForm({
        country_code: '91',
        integrated_number: '',
    });

    // Lazy load WhatsApp Business Profile only when Meta credentials exist
    const fetchBusinessProfile = async () => {
        if (!meta?.is_configured) return;
        setIsLoadingProfile(true);
        setProfileError(null);
        try {
            const response = await axios.get(route('settings.tenant.business-profile.get'));
            if (response.data?.success && response.data?.data) {
                const p = response.data.data;
                const v = p.vertical && p.vertical !== 'UNDEFINED' ? p.vertical : 'OTHER';
                profileForm.setData({
                    about: p.about || '',
                    description: p.description || '',
                    address: p.address || '',
                    email: p.email || '',
                    vertical: v,
                    websites: p.websites && p.websites.length > 0 ? p.websites : ['', ''],
                    profile_picture_url: p.profile_picture_url || '',
                    profile_picture: null,
                });
                setProfileLoaded(true);
            } else {
                setProfileError(response.data?.error || 'Could not load WhatsApp profile from Meta.');
            }
        } catch (err) {
            setProfileError(err.response?.data?.error || err.message || 'Failed to connect to Meta Graph API.');
        } finally {
            setIsLoadingProfile(false);
        }
    };

    useEffect(() => {
        if (meta?.is_configured) {
            fetchBusinessProfile();
        }
    }, [meta?.is_configured]);

    const handleSaveKeys = (e) => {
        e.preventDefault();
        post(route('settings.tenant.update'), { preserveScroll: true });
    };

    const handleSaveMeta = (e) => {
        e.preventDefault();
        metaForm.post(route('settings.tenant.meta-credentials'), { 
            preserveScroll: true,
            onSuccess: () => {
                metaForm.reset('meta_access_token');
                fetchBusinessProfile();
            }
        });
    };

    const handleSaveProfile = (e) => {
        e.preventDefault();
        profileForm.post(route('settings.tenant.business-profile'), { 
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                fetchBusinessProfile();
                setPhotoPreview(null);
            }
        });
    };

    const handleSaveWidget = (e) => {
        e.preventDefault();
        widgetForm.post(route('settings.tenant.update'), { preserveScroll: true });
    };

    const handleAddNumber = (e) => {
        e.preventDefault();
        numberForm.post(route('settings.tenant.numbers.store'), {
            onSuccess: () => numberForm.reset(),
        });
    };

    const handleRegenerateApiKey = () => {
        if (confirm('Are you sure you want to generate a new API key? This will immediately revoke the previous key.')) {
            router.post(route('settings.tenant.api-key'), {}, { preserveScroll: true });
        }
    };

    const handlePhotoChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            profileForm.setData('profile_picture', file);
            setPhotoPreview(URL.createObjectURL(file));
        }
    };

    const embedScript = `<script src="${window.location.origin}/widget/v1/${tenantId}.js" async></script>`;

    const verticals = [
        { value: 'OTHER', label: 'Other' },
        { value: 'AUTO', label: 'Automotive' },
        { value: 'BEAUTY', label: 'Beauty, Spa & Salon' },
        { value: 'APPAREL', label: 'Clothing & Apparel' },
        { value: 'EDU', label: 'Education' },
        { value: 'ENTERTAIN', label: 'Entertainment' },
        { value: 'EVENT_PLAN', label: 'Event Planning & Service' },
        { value: 'FINANCE', label: 'Finance & Banking' },
        { value: 'GROCERY', label: 'Grocery & Supermarket' },
        { value: 'GOVT', label: 'Public Service / Government' },
        { value: 'HOTEL', label: 'Hotel & Lodging' },
        { value: 'HEALTH', label: 'Medical & Health' },
        { value: 'NONPROFIT', label: 'Non-profit Organization' },
        { value: 'PROF_SERVICES', label: 'Professional Services' },
        { value: 'RESTAURANT', label: 'Restaurant' },
        { value: 'RETAIL', label: 'Retail & Shopping' },
        { value: 'TRAVEL', label: 'Travel & Transportation' },
    ];

    return (
        <AppLayout>
            <Head title="Tenant API & WhatsApp Settings" />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Header */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs">
                    <h1 className="text-xl font-extrabold text-gray-900 tracking-tight flex items-center gap-2">
                        <span>🔑</span> Tenant API & WhatsApp Integration Settings
                    </h1>
                    <p className="text-xs text-gray-500 mt-1">
                        Configure WhatsApp Business Profile, Meta Cloud API, AI Assistants, and Website Lead Widgets.
                    </p>
                </div>

                {/* WhatsApp Business Profile Manager */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-5">
                    <div className="flex items-center justify-between border-b border-gray-100 pb-3">
                        <div className="flex items-center gap-2">
                            <span className="text-base font-bold text-gray-900">🏢 WhatsApp Business Profile</span>
                            {meta?.is_configured && (
                                <button
                                    type="button"
                                    onClick={fetchBusinessProfile}
                                    disabled={isLoadingProfile}
                                    className="text-xs text-gray-400 hover:text-gray-700 p-1 rounded-md transition"
                                    title="Refresh profile from Meta"
                                >
                                    🔄
                                </button>
                            )}
                        </div>
                        {meta?.is_configured ? (
                            <span className="px-2.5 py-0.5 bg-emerald-50 text-[#00a884] border border-emerald-200 rounded-full text-[10px] font-bold">
                                Meta Cloud API Connected
                            </span>
                        ) : (
                            <span className="px-2.5 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-full text-[10px] font-bold">
                                Meta Credentials Required Below
                            </span>
                        )}
                    </div>

                    {!meta?.is_configured ? (
                        <div className="p-4 bg-gray-50 rounded-xl border border-dashed border-gray-200 text-center space-y-2">
                            <p className="text-xs text-gray-600 font-medium">
                                To manage your public WhatsApp Business Profile (Photo, About, Address, Description), please configure your <strong>Meta Phone Number ID</strong> and <strong>Access Token</strong> in the Meta Credentials section below.
                            </p>
                        </div>
                    ) : (
                        <form onSubmit={handleSaveProfile} className="space-y-4">
                            {isLoadingProfile && (
                                <div className="flex items-center gap-2 p-3 bg-blue-50 border border-blue-200 rounded-xl text-blue-700 text-xs font-semibold">
                                    <svg className="w-4 h-4 animate-spin shrink-0" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <span>Syncing business profile details from Meta Cloud API...</span>
                                </div>
                            )}

                            {profileError && (
                                <div className="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs font-medium flex items-center justify-between">
                                    <span>⚠️ {profileError}</span>
                                    <button 
                                        type="button" 
                                        onClick={fetchBusinessProfile} 
                                        className="text-xs font-bold text-amber-900 underline ml-2 shrink-0"
                                    >
                                        Retry
                                    </button>
                                </div>
                            )}

                            {/* Profile Picture & Live Preview */}
                            <div className="flex items-center gap-5 p-4 bg-gray-50/70 rounded-xl border border-gray-100">
                                <div className="relative w-16 h-16 rounded-full overflow-hidden border-2 border-[#00a884] bg-emerald-50 shrink-0 flex items-center justify-center text-gray-400 font-bold text-sm shadow-sm">
                                    {photoPreview ? (
                                        <img src={photoPreview} alt="Preview" className="w-full h-full object-cover" />
                                    ) : profileForm.data.profile_picture_url ? (
                                        <img src={profileForm.data.profile_picture_url} alt="Profile" className="w-full h-full object-cover" />
                                    ) : (
                                        <span>WA</span>
                                    )}
                                </div>
                                <div className="flex-1 space-y-1">
                                    <label className="block text-xs font-bold text-gray-800">Business Profile Photo</label>
                                    <input 
                                        type="file" 
                                        accept="image/jpeg,image/png"
                                        onChange={handlePhotoChange}
                                        className="text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#00a884] file:text-white hover:file:bg-[#008f70] cursor-pointer"
                                    />
                                    <p className="text-[10px] text-gray-400">Recommended 640x640 JPG/PNG. Max size: 5MB.</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">
                                        About Text <span className="text-gray-400 font-normal">(Max 139 chars)</span>
                                    </label>
                                    <input
                                        type="text"
                                        maxLength="139"
                                        value={profileForm.data.about}
                                        onChange={e => profileForm.setData('about', e.target.value)}
                                        placeholder="e.g. Premium Fashion & Accessories"
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">Business Category (Vertical)</label>
                                    <select
                                        value={profileForm.data.vertical}
                                        onChange={e => profileForm.setData('vertical', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs outline-none bg-white"
                                    >
                                        {verticals.map(v => (
                                            <option key={v.value} value={v.value}>{v.label}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-700 mb-1">
                                    Business Description <span className="text-gray-400 font-normal">(Max 512 chars)</span>
                                </label>
                                <textarea
                                    maxLength="512"
                                    rows="3"
                                    value={profileForm.data.description}
                                    onChange={e => profileForm.setData('description', e.target.value)}
                                    placeholder="Describe your services, working hours, and what makes your business unique..."
                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                ></textarea>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">Business Contact Email</label>
                                    <input
                                        type="email"
                                        value={profileForm.data.email}
                                        onChange={e => profileForm.setData('email', e.target.value)}
                                        placeholder="support@company.com"
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">Business Physical Address</label>
                                    <input
                                        type="text"
                                        value={profileForm.data.address}
                                        onChange={e => profileForm.setData('address', e.target.value)}
                                        placeholder="123 Business Boulevard, City, Country"
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">Website URL 1</label>
                                    <input
                                        type="url"
                                        value={profileForm.data.websites[0] || ''}
                                        onChange={e => {
                                            const updated = [...profileForm.data.websites];
                                            updated[0] = e.target.value;
                                            profileForm.setData('websites', updated);
                                        }}
                                        placeholder="https://company.com"
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">Website URL 2 (Optional)</label>
                                    <input
                                        type="url"
                                        value={profileForm.data.websites[1] || ''}
                                        onChange={e => {
                                            const updated = [...profileForm.data.websites];
                                            updated[1] = e.target.value;
                                            profileForm.setData('websites', updated);
                                        }}
                                        placeholder="https://store.company.com"
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end pt-2">
                                <button
                                    type="submit"
                                    disabled={profileForm.processing}
                                    className="flex items-center gap-2 px-5 py-2 bg-[#00a884] hover:bg-[#008f70] text-white rounded-xl text-xs font-bold transition shadow-xs disabled:opacity-50"
                                >
                                    {profileForm.processing ? 'Syncing with Meta...' : 'Save & Sync WhatsApp Profile'}
                                </button>
                            </div>
                        </form>
                    )}
                </div>

                {/* Meta Cloud API & Omnichannel Credentials */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-5">
                    <h2 className="text-base font-bold text-gray-900 border-b border-gray-100 pb-3 flex items-center justify-between">
                        <span>📱 Meta Cloud API & Omnichannel Credentials</span>
                        {settings.has_meta_access_token && (
                            <span className="text-[10px] px-2 py-0.5 bg-emerald-50 text-[#00a884] border border-emerald-200 rounded-md font-bold">
                                Token Encrypted At Rest 🔒
                            </span>
                        )}
                    </h2>

                    <form onSubmit={handleSaveMeta} className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-bold text-gray-700 mb-1">WhatsApp Phone Number ID</label>
                                <input
                                    type="text"
                                    value={metaForm.data.meta_phone_number_id}
                                    onChange={e => metaForm.setData('meta_phone_number_id', e.target.value)}
                                    placeholder="e.g. 1247778671756217"
                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-700 mb-1">WhatsApp Business Account ID (WABA ID)</label>
                                <input
                                    type="text"
                                    value={metaForm.data.meta_waba_id}
                                    onChange={e => metaForm.setData('meta_waba_id', e.target.value)}
                                    placeholder="e.g. 109827364512938"
                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-gray-700 mb-1">
                                Meta System User Access Token
                                {settings.has_meta_access_token && (
                                    <span className="text-emerald-600 font-normal ml-2 text-[10px]">
                                        (Current token is saved & active. Enter a new token only to replace it)
                                    </span>
                                )}
                            </label>
                            <input
                                type="password"
                                value={metaForm.data.meta_access_token}
                                onChange={e => metaForm.setData('meta_access_token', e.target.value)}
                                placeholder={settings.has_meta_access_token ? '••••••••••••••••••••••••••••••••' : 'EAAN76kLhvIIBA...'}
                                className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                            />
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                            <div>
                                <label className="block text-xs font-bold text-gray-700 mb-1">Facebook Page ID (Messenger)</label>
                                <input
                                    type="text"
                                    value={metaForm.data.facebook_page_id}
                                    onChange={e => metaForm.setData('facebook_page_id', e.target.value)}
                                    placeholder="e.g. 1029384756"
                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-gray-700 mb-1">Instagram Business Account ID</label>
                                <input
                                    type="text"
                                    value={metaForm.data.instagram_account_id}
                                    onChange={e => metaForm.setData('instagram_account_id', e.target.value)}
                                    placeholder="e.g. 17841400123456789"
                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                />
                            </div>
                        </div>

                        <div className="flex justify-end pt-2">
                            <button
                                type="submit"
                                disabled={metaForm.processing}
                                className="px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white rounded-xl text-xs font-bold transition disabled:opacity-50"
                            >
                                {metaForm.processing ? 'Saving...' : 'Save Meta Credentials'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Public API Key */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-5">
                    <h2 className="text-base font-bold text-gray-900 border-b border-gray-100 pb-3">Public API Configuration</h2>
                    
                    {new_public_api_key && (
                        <div className="p-4 bg-amber-50 border border-amber-200 rounded-xl space-y-2">
                            <div className="flex items-center gap-2 text-amber-800 font-bold text-xs">
                                <span>⚠️</span> Save Your Public API Key Now!
                            </div>
                            <p className="text-xs text-amber-700">
                                This key will <strong>never be shown again</strong>. Store it securely in your website's server environment.
                            </p>
                            <div className="flex items-center gap-2 mt-2">
                                <input
                                    type="text"
                                    readOnly
                                    value={new_public_api_key}
                                    className="w-full px-3 py-2 bg-white border border-amber-300 rounded-lg text-xs font-mono font-bold text-gray-900 outline-none select-all"
                                />
                                <button
                                    type="button"
                                    onClick={() => {
                                        navigator.clipboard.writeText(new_public_api_key);
                                        alert('API Key copied to clipboard!');
                                    }}
                                    className="px-3 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-bold text-xs transition"
                                >
                                    Copy
                                </button>
                            </div>
                        </div>
                    )}

                    <div className="pt-2">
                        <label className="block text-xs font-bold text-gray-700 mb-1">Public Ingestion API Key</label>
                        <div className="flex items-center gap-4">
                            <input
                                type="text"
                                readOnly
                                value={public_api_key_preview || 'No API key generated yet.'}
                                className="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono text-gray-600 outline-none"
                            />
                            <button
                                type="button"
                                onClick={handleRegenerateApiKey}
                                className="whitespace-nowrap px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg font-semibold hover:bg-indigo-100 transition border border-indigo-200/80 text-xs"
                            >
                                {public_api_key_preview ? 'Regenerate Key' : 'Generate Key'}
                            </button>
                        </div>
                        <p className="text-[10px] text-gray-500 mt-2">
                            Use this key as a Bearer token to authorize requests to <code>POST /api/v1/contacts</code>.
                        </p>
                    </div>
                </div>

                {/* Website WhatsApp Lead Widget Builder */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-5">
                    <form onSubmit={handleSaveWidget} className="space-y-5">
                        <h2 className="text-base font-bold text-gray-900 border-b border-gray-100 pb-3 flex items-center justify-between">
                            <span>💬 Website WhatsApp Lead Widget Builder</span>
                            <span className="text-xs font-normal text-gray-500">Zero-code website integration</span>
                        </h2>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Customization Form Controls */}
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">Widget Title</label>
                                    <input
                                        type="text"
                                        value={widgetForm.data.widget_title}
                                        onChange={e => widgetForm.setData('widget_title', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">Welcome Subtitle</label>
                                    <textarea
                                        value={widgetForm.data.widget_welcome_msg}
                                        onChange={e => widgetForm.setData('widget_welcome_msg', e.target.value)}
                                        rows="2"
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                    ></textarea>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-bold text-gray-700 mb-1">Brand Color</label>
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="color"
                                                value={widgetForm.data.widget_color}
                                                onChange={e => widgetForm.setData('widget_color', e.target.value)}
                                                className="w-8 h-8 rounded border border-gray-200 cursor-pointer p-0"
                                            />
                                            <input
                                                type="text"
                                                value={widgetForm.data.widget_color}
                                                onChange={e => widgetForm.setData('widget_color', e.target.value)}
                                                className="w-full px-3 py-1.5 border border-gray-200 rounded-xl text-xs font-mono"
                                            />
                                        </div>
                                        {widgetForm.errors.widget_color && (
                                            <p className="text-[10px] text-rose-500 mt-1 font-semibold">{widgetForm.errors.widget_color}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-gray-700 mb-1">Position</label>
                                        <select
                                            value={widgetForm.data.widget_position}
                                            onChange={e => widgetForm.setData('widget_position', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs outline-none"
                                        >
                                            <option value="bottom-right">Bottom Right</option>
                                            <option value="bottom-left">Bottom Left</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">Target WhatsApp Number</label>
                                    <select
                                        value={widgetForm.data.widget_target_phone}
                                        onChange={e => widgetForm.setData('widget_target_phone', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs outline-none"
                                    >
                                        <option value="">-- Select Integrated Number --</option>
                                        {numbers.map(num => (
                                            <option key={num.id} value={num.integrated_number}>
                                                +{num.integrated_number}
                                            </option>
                                        ))}
                                    </select>
                                    {widgetForm.errors.widget_target_phone && (
                                        <p className="text-[10px] text-rose-500 mt-1 font-semibold">{widgetForm.errors.widget_target_phone}</p>
                                    )}
                                </div>

                                <div className="pt-2 flex items-center justify-between">
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={widgetForm.data.widget_auto_redirect_wa}
                                            onChange={e => widgetForm.setData('widget_auto_redirect_wa', e.target.checked)}
                                            className="rounded border-gray-300 text-[#00a884] focus:ring-[#00a884]"
                                        />
                                        <span className="text-xs font-bold text-gray-700">Auto-redirect to WhatsApp</span>
                                    </label>

                                    <button
                                        type="submit"
                                        disabled={widgetForm.processing}
                                        className="px-4 py-2 bg-[#00a884] hover:bg-[#008f70] text-white rounded-xl text-xs font-bold transition shadow-xs disabled:opacity-50"
                                    >
                                        Save Widget Settings
                                    </button>
                                </div>
                            </div>

                            {/* Live Widget Interactive Preview */}
                            <div className="bg-gray-50 border border-gray-200/80 rounded-xl p-4 flex flex-col justify-between relative min-h-[300px]">
                                <div className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Live Website Preview</div>
                                
                                {/* Floating Card Popup Preview */}
                                <div className="w-full bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden my-auto">
                                    <div style={{ backgroundColor: widgetForm.data.widget_color }} className="p-3 text-white">
                                        <div className="font-bold text-xs">{widgetForm.data.widget_title}</div>
                                        <div className="text-[10px] opacity-90">{widgetForm.data.widget_welcome_msg}</div>
                                    </div>
                                    <div className="p-3 space-y-2 text-xs">
                                        <input type="text" disabled placeholder="Your Name" className="w-full px-2 py-1 border rounded text-[11px] bg-gray-50" />
                                        <input type="tel" disabled placeholder="Phone Number" className="w-full px-2 py-1 border rounded text-[11px] bg-gray-50" />
                                        <button style={{ backgroundColor: widgetForm.data.widget_color }} className="w-full py-1.5 text-white font-bold rounded text-[11px] opacity-90 cursor-default">
                                            Start Chat
                                        </button>
                                    </div>
                                </div>

                                {/* Floating Button Icon Preview */}
                                <div className={`flex ${widgetForm.data.widget_position === 'bottom-left' ? 'justify-start' : 'justify-end'} mt-2`}>
                                    <div style={{ backgroundColor: widgetForm.data.widget_color }} className="w-10 h-10 rounded-full flex items-center justify-center shadow-md">
                                        <svg className="w-5 h-5 fill-white" viewBox="0 0 32 32">
                                            <path d="M16 2A13 13 0 0 0 4.68 21.27L3 27.5l6.38-1.66A13 13 0 1 0 16 2zm0 24a11 11 0 0 1-5.61-1.54l-.4-.24-3.79.99 1.01-3.69-.26-.41A11 11 0 1 1 16 26zm6.05-8.23c-.33-.17-1.96-.97-2.27-1.08-.31-.11-.53-.17-.75.17s-.86 1.08-1.05 1.3-.39.25-.72.08a9.12 9.12 0 0 1-2.67-1.65 10.07 10.07 0 0 1-1.85-2.3c-.19-.33 0-.51.15-.67.14-.14.33-.39.49-.58.17-.19.22-.33.33-.55.11-.22.06-.41-.03-.58s-.75-1.81-1.03-2.48c-.27-.65-.55-.56-.75-.57h-.64c-.22 0-.58.08-.88.41s-1.16 1.13-1.16 2.76 1.19 3.2 1.35 3.42 2.34 3.57 5.67 5.01c.79.34 1.41.55 1.89.7.79.25 1.51.22 2.08.13.63-.09 1.96-.8 2.24-1.57.28-.77.28-1.43.19-1.57-.08-.14-.3-.22-.63-.38z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Copy Snippet Code Box */}
                        <div className="pt-4 border-t border-gray-100">
                            <label className="block text-xs font-bold text-gray-700 mb-1">Copy Website Embed Code</label>
                            <div className="flex items-center gap-2">
                                <input
                                    type="text"
                                    readOnly
                                    value={embedScript}
                                    className="w-full px-3 py-2 bg-gray-900 text-emerald-400 font-mono rounded-xl text-xs outline-none select-all"
                                />
                                <button
                                    type="button"
                                    onClick={() => {
                                        navigator.clipboard.writeText(embedScript);
                                        alert('Embed script copied to clipboard! Paste it before the </body> tag of your website.');
                                    }}
                                    className="whitespace-nowrap px-4 py-2 bg-[#00a884] text-white rounded-xl font-bold hover:bg-[#008f70] text-xs transition"
                                >
                                    Copy Script
                                </button>
                            </div>
                            <p className="text-[10px] text-gray-500 mt-1">Paste this script before the closing <code>&lt;/body&gt;</code> tag on WordPress, Shopify, Wix, or HTML sites.</p>
                        </div>
                    </form>
                </div>

                {/* AI Configuration Form */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-5">
                    <h2 className="text-base font-bold text-gray-900 border-b border-gray-100 pb-3">AI Bot Configuration</h2>

                    <form onSubmit={handleSaveKeys} className="space-y-4">
                        <div className="pt-2">
                            
                            <div className="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">AI Provider</label>
                                    <select
                                        value={data.ai_provider}
                                        onChange={(e) => setData('ai_provider', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                    >
                                        <option value="openai">OpenAI (Direct LLM)</option>
                                        <option value="grok">Grok (xAI)</option>
                                        <option value="gemini">Gemini (Google AI)</option>
                                        <option value="flowise">Flowise (LangChain/RAG)</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">AI Model Name</label>
                                    <input
                                        type="text"
                                        value={data.ai_model}
                                        onChange={(e) => setData('ai_model', e.target.value)}
                                        placeholder={
                                            data.ai_provider === 'openai' ? 'gpt-4o-mini' : 
                                            data.ai_provider === 'grok' ? 'grok-2-mini' : 
                                            data.ai_provider === 'gemini' ? 'gemini-3.1-flash-lite' : 
                                            'model-name'
                                        }
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                    />
                                </div>
                            </div>

                            <div className="mb-4">
                                <label className="block text-xs font-bold text-gray-700 mb-1">System Prompt Context</label>
                                <textarea
                                    value={data.ai_system_prompt}
                                    onChange={(e) => setData('ai_system_prompt', e.target.value)}
                                    placeholder="You are a helpful customer service assistant for our company..."
                                    rows="4"
                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none"
                                ></textarea>
                            </div>

                            <div className="mb-4">
                                <label className="block text-xs font-bold text-gray-700 mb-1">
                                    AI Confidence Threshold (0.0 to 1.0)
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="1"
                                    value={data.ai_confidence_threshold}
                                    onChange={(e) => setData('ai_confidence_threshold', e.target.value)}
                                    className={`w-full px-3 py-2 border rounded-xl text-xs outline-none transition-colors ${
                                        errors.ai_confidence_threshold 
                                            ? 'border-rose-500 focus:ring-rose-200' 
                                            : 'border-gray-200 focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884]'
                                    }`}
                                />
                                {errors.ai_confidence_threshold && <p className="text-[10px] text-rose-500 mt-1 font-semibold">{errors.ai_confidence_threshold}</p>}
                                <p className="text-[10px] text-gray-400 mt-1">If the model scores below this confidence level, it will not answer.</p>
                            </div>

                            <div className="flex gap-6 mt-2">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.ai_is_active}
                                        onChange={(e) => setData('ai_is_active', e.target.checked)}
                                        className="rounded border-gray-300 text-[#00a884] focus:ring-[#00a884]"
                                    />
                                    <span className="text-xs font-bold text-gray-700">Enable AI Fallback Bot</span>
                                </label>

                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.ai_human_escalation_enabled}
                                        onChange={(e) => setData('ai_human_escalation_enabled', e.target.checked)}
                                        className="rounded border-gray-300 text-[#00a884] focus:ring-[#00a884]"
                                    />
                                    <span className="text-xs font-bold text-gray-700">Escalate to Human on Low Confidence</span>
                                </label>
                            </div>
                        </div>

                        <div className="flex justify-end pt-3 items-center gap-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex items-center gap-2 px-5 py-2.5 bg-[#00a884] hover:bg-[#008f70] text-white rounded-xl text-xs font-semibold transition shadow-md shadow-emerald-500/20 disabled:opacity-50"
                            >
                                {processing && (
                                    <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                )}
                                {processing ? 'Saving...' : 'Save API Credentials'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* WhatsApp Numbers Registration */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-xs space-y-5">
                    <h2 className="text-base font-bold text-gray-900 border-b border-gray-100 pb-3">Registered WhatsApp Integrated Numbers</h2>

                    <form onSubmit={handleAddNumber} className="flex gap-3 items-start">
                        <div className="flex-none w-36">
                            <select
                                value={numberForm.data.country_code}
                                onChange={(e) => numberForm.setData('country_code', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-xl text-xs outline-none transition-colors border-gray-200 focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] bg-white`}
                            >
                                <option value="91">+91 (India)</option>
                                <option value="1">+1 (US/Canada)</option>
                                <option value="44">+44 (UK)</option>
                                <option value="61">+61 (Australia)</option>
                                <option value="">None (Raw)</option>
                            </select>
                        </div>
                        <div className="flex-1">
                            <input
                                type="text"
                                value={numberForm.data.integrated_number}
                                onChange={(e) => numberForm.setData('integrated_number', e.target.value)}
                                placeholder="e.g. 917425889008"
                                className={`w-full px-3 py-2 border rounded-xl text-xs outline-none transition-colors ${
                                    numberForm.errors.integrated_number 
                                        ? 'border-rose-500 focus:ring-rose-200' 
                                        : 'border-gray-200 focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884]'
                                }`}
                                required
                            />
                            {numberForm.errors.integrated_number && (
                                <p className="text-[10px] text-rose-500 mt-1 font-semibold">{numberForm.errors.integrated_number}</p>
                            )}
                        </div>
                        <button
                            type="submit"
                            disabled={numberForm.processing}
                            className="flex items-center gap-2 px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white rounded-xl text-xs font-semibold transition disabled:opacity-50"
                        >
                            {numberForm.processing && (
                                <svg className="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            )}
                            + Add Number
                        </button>
                    </form>

                    <div className="divide-y divide-gray-100 border border-gray-100 rounded-xl overflow-hidden">
                        {numbers.length === 0 ? (
                            <div className="p-4 text-center text-xs text-gray-400">No WhatsApp numbers registered yet.</div>
                        ) : (
                            numbers.map((num) => (
                                <div key={num.id} className="p-3.5 flex items-center justify-between text-xs font-semibold text-gray-800 bg-gray-50/50">
                                    <div className="flex items-center gap-3">
                                        <span className="font-mono">📱 +{num.integrated_number}</span>
                                        <span className="px-2 py-0.5 bg-emerald-50 text-[#00a884] rounded-md text-[10px] font-bold">Active</span>
                                    </div>
                                    <button 
                                        type="button"
                                        onClick={() => {
                                            if(confirm('Are you sure you want to remove this number?')) {
                                                router.delete(route('settings.tenant.numbers.destroy', num.integrated_number));
                                            }
                                        }}
                                        className="px-2 py-1 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-md text-[10px] font-bold transition-colors"
                                    >
                                        Remove
                                    </button>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
