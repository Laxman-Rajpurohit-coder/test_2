import { useState } from 'react';

export default function Sidebar({ conversations, activeConversation, onSelect, user }) {
    const [searchQuery, setSearchQuery] = useState('');

    const filteredConversations = conversations.filter(conv => 
        conv.customer_number.includes(searchQuery)
    );

    const formatTimestamp = (dateStr) => {
        if (!dateStr) return '';
        try {
            const strVal = String(dateStr);
            const isoStr = strVal.endsWith('Z') ? strVal : strVal + 'Z';
            const date = new Date(isoStr);
            return isNaN(date.getTime()) 
                ? '' 
                : date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return '';
        }
    };

    return (
        <div className="flex h-full flex-col bg-[#111b21]">
            {/* Sidebar Top Header Bar */}
            <header className="flex h-[60px] items-center justify-between bg-[#202c33] px-4 py-2 border-r border-[#222d34] flex-shrink-0">
                <div className="flex items-center gap-3">
                    <div className="h-10 w-10 rounded-full bg-[#6b7c85] flex items-center justify-center text-white font-bold text-sm shadow">
                        {user?.name ? user.name.substring(0, 2).toUpperCase() : 'ME'}
                    </div>
                </div>
                <div className="flex items-center gap-4 text-[#8696a0]">
                    <button title="Communities" className="hover:text-[#e9edef] transition-colors">
                        <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                            <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"/>
                        </svg>
                    </button>
                    <button title="Status" className="hover:text-[#e9edef] transition-colors">
                        <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                            <path d="M12 4a8 8 0 1 0 8 8 8 8 0 0 0-8-8zm0 14a6 6 0 1 1 6-6 6 6 0 0 1-6 6z"/>
                        </svg>
                    </button>
                    <button title="Channels" className="hover:text-[#e9edef] transition-colors">
                        <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/>
                        </svg>
                    </button>
                    <button title="New Chat" className="hover:text-[#e9edef] transition-colors">
                        <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                            <path d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6z"/>
                        </svg>
                    </button>
                    <button title="Menu" className="hover:text-[#e9edef] transition-colors">
                        <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                            <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                        </svg>
                    </button>
                </div>
            </header>

            {/* Search Bar Container */}
            <div className="p-2 bg-[#111b21] border-r border-[#222d34] flex-shrink-0">
                <div className="flex items-center gap-3 rounded-lg bg-[#202c33] px-3 py-1.5 text-sm">
                    <span className="text-[#8696a0]">🔍</span>
                    <input 
                        type="text" 
                        id="chat-search"
                        name="chat_search"
                        autoComplete="off"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Search or start new chat" 
                        className="w-full bg-transparent outline-none text-[#e9edef] placeholder-[#8696a0] text-sm" 
                    />
                </div>
            </div>

            {/* Conversation Threads List */}
            <div className="flex-1 overflow-y-auto custom-scrollbar border-r border-[#222d34]">
                {filteredConversations.length === 0 ? (
                    <div className="text-center text-xs text-[#8696a0] py-12 font-medium">
                        No conversations found
                    </div>
                ) : (
                    filteredConversations.map(conv => {
                        const isActive = activeConversation?.id === conv.id;
                        return (
                            <div
                                key={conv.id}
                                onClick={() => onSelect(conv)}
                                className={`flex items-center gap-3 px-3 py-3 cursor-pointer transition-colors border-b border-[#222d34]/60 ${
                                    isActive 
                                        ? 'bg-[#2a3942]' 
                                        : 'hover:bg-[#202c33]'
                                }`}
                            >
                                {/* Customer Avatar */}
                                <div className="h-12 w-12 rounded-full bg-[#374248] flex items-center justify-center text-[#e9edef] font-medium text-base flex-shrink-0">
                                    {conv.customer_number.substring(0, 2)}
                                </div>

                                {/* Thread Info */}
                                <div className="flex-1 overflow-hidden">
                                    <div className="flex justify-between items-baseline">
                                        <h4 className="font-medium text-sm text-[#e9edef] truncate">
                                            +{conv.customer_number}
                                        </h4>
                                        <span className="text-[11px] text-[#8696a0]">
                                            {formatTimestamp(conv.last_message_at)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between items-center mt-1">
                                        <p className="text-xs text-[#8696a0] truncate max-w-[200px]">
                                            Active thread
                                        </p>
                                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-[#00a884] text-[11px] font-bold text-[#111b21]">
                                            1
                                        </span>
                                    </div>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>

            <style jsx>{`
                .custom-scrollbar::-webkit-scrollbar {
                    width: 6px;
                }
                .custom-scrollbar::-webkit-scrollbar-track {
                    background-color: transparent;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb {
                    background-color: #374248;
                    border-radius: 4px;
                }
            `}</style>
        </div>
    );
}
