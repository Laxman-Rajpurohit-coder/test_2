import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Sidebar from './Sidebar';
import Thread from './Thread';

export default function ChatIndex({ auth }) {
    const [conversations, setConversations] = useState([]);
    const [activeConversation, setActiveConversation] = useState(null);

    const fetchConversations = () => {
        window.axios.get('/api/conversations').then(res => {
            setConversations(res.data);
        });
    };

    useEffect(() => {
        fetchConversations();
        const interval = setInterval(fetchConversations, 10000); // Polling fallback
        return () => clearInterval(interval);
    }, []);

    return (
        <AppLayout>
            <Head title="WhatsApp Web - Live Inbox" />

            <div className="flex h-[calc(100vh-112px)] w-full overflow-hidden bg-[#0c1317] font-sans antialiased text-[#e9edef] rounded-2xl border border-gray-200/80 shadow-sm">
                {/* LEFT COLUMN: Thread List Sidebar */}
                <aside className="flex flex-col w-full max-w-[340px] lg:max-w-[380px] border-r border-[#222d34] bg-[#111b21] h-full flex-shrink-0">
                    <Sidebar 
                        conversations={conversations} 
                        activeConversation={activeConversation} 
                        onSelect={setActiveConversation} 
                        user={auth?.user}
                    />
                </aside>
                
                {/* RIGHT COLUMN: Active Chat Canvas */}
                <main className="flex flex-1 flex-col bg-[#0b141a] relative h-full">
                    {activeConversation ? (
                        <Thread conversation={activeConversation} />
                    ) : (
                        <div className="flex h-full items-center justify-center flex-col space-y-4 bg-[#222e35] text-center p-8">
                            <div className="w-24 h-24 rounded-full bg-[#202c33] flex items-center justify-center shadow-lg border border-[#222d34]">
                                <svg className="w-12 h-12 text-[#00a884]" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0012.04 2zm.01 16.59c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.32a8.192 8.192 0 01-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.194 8.194 0 012.41 5.83c0 4.54-3.7 8.24-8.24 8.24z"/>
                                </svg>
                            </div>
                            <h2 className="text-2xl font-light text-[#e9edef] mt-2">WhatsApp Web for Business</h2>
                            <p className="text-sm text-[#8696a0] max-w-md leading-relaxed">
                                Send and receive messages in real time with high-concurrency PostgreSQL performance and Reverb WebSockets.
                            </p>
                            <div className="flex items-center gap-2 text-xs text-[#8696a0] mt-6">
                                <svg className="w-4 h-4 text-[#8696a0]" fill="currentColor" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                                <span>End-to-end encrypted integration</span>
                            </div>
                        </div>
                    )}
                </main>
            </div>
        </AppLayout>
    );
}
