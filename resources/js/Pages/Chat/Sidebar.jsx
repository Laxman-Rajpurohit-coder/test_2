import { useState } from 'react';

/**
 * Render a searchable conversation sidebar with optional tenant-number selection.
 * @param {Object} props - Sidebar properties.
 * @param {Array} props.conversations - Conversation threads to display.
 * @param {Object} props.activeConversation - Currently selected conversation.
 * @param {Function} props.onSelect - Called when a conversation is selected.
 * @param {Object} props.user - User whose initials appear in the header.
 * @param {Array} props.tenantNumbers - Tenant numbers available for selection.
 * @param {number|string|null} props.selectedNumberId - Currently selected tenant number identifier.
 * @param {Function} props.onSelectNumber - Called with the selected tenant number identifier or `null`.
 * @returns {JSX.Element} The rendered conversation sidebar.
 */
export default function Sidebar({ conversations, activeConversation, onSelect, user, tenantNumbers, selectedNumberId, onSelectNumber }) {
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
        <div className="flex h-full flex-col bg-[#111b21] overflow-hidden">
            {/* Sidebar Top Header Bar */}
            <header className="flex h-[60px] items-center justify-between bg-[#202c33] px-4 py-2 border-r border-[#222d34] flex-shrink-0">
                <div className="flex items-center gap-3 shrink-0">
                    <div className="h-10 w-10 rounded-full bg-[#6b7c85] flex items-center justify-center text-white font-bold text-sm shadow">
                        {user?.name ? user.name.substring(0, 2).toUpperCase() : 'ME'}
                    </div>
                </div>
                <div className="flex items-center gap-4 text-[#8696a0] shrink-0">
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
            <div className="px-4 py-2 bg-[#111b21] border-r border-[#222d34] flex-shrink-0 pb-3">
                <div className="flex items-center gap-3 rounded-lg bg-[#202c33] px-3 py-2 text-sm w-full border border-[#222d34]/60">
                    <span className="text-[#8696a0] shrink-0">🔍</span>
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
                                            {(() => {
                                                if (conv.messages && conv.messages.length > 0) {
                                                    const lastMsg = conv.messages[0];
                                                    let textStr = '📷 Media';
                                                    try {
                                                        const parsed = typeof lastMsg.content === 'string' ? JSON.parse(lastMsg.content) : lastMsg.content;
                                                        if (parsed && typeof parsed === 'string') {
                                                            const doubleParsed = JSON.parse(parsed);
                                                            textStr = doubleParsed.text || textStr;
                                                        } else if (parsed && parsed.text) {
                                                            textStr = parsed.text;
                                                        }
                                                    } catch(e) {}
                                                    
                                                    const prefix = lastMsg.direction === 'outbound' ? 'You: ' : '';
                                                    return prefix + textStr;
                                                }
                                                return 'Active thread';
                                            })()}
                                        </p>
                                        {conv.unread_count > 0 && !isActive && (
                                            <span className="flex min-w-[20px] h-5 px-1 items-center justify-center rounded-full bg-[#00a884] text-[11px] font-bold text-[#111b21]">
                                                {conv.unread_count}
                                            </span>
                                        )}
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
