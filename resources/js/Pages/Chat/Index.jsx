import { Head, Link, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Sidebar from './Sidebar';
import Thread from './Thread';

/**
 * Render the WhatsApp-style inbox for browsing and viewing conversations.
 * @param {Object} props - Component properties.
 * @param {Object} props.auth - Authentication data used to identify the current user.
 * @param {Array} props.tenantNumbers - Tenant numbers available for filtering conversations.
 */
export default function ChatIndex({ auth, tenantNumbers, approvedTemplates, currentChannel = 'whatsapp', channelCounts = {}, socialSettings = {} }) {
    const [conversations, setConversations] = useState([]);
    const [activeConversation, setActiveConversation] = useState(null);
    const [selectedNumberId, setSelectedNumberId] = useState(null);

    // Keep global state in sync for the websocket closure
    useEffect(() => {
        window.activeConversationId = activeConversation?.id || null;
    }, [activeConversation]);

    const { impersonation } = usePage().props;
    const resolvedTenantId = impersonation?.is_impersonating ? impersonation.tenant_id : auth?.user?.tenant_id;

    const fetchConversations = () => {
        let url = '/api/conversations';
        const params = [];
        if (selectedNumberId) {
            params.push(`tenant_number_id=${selectedNumberId}`);
        }
        if (currentChannel && currentChannel !== 'all') {
            params.push(`channel=${currentChannel}`);
        }
        if (params.length > 0) {
            url += `?${params.join('&')}`;
        }
        window.axios.get(url).then(res => {
            setConversations(res.data);
        });
    };

    useEffect(() => {
        fetchConversations();
        const interval = setInterval(fetchConversations, 3000); // Fast polling fallback

        // Request Browser Notification Permission on Load
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Real-time Sidebar Updates
        let channel = null;
        
        if (resolvedTenantId) {
            channel = window.Echo.private(`tenant.${resolvedTenantId}`);
            channel.listen('.message.received', (e) => {
                fetchConversations();
                
                // Show Desktop Notification if it's an inbound message and NOT the active chat
                if (e.message && e.message.direction === 'inbound') {
                    const isMuted = window.activeConversationId === e.message.conversation_id && document.visibilityState === 'visible';
                    
                    if (!isMuted && 'Notification' in window && Notification.permission === 'granted') {
                        let textStr = 'New Message';
                        try {
                            const parsed = typeof e.message.content === 'string' ? JSON.parse(e.message.content) : e.message.content;
                            if (parsed && typeof parsed === 'string') {
                                textStr = JSON.parse(parsed).text || textStr;
                            } else if (parsed && parsed.text) {
                                textStr = parsed.text;
                            } else if (parsed && parsed.type) {
                                textStr = '📷 ' + parsed.type;
                            }
                        } catch(err) {}

                        const channelLabel = e.message.channel === 'facebook' ? 'Facebook Messenger' : e.message.channel === 'instagram' ? 'Instagram Direct' : 'WhatsApp';

                        new Notification(`New ${channelLabel} Message`, {
                            body: textStr,
                            icon: '/favicon.ico'
                        });
                    }
                }
            });
        }

        return () => {
            clearInterval(interval);
            if (channel) {
                window.Echo.leave(`tenant.${resolvedTenantId}`);
            }
        };
    }, [selectedNumberId, currentChannel]);

    const handleSelectConversation = (conv) => {
        setActiveConversation(conv);
        if (conv.unread_count > 0) {
            setConversations(prev => prev.map(c => c.id === conv.id ? { ...c, unread_count: 0 } : c));
            window.axios.post(`/api/conversations/${conv.id}/read`).catch(console.error);
        }
    };

    const handleConversationsDeleted = (deletedIds) => {
        if (activeConversation && deletedIds.includes(activeConversation.id)) {
            setActiveConversation(null);
        }
        fetchConversations();
    };

    const channelTabs = [
        {
            id: 'whatsapp',
            label: 'WhatsApp',
            href: '/chat/whatsapp',
            count: channelCounts.whatsapp ?? 0,
            unread: channelCounts.whatsapp_unread ?? 0,
            icon: (
                <svg className="w-4 h-4 fill-current" viewBox="0 0 24 24">
                    <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0012.04 2zm.01 16.59c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.32a8.192 8.192 0 01-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.194 8.194 0 012.41 5.83c0 4.54-3.7 8.24-8.24 8.24z"/>
                </svg>
            ),
            activeClass: 'bg-[#00a884] text-[#111b21] shadow-md shadow-emerald-500/20 font-bold',
            inactiveClass: 'text-[#8696a0] hover:text-[#e9edef] hover:bg-[#202c33]'
        },
        {
            id: 'facebook',
            label: 'Facebook Messenger',
            href: '/chat/facebook',
            count: channelCounts.facebook ?? 0,
            unread: channelCounts.facebook_unread ?? 0,
            icon: (
                <svg className="w-4 h-4 fill-current" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.03 2 11c0 2.87 1.48 5.43 3.78 7.04V22l3.82-2.1c.78.22 1.61.34 2.4.34 5.52 0 10-4.03 10-9s-4.48-9-10-9zm1.06 12.13l-2.62-2.8-5.12 2.8 5.63-5.98 2.68 2.8 5.06-2.8-5.63 5.98z"/>
                </svg>
            ),
            activeClass: 'bg-[#1877f2] text-white shadow-md shadow-blue-500/20 font-bold',
            inactiveClass: 'text-[#8696a0] hover:text-[#1877f2] hover:bg-[#202c33]'
        },
        {
            id: 'instagram',
            label: 'Instagram Direct',
            href: '/chat/instagram',
            count: channelCounts.instagram ?? 0,
            unread: channelCounts.instagram_unread ?? 0,
            icon: (
                <svg className="w-4 h-4 fill-current" viewBox="0 0 24 24">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
            ),
            activeClass: 'bg-gradient-to-tr from-[#f09433] via-[#dc2743] to-[#bc1888] text-white shadow-md shadow-pink-500/20 font-bold',
            inactiveClass: 'text-[#8696a0] hover:text-pink-400 hover:bg-[#202c33]'
        },
        {
            id: 'all',
            label: 'All Channels',
            href: '/chat/all',
            count: channelCounts.all ?? 0,
            unread: (channelCounts.whatsapp_unread || 0) + (channelCounts.facebook_unread || 0) + (channelCounts.instagram_unread || 0),
            icon: (
                <svg className="w-4 h-4 fill-current" viewBox="0 0 24 24">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                </svg>
            ),
            activeClass: 'bg-[#2a3942] text-[#e9edef] ring-1 ring-[#3b4a54] font-bold',
            inactiveClass: 'text-[#8696a0] hover:text-[#e9edef] hover:bg-[#202c33]'
        }
    ];

    const currentTab = channelTabs.find(t => t.id === currentChannel) || channelTabs[0];

    return (
        <AppLayout>
            <Head title={`${currentTab.label} - Live Inbox`} />

            <div className="flex flex-col h-[calc(100vh-112px)] w-full overflow-hidden bg-[#0c1317] font-sans antialiased text-[#e9edef] rounded-2xl border border-gray-200/80 shadow-sm">
                {/* DEDICATED TOP-LEVEL CHANNEL SELECTOR BAR */}
                <div className="flex items-center justify-between px-4 py-2.5 bg-[#111b21] border-b border-[#222d34] flex-shrink-0">
                    {/* Left: Platform Tabs */}
                    <div className="flex items-center gap-2 overflow-x-auto custom-scrollbar">
                        {channelTabs.map(tab => {
                            const isActive = currentChannel === tab.id;
                            return (
                                <Link
                                    key={tab.id}
                                    href={tab.href}
                                    className={`flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs transition-all duration-150 shrink-0 ${
                                        isActive ? tab.activeClass : tab.inactiveClass
                                    }`}
                                >
                                    <span>{tab.icon}</span>
                                    <span>{tab.label}</span>
                                    {tab.unread > 0 ? (
                                        <span className={`text-[10px] font-black px-1.5 py-0.5 rounded-full ${
                                            isActive && tab.id === 'whatsapp' ? 'bg-[#111b21] text-[#00a884]' : 'bg-rose-500 text-white'
                                        }`}>
                                            {tab.unread}
                                        </span>
                                    ) : (
                                        <span className={`text-[10px] opacity-75 font-medium`}>
                                            ({tab.count})
                                        </span>
                                    )}
                                </Link>
                            );
                        })}
                    </div>

                    {/* Right: Channel Status Indicator */}
                    <div className="hidden sm:flex items-center gap-3 text-xs text-[#8696a0]">
                        {currentChannel === 'whatsapp' && (
                            <span className="flex items-center gap-1.5 text-[#00a884] font-medium bg-[#00a884]/10 px-2.5 py-1 rounded-lg border border-[#00a884]/30">
                                <span className="w-2 h-2 rounded-full bg-[#25d366] animate-pulse"></span>
                                WhatsApp Gateway Active
                            </span>
                        )}
                        {currentChannel === 'facebook' && (
                            <span className="flex items-center gap-1.5 text-[#1877f2] font-medium bg-[#1877f2]/10 px-2.5 py-1 rounded-lg border border-[#1877f2]/30">
                                <span className="w-2 h-2 rounded-full bg-[#1877f2] animate-pulse"></span>
                                {socialSettings.has_facebook ? 'Facebook Page Connected' : 'Facebook Ready'}
                            </span>
                        )}
                        {currentChannel === 'instagram' && (
                            <span className="flex items-center gap-1.5 text-pink-400 font-medium bg-pink-500/10 px-2.5 py-1 rounded-lg border border-pink-500/30">
                                <span className="w-2 h-2 rounded-full bg-pink-500 animate-pulse"></span>
                                {socialSettings.has_instagram ? 'Instagram Account Connected' : 'Instagram Ready'}
                            </span>
                        )}
                        {currentChannel === 'all' && (
                            <span className="flex items-center gap-1.5 text-[#8696a0] font-medium bg-[#202c33] px-2.5 py-1 rounded-lg border border-[#222d34]">
                                🌐 Unified Omnichannel View
                            </span>
                        )}
                    </div>
                </div>

                {/* INBOX BODY (SIDEBAR + ACTIVE THREAD CANVAS) */}
                <div className="flex flex-1 overflow-hidden">
                    {/* LEFT COLUMN: Thread List Sidebar */}
                    <aside className={`flex flex-col w-full md:w-[340px] lg:w-[380px] border-r border-[#222d34] bg-[#111b21] h-full flex-shrink-0 ${activeConversation ? 'hidden md:flex' : 'flex'}`}>
                        <Sidebar 
                            conversations={conversations} 
                            activeConversation={activeConversation} 
                            onSelect={handleSelectConversation} 
                            user={auth?.user}
                            tenantNumbers={tenantNumbers}
                            selectedNumberId={selectedNumberId}
                            onSelectNumber={setSelectedNumberId}
                            onConversationsDeleted={handleConversationsDeleted}
                            currentChannel={currentChannel}
                        />
                    </aside>
                    
                    {/* RIGHT COLUMN: Active Chat Canvas */}
                    <main className={`flex flex-1 flex-col bg-[#0b141a] relative h-full min-w-0 ${!activeConversation ? 'hidden md:flex' : 'flex'}`}>
                        {activeConversation ? (
                            <Thread conversation={activeConversation} approvedTemplates={approvedTemplates} onBack={() => setActiveConversation(null)} />
                        ) : (
                            <div className="flex h-full items-center justify-center flex-col space-y-4 bg-[#222e35] text-center p-8">
                                {currentChannel === 'facebook' ? (
                                    <>
                                        <div className="w-24 h-24 rounded-full bg-[#1877f2]/10 border border-[#1877f2]/30 flex items-center justify-center shadow-lg shadow-blue-500/10">
                                            <svg className="w-12 h-12 text-[#1877f2]" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 2C6.48 2 2 6.03 2 11c0 2.87 1.48 5.43 3.78 7.04V22l3.82-2.1c.78.22 1.61.34 2.4.34 5.52 0 10-4.03 10-9s-4.48-9-10-9zm1.06 12.13l-2.62-2.8-5.12 2.8 5.63-5.98 2.68 2.8 5.06-2.8-5.63 5.98z"/>
                                            </svg>
                                        </div>
                                        <h2 className="text-2xl font-light text-[#e9edef] mt-2">Facebook Messenger Inbox</h2>
                                        <p className="text-sm text-[#8696a0] max-w-md leading-relaxed">
                                            Manage customer direct messages from your Facebook Business Page with 24-hour reply window enforcement.
                                        </p>
                                    </>
                                ) : currentChannel === 'instagram' ? (
                                    <>
                                        <div className="w-24 h-24 rounded-full bg-pink-500/10 border border-pink-500/30 flex items-center justify-center shadow-lg shadow-pink-500/10">
                                            <svg className="w-12 h-12 text-pink-400" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                            </svg>
                                        </div>
                                        <h2 className="text-2xl font-light text-[#e9edef] mt-2">Instagram Direct Inbox</h2>
                                        <p className="text-sm text-[#8696a0] max-w-md leading-relaxed">
                                            Reply to Instagram customer inquiries and direct messages seamlessly from your dashboard.
                                        </p>
                                    </>
                                ) : currentChannel === 'all' ? (
                                    <>
                                        <div className="w-24 h-24 rounded-full bg-[#202c33] flex items-center justify-center shadow-lg border border-[#222d34]">
                                            <span className="text-3xl">💬</span>
                                        </div>
                                        <h2 className="text-2xl font-light text-[#e9edef] mt-2">Unified Omnichannel Inbox</h2>
                                        <p className="text-sm text-[#8696a0] max-w-md leading-relaxed">
                                            All your customer conversations across WhatsApp, Facebook Messenger, and Instagram Direct in one place.
                                        </p>
                                    </>
                                ) : (
                                    <>
                                        <div className="w-24 h-24 rounded-full bg-[#202c33] flex items-center justify-center shadow-lg border border-[#222d34]">
                                            <svg className="w-12 h-12 text-[#00a884]" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0012.04 2zm.01 16.59c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.32a8.192 8.192 0 01-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.194 8.194 0 012.41 5.83c0 4.54-3.7 8.24-8.24 8.24z"/>
                                            </svg>
                                        </div>
                                        <h2 className="text-2xl font-light text-[#e9edef] mt-2">WhatsApp Web for Business</h2>
                                        <p className="text-sm text-[#8696a0] max-w-md leading-relaxed">
                                            Send and receive WhatsApp messages in real time with template support and interactive quick-replies.
                                        </p>
                                    </>
                                )}
                                <div className="flex items-center gap-2 text-xs text-[#8696a0] mt-6">
                                    <svg className="w-4 h-4 text-[#8696a0]" fill="currentColor" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                                    <span>Real-time WebSocket connection active</span>
                                </div>
                            </div>
                        )}
                    </main>
                </div>
            </div>
        </AppLayout>
    );
}
