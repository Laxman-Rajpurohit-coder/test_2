import { useState } from 'react';

/**
 * Render a high-performance, accessible conversation sidebar with cursor-paginated loading,
 * server-side search, favorite pinning, and bulk management.
 */
export default function Sidebar({ 
    conversations = [], 
    activeConversation, 
    onSelect, 
    user, 
    tenantNumbers, 
    selectedNumberId, 
    onSelectNumber, 
    onConversationsDeleted, 
    currentChannel = 'whatsapp',
    searchQuery = '',
    onSearchChange,
    showUnreadOnly = false,
    onToggleUnreadOnly,
    showFavoritesOnly = false,
    onToggleFavoritesOnly,
    onToggleFavorite,
    onLoadMore,
    hasMore = false,
    isLoadingMore = false
}) {
    const [isSelectMode, setIsSelectMode] = useState(false);
    const [selectedConversations, setSelectedConversations] = useState([]);

    const normalizePhone = (phone) => {
        if (!phone) return '';
        let digits = String(phone).replace(/\D/g, '');
        if (digits.length === 10) {
            digits = '91' + digits;
        }
        return digits;
    };

    const formatSidebarPreview = (preview) => {
        if (!preview) return 'Active thread';
        if (typeof preview === 'string' && (preview.trim().startsWith('{') || preview.trim().startsWith('['))) {
            try {
                const parsed = JSON.parse(preview.trim());
                if (parsed && typeof parsed === 'object') {
                    return parsed.text || parsed.body || parsed.caption || 'Active thread';
                }
            } catch (e) {}
        }
        return preview;
    };

    const getDisplayName = (conv) => {
        const isWhatsApp = !conv?.channel || conv?.channel === 'whatsapp' || currentChannel === 'whatsapp';
        if (isWhatsApp) {
            if (conv?.customer_number) {
                const norm = normalizePhone(conv.customer_number);
                return `+${norm}`;
            }
            return 'Unknown Contact';
        }
        if (conv?.customer_name && conv.customer_name !== 'New Contact' && conv.customer_name !== 'Unknown') {
            return conv.customer_name;
        }
        if (conv?.customer_number) {
            return conv.customer_number.startsWith('+') ? conv.customer_number : `+${conv.customer_number}`;
        }
        return 'Unknown Contact';
    };

    const getAvatarInitials = (conv) => {
        const name = getDisplayName(conv);
        const digits = name.replace(/\D/g, '');
        if (digits.length >= 2) {
            return digits.substring(digits.length - 2);
        }
        return name.substring(0, 2).toUpperCase();
    };

    const uniqueConversations = (conversations || []).filter((conv, index, self) => {
        const rawNumber = normalizePhone(conv.customer_number);
        const firstIdx = self.findIndex(c => 
            (conv.id && c.id === conv.id) ||
            (rawNumber && normalizePhone(c.customer_number) === rawNumber)
        );
        return firstIdx === index;
    });

    const filteredConversations = uniqueConversations.filter(conv => {
        if (showFavoritesOnly && !conv.is_favorite) return false;
        if (showUnreadOnly && (!conv.unread_count || conv.unread_count <= 0)) return false;
        return true;
    });

    const sortedConversations = [...filteredConversations].sort((a, b) => {
        if (!!a.is_favorite !== !!b.is_favorite) {
            return a.is_favorite ? -1 : 1;
        }
        return new Date(b.last_message_at || 0) - new Date(a.last_message_at || 0);
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

    const handleScroll = (e) => {
        const { scrollTop, scrollHeight, clientHeight } = e.currentTarget;
        if (scrollHeight - scrollTop - clientHeight < 150 && hasMore && !isLoadingMore && onLoadMore) {
            onLoadMore();
        }
    };

    return (
        <div className="flex h-full flex-col bg-[#111b21] overflow-hidden select-none">
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
                        <div className="w-8 h-8 rounded-lg bg-[#00a884] flex items-center justify-center text-[#111b21] font-black text-xs shadow">
                            WA
                        </div>
                    )}
                    <div>
                        <h3 className="text-xs font-bold text-[#e9edef] capitalize">
                            {currentChannel === 'facebook' ? 'Messenger' : currentChannel === 'instagram' ? 'Instagram' : currentChannel === 'all' ? 'All Chats' : 'WhatsApp'}
                        </h3>
                        <span className="text-[11px] text-[#9ca3af]">
                            {conversations.length} loaded{hasMore ? '+' : ''}
                        </span>
                    </div>
                </div>

                <div className="flex items-center gap-2 text-[#8696a0] shrink-0">
                    {/* Select Mode Toggle */}
                    <button 
                        type="button"
                        onClick={() => {
                            setIsSelectMode(!isSelectMode);
                            setSelectedConversations([]);
                        }}
                        className={`p-1.5 rounded-lg transition-colors ${
                            isSelectMode ? 'bg-[#00a884] text-[#111b21]' : 'hover:text-[#e9edef] hover:bg-[#2a3942]'
                        }`}
                        title="Select Conversations"
                        aria-label="Select conversations for bulk actions"
                        aria-pressed={isSelectMode}
                    >
                        <svg className="w-4 h-4 fill-current" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </button>
                    {isSelectMode && selectedConversations.length > 0 && (
                        <button 
                            type="button"
                            onClick={handleBulkDelete}
                            className="p-1.5 rounded-lg bg-rose-950 hover:bg-rose-900 border border-rose-800 text-rose-300 transition-colors flex items-center gap-1 text-[11px] font-bold"
                            title="Delete Selected Conversations"
                            aria-label={`Delete ${selectedConversations.length} selected conversations`}
                        >
                            🗑️ ({selectedConversations.length})
                        </button>
                    )}
                </div>
            </header>

            {/* Tenant Number Filter Dropdown (WhatsApp Only) */}
            {currentChannel === 'whatsapp' && tenantNumbers && tenantNumbers.length > 1 && (
                <div className="px-3 py-1.5 bg-[#111b21] border-b border-[#222d34]/60 flex-shrink-0">
                    <label htmlFor="tenant-number-select" className="sr-only">Filter by WhatsApp Phone Number</label>
                    <select
                        id="tenant-number-select"
                        value={selectedNumberId || ''}
                        onChange={(e) => onSelectNumber && onSelectNumber(e.target.value ? Number(e.target.value) : null)}
                        aria-label="Filter by WhatsApp Phone Number"
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

            {/* Search & Quick Filters Bar */}
            <div className="px-3 py-2 bg-[#111b21] border-b border-[#222d34] flex-shrink-0 flex items-center gap-1.5">
                {/* Search input container */}
                <div className="flex-1 flex items-center gap-2 rounded-lg bg-[#202c33] px-3 py-1.5 text-sm border border-[#222d34]/60 focus-within:border-[#00a884]/60">
                    <span className="text-[#8696a0] shrink-0 text-xs" aria-hidden="true">🔍</span>
                    <label htmlFor="chat-search-input" className="sr-only">Search conversations</label>
                    <input 
                        type="text" 
                        id="chat-search-input"
                        name="chat_search"
                        autoComplete="off"
                        value={searchQuery}
                        onChange={(e) => onSearchChange && onSearchChange(e.target.value)}
                        placeholder={`Search ${currentChannel === 'all' ? '' : currentChannel}...`} 
                        aria-label="Search conversations by customer name or phone"
                        className="w-full bg-transparent outline-none text-[#e9edef] placeholder-[#8696a0] text-xs" 
                    />
                    {searchQuery && (
                        <button 
                            type="button"
                            onClick={() => onSearchChange && onSearchChange('')} 
                            className="text-[#8696a0] hover:text-[#e9edef] text-xs p-0.5"
                            title="Clear search"
                            aria-label="Clear search input"
                        >
                            ✕
                        </button>
                    )}
                </div>

                {/* Favorites Filter Toggle Button */}
                <button
                    type="button"
                    onClick={onToggleFavoritesOnly}
                    className={`p-2 rounded-lg text-xs font-semibold shrink-0 transition-colors border ${
                        showFavoritesOnly 
                            ? 'bg-amber-500/20 text-amber-400 border-amber-500/40 shadow-sm' 
                            : 'bg-[#202c33] text-[#8696a0] border-[#222d34]/60 hover:text-amber-400 hover:bg-[#2a3942]'
                    }`}
                    title={showFavoritesOnly ? "Show all chats" : "Filter by favorites"}
                    aria-label="Filter favorite conversations"
                    aria-pressed={showFavoritesOnly}
                >
                    <svg className="w-4 h-4 fill-current" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                </button>

                {/* Unread Filter Toggle Button */}
                <button
                    type="button"
                    onClick={onToggleUnreadOnly}
                    className={`p-2 rounded-lg text-xs font-semibold shrink-0 transition-colors border ${
                        showUnreadOnly 
                            ? 'bg-[#00a884] text-[#111b21] border-[#00a884] shadow-sm shadow-emerald-500/20' 
                            : 'bg-[#202c33] text-[#8696a0] border-[#222d34]/60 hover:text-[#e9edef] hover:bg-[#2a3942]'
                    }`}
                    title={showUnreadOnly ? "Show all chats" : "Filter by unread"}
                    aria-label="Filter unread conversations"
                    aria-pressed={showUnreadOnly}
                >
                    <svg className="w-4 h-4 fill-current" viewBox="0 0 24 24">
                        <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z"/>
                    </svg>
                </button>
            </div>

            {/* Conversation Threads List Container */}
            <div 
                onScroll={handleScroll}
                className="flex-1 overflow-y-auto custom-scrollbar border-r border-[#222d34]"
            >
                {sortedConversations.length === 0 ? (
                    <div className="text-center text-xs text-[#8696a0] py-16 px-4 font-medium">
                        {showFavoritesOnly 
                            ? 'No favorite conversations found' 
                            : showUnreadOnly 
                            ? 'No unread conversations' 
                            : searchQuery 
                            ? `No conversations matching "${searchQuery}"`
                            : `No ${currentChannel === 'all' ? '' : currentChannel} conversations yet`}
                    </div>
                ) : (
                    sortedConversations.map(conv => {
                        const isActive = activeConversation?.id === conv.id;
                        const isSelected = selectedConversations.includes(conv.id);
                        return (
                            <div
                                key={conv.id}
                                onClick={() => {
                                    if (isSelectMode) {
                                        handleToggleSelect(conv.id);
                                    } else {
                                        onSelect(conv);
                                    }
                                }}
                                className={`group relative flex items-center gap-3 px-3 py-3 cursor-pointer transition-colors border-b border-[#222d34]/60 ${
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
                                            aria-label={`Select conversation with ${conv.customer_name || conv.customer_number}`}
                                            className="rounded border-[#3b4a54] bg-[#2a3942] text-[#00a884] focus:ring-[#00a884]"
                                        />
                                    </div>
                                )}

                                {/* Customer Avatar with Channel Badge */}
                                <div className="relative flex-shrink-0">
                                    <div className="h-12 w-12 rounded-full bg-[#374248] flex items-center justify-center text-[#e9edef] font-medium text-base">
                                        {getAvatarInitials(conv)}
                                    </div>
                                    <span className={`absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full text-[9px] font-black text-white shadow ring-1 ring-[#111b21] ${
                                        conv.channel === 'facebook'
                                            ? 'bg-[#1877f2]'
                                            : conv.channel === 'instagram'
                                            ? 'bg-gradient-to-tr from-[#f09433] via-[#dc2743] to-[#bc1888]'
                                            : 'bg-[#15803d]'
                                    }`} title={conv.channel || 'whatsapp'}>
                                        {conv.channel === 'facebook' ? 'f' : conv.channel === 'instagram' ? 'ig' : 'wa'}
                                    </span>
                                </div>

                                {/* Thread Info */}
                                <div className="flex-1 overflow-hidden min-w-0">
                                    <div className="flex justify-between items-baseline gap-2">
                                        <div className="flex items-center gap-1.5 min-w-0">
                                            <h4 className="font-medium text-sm text-[#e9edef] truncate">
                                                {getDisplayName(conv)}
                                            </h4>
                                            {conv.is_favorite && (
                                                <span className="text-amber-400 text-xs shrink-0" title="Favorite">★</span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1.5 shrink-0">
                                            {/* Star toggle button */}
                                            {onToggleFavorite && (
                                                <button
                                                    type="button"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        onToggleFavorite(conv.id);
                                                    }}
                                                    className={`p-0.5 rounded transition-all ${
                                                        conv.is_favorite 
                                                            ? 'text-amber-400 opacity-100' 
                                                            : 'text-[#8696a0] opacity-0 group-hover:opacity-100 hover:text-amber-400'
                                                    }`}
                                                    title={conv.is_favorite ? "Remove from favorites" : "Mark as favorite"}
                                                    aria-label={conv.is_favorite ? "Remove from favorites" : "Mark as favorite"}
                                                    aria-pressed={!!conv.is_favorite}
                                                >
                                                    <svg className="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                                        {conv.is_favorite ? (
                                                            <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                                        ) : (
                                                            <path d="M22 9.24l-7.19-.62L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27 18.18 21l-1.63-7.03L22 9.24zM12 15.4l-3.76 2.27 1-4.28-3.32-2.88 4.38-.38L12 6.1l1.71 4.04 4.38.38-3.32 2.88 1 4.28L12 15.4z"/>
                                                        )}
                                                    </svg>
                                                </button>
                                            )}
                                            <span className="text-[11px] text-[#9ca3af]">
                                                {formatTimestamp(conv.last_message_at)}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex justify-between items-center mt-1 gap-2">
                                        <p className="text-xs text-[#9ca3af] truncate max-w-[200px]">
                                            {conv.last_message_direction === 'outbound' ? 'You: ' : ''}
                                            {formatSidebarPreview(conv.preview)}
                                        </p>
                                        {conv.unread_count > 0 && !isActive && (
                                            <span className="flex min-w-[20px] h-5 px-1 items-center justify-center rounded-full bg-[#00a884] text-[11px] font-bold text-[#111b21] shrink-0">
                                                {conv.unread_count}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })
                )}

                {/* Loading indicator for infinite scroll */}
                {isLoadingMore && (
                    <div className="py-3 text-center text-xs text-[#8696a0] flex items-center justify-center gap-2">
                        <svg className="w-4 h-4 animate-spin text-[#00a884]" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>Loading more conversations...</span>
                    </div>
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
