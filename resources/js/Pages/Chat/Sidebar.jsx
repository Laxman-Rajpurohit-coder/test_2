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
export default function Sidebar({ conversations, activeConversation, onSelect, user, tenantNumbers, selectedNumberId, onSelectNumber, onConversationsDeleted, currentChannel = 'whatsapp' }) {
    const [searchQuery, setSearchQuery] = useState('');
    const [showUnreadOnly, setShowUnreadOnly] = useState(false);
    const [isSelectMode, setIsSelectMode] = useState(false);
    const [selectedConversations, setSelectedConversations] = useState([]);

    const filteredConversations = conversations.filter(conv => {
        const matchesSearch = (conv.customer_name || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
            (conv.customer_number || '').includes(searchQuery);
        const matchesUnread = !showUnreadOnly || conv.unread_count > 0;
        return matchesSearch && matchesUnread;
    });

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

    const handleToggleSelect = (convId) => {
        setSelectedConversations(prev => 
            prev.includes(convId) ? prev.filter(id => id !== convId) : [...prev, convId]
        );
    };

    const handleBulkDelete = () => {
        if (selectedConversations.length === 0) return;
        if (!confirm(`Are you sure you want to delete ${selectedConversations.length} selected conversation(s)? This will permanently remove all messages.`)) return;

        window.axios.post('/api/conversations/bulk-delete', {
            conversation_ids: selectedConversations
        }).then(() => {
            setSelectedConversations([]);
            setIsSelectMode(false);
            if (onConversationsDeleted) {
                onConversationsDeleted(selectedConversations);
            }
        }).catch(err => {
            alert('Failed to delete conversations: ' + (err.response?.data?.message || err.message));
        });
    };

    return (
        <div className="flex h-full flex-col bg-[#111b21] overflow-hidden">
            {/* Sidebar Top Header Bar */}
            <header className="flex h-[56px] items-center justify-between bg-[#202c33] px-4 py-2 border-r border-[#222d34] flex-shrink-0">
                <div className="flex items-center gap-2.5 shrink-0">
                    {currentChannel === 'facebook' ? (
                        <div className="w-8 h-8 rounded-lg bg-[#1877f2] flex items-center justify-center text-white font-bold text-sm shadow">
                            f
                        </div>
                    ) : currentChannel === 'instagram' ? (
                        <div className="w-8 h-8 rounded-lg bg-gradient-to-tr from-[#f09433] via-[#dc2743] to-[#bc1888] flex items-center justify-center text-white font-bold text-xs shadow">
                            ig
                        </div>
                    ) : currentChannel === 'all' ? (
                        <div className="w-8 h-8 rounded-lg bg-[#2a3942] flex items-center justify-center text-white text-sm shadow">
                            💬
                        </div>
                    ) : (
                        <div className="w-8 h-8 rounded-lg bg-[#00a884] flex items-center justify-center text-white font-bold text-xs shadow">
                            WA
                        </div>
                    )}
                    <div>
                        <h3 className="text-xs font-bold text-[#e9edef] capitalize">
                            {currentChannel === 'facebook' ? 'Messenger' : currentChannel === 'instagram' ? 'Instagram' : currentChannel === 'all' ? 'All Chats' : 'WhatsApp'}
                        </h3>
                        <span className="text-[11px] text-[#8696a0]">
                            {filteredConversations.length} conversation{filteredConversations.length === 1 ? '' : 's'}
                        </span>
                    </div>
                </div>

                <div className="flex items-center gap-3 text-[#8696a0] shrink-0">
                    {/* Select Mode Toggle */}
                    <button 
                        onClick={() => {
                            setIsSelectMode(!isSelectMode);
                            setSelectedConversations([]);
                        }}
                        className={`p-1.5 rounded transition-colors ${isSelectMode ? 'bg-[#00a884] text-[#111b21]' : 'hover:text-[#e9edef]'}`}
                        title="Select Conversations"
                    >
                        <svg className="w-4 h-4 fill-current" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </button>
                    {isSelectMode && selectedConversations.length > 0 && (
                        <button 
                            onClick={handleBulkDelete}
                            className="p-1 rounded bg-rose-950 hover:bg-rose-900 border border-rose-800 text-rose-400 transition-colors flex items-center gap-1 text-[11px] font-bold"
                            title="Delete Selected Conversations"
                        >
                            🗑️ ({selectedConversations.length})
                        </button>
                    )}
                </div>
            </header>

            {/* Tenant Number Filter Dropdown (WhatsApp Only) */}
            {currentChannel === 'whatsapp' && tenantNumbers && tenantNumbers.length > 1 && (
                <div className="px-3 py-1.5 bg-[#111b21] border-b border-[#222d34]/60 flex-shrink-0">
                    <select
                        value={selectedNumberId || ''}
                        onChange={(e) => onSelectNumber && onSelectNumber(e.target.value ? Number(e.target.value) : null)}
                        className="w-full bg-[#202c33] border border-[#222d34] rounded-lg px-2.5 py-1 text-xs text-[#e9edef] outline-none focus:border-[#00a884]"
                    >
                        <option value="">All WhatsApp Numbers</option>
                        {tenantNumbers.map(num => (
                            <option key={num.id} value={num.id}>
                                +{num.integrated_number}
                            </option>
                        ))}
                    </select>
                </div>
            )}

            {/* Search Bar Container */}
            <div className="px-3 py-2 bg-[#111b21] border-b border-[#222d34] flex-shrink-0 flex items-center gap-2">
                <div className="flex-1 flex items-center gap-2 rounded-lg bg-[#202c33] px-3 py-1.5 text-sm border border-[#222d34]/60">
                    <span className="text-[#8696a0] shrink-0 text-xs">🔍</span>
                    <input 
                        type="text" 
                        id="chat-search"
                        name="chat_search"
                        autoComplete="off"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder={`Search ${currentChannel === 'all' ? '' : currentChannel} conversations...`} 
                        className="w-full bg-transparent outline-none text-[#e9edef] placeholder-[#8696a0] text-xs" 
                    />
                    {searchQuery && (
                        <button onClick={() => setSearchQuery('')} className="text-[#8696a0] hover:text-[#e9edef] text-xs">
                            ✕
                        </button>
                    )}
                </div>

                {/* Unread Filter Toggle Button */}
                <button
                    type="button"
                    onClick={() => setShowUnreadOnly(!showUnreadOnly)}
                    className={`p-2 rounded-lg text-xs font-semibold shrink-0 transition-colors border ${
                        showUnreadOnly 
                            ? 'bg-[#00a884] text-[#111b21] border-[#00a884] shadow-md shadow-emerald-500/10' 
                            : 'bg-[#202c33] text-[#8696a0] border-[#222d34]/60 hover:text-[#e9edef]'
                    }`}
                    title={showUnreadOnly ? "Show all chats" : "Filter by unread"}
                >
                    <svg className="w-4 h-4 fill-current" viewBox="0 0 24 24">
                        <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z"/>
                    </svg>
                </button>
            </div>

            {/* Conversation Threads List */}
            <div className="flex-1 overflow-y-auto custom-scrollbar border-r border-[#222d34]">
                {filteredConversations.length === 0 ? (
                    <div className="text-center text-xs text-[#8696a0] py-12 font-medium">
                        No {currentChannel === 'all' ? '' : currentChannel} conversations found
                    </div>
                ) : (
                    filteredConversations.map(conv => {
                        const isActive = activeConversation?.id === conv.id;
                        const isSelected = selectedConversations.includes(conv.id);
                        return (
                            <div
                                key={conv.id}
                                onClick={(e) => {
                                    if (isSelectMode) {
                                        handleToggleSelect(conv.id);
                                    } else {
                                        onSelect(conv);
                                    }
                                }}
                                className={`flex items-center gap-3 px-3 py-3 cursor-pointer transition-colors border-b border-[#222d34]/60 ${
                                    isActive && !isSelectMode
                                        ? 'bg-[#2a3942]' 
                                        : isSelected
                                        ? 'bg-[#1e4620]/30 hover:bg-[#1e4620]/40'
                                        : 'hover:bg-[#202c33]'
                                }`}
                            >
                                {/* Checkbox Select Icon */}
                                {isSelectMode && (
                                    <div className="shrink-0 flex items-center pr-1" onClick={(e) => e.stopPropagation()}>
                                        <input
                                            type="checkbox"
                                            checked={isSelected}
                                            onChange={() => handleToggleSelect(conv.id)}
                                            className="rounded border-[#3b4a54] bg-[#2a3942] text-[#00a884] focus:ring-[#00a884]"
                                        />
                                    </div>
                                )}

                                {/* Customer Avatar with Channel Badge */}
                                <div className="relative flex-shrink-0">
                                    <div className="h-12 w-12 rounded-full bg-[#374248] flex items-center justify-center text-[#e9edef] font-medium text-base">
                                        {conv.customer_name ? conv.customer_name.substring(0, 2).toUpperCase() : (conv.customer_number ? conv.customer_number.substring(0, 2) : '??')}
                                    </div>
                                    <span className={`absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full text-[9px] font-bold text-white shadow ring-1 ring-[#111b21] ${
                                        conv.channel === 'facebook'
                                            ? 'bg-[#1877f2]'
                                            : conv.channel === 'instagram'
                                            ? 'bg-gradient-to-tr from-[#f09433] via-[#dc2743] to-[#bc1888]'
                                            : 'bg-[#25d366]'
                                    }`} title={conv.channel || 'whatsapp'}>
                                        {conv.channel === 'facebook' ? 'f' : conv.channel === 'instagram' ? 'ig' : 'wa'}
                                    </span>
                                </div>

                                {/* Thread Info */}
                                <div className="flex-1 overflow-hidden">
                                    <div className="flex justify-between items-baseline">
                                        <div className="flex items-center gap-1.5 min-w-0">
                                            <h4 className="font-medium text-sm text-[#e9edef] truncate">
                                                {conv.customer_name || (conv.customer_number && !conv.customer_number.startsWith('fb_') ? `+${conv.customer_number}` : conv.customer_number)}
                                            </h4>
                                        </div>
                                        <span className="text-[11px] text-[#8696a0] shrink-0 ml-2">
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

            <style>{`
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
