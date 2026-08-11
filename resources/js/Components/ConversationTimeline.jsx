import React from 'react';

/**
 * Reusable ConversationTimeline Component
 * Renders a WhatsApp-style message thread timeline with status badges, timestamps, and media links.
 */
export default function ConversationTimeline({ messages = [], customerName = 'Customer' }) {
    if (!messages || messages.length === 0) {
        return (
            <div className="bg-gray-50/80 rounded-2xl p-8 text-center border border-gray-100 my-4">
                <div className="w-12 h-12 bg-emerald-50 text-[#00a884] rounded-full flex items-center justify-center mx-auto mb-3 text-xl">
                    💬
                </div>
                <h3 className="text-sm font-bold text-gray-900 mb-1">No conversation history yet</h3>
                <p className="text-xs text-gray-500 max-w-xs mx-auto">
                    When this customer sends a message or receives a broadcast, the full message thread will appear here.
                </p>
            </div>
        );
    }

    const renderStatusBadge = (status) => {
        switch (status) {
            case 'read':
                return <span title="Read" className="text-blue-500 font-bold text-xs">✓✓</span>;
            case 'delivered':
                return <span title="Delivered" className="text-gray-400 font-bold text-xs">✓✓</span>;
            case 'sent':
                return <span title="Sent" className="text-gray-400 font-bold text-xs">✓</span>;
            case 'failed':
                return <span title="Failed" className="text-rose-500 font-bold text-xs">✕</span>;
            default:
                return <span title="Pending" className="text-amber-500 text-xs">🕒</span>;
        }
    };

    return (
        <div className="space-y-3 py-2 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
            {messages.map((msg) => {
                const isOutbound = msg.direction === 'outbound';
                let parsedContent = null;
                
                try {
                    parsedContent = typeof msg.content === 'string' ? JSON.parse(msg.content) : msg.content;
                } catch (e) {
                    parsedContent = { text: msg.content };
                }

                const messageText = parsedContent?.text || parsedContent?.body || (typeof msg.content === 'string' ? msg.content : '');
                const mediaUrl = parsedContent?.url || msg.attachment_url;

                return (
                    <div
                        key={msg.id}
                        className={`flex flex-col ${isOutbound ? 'items-end' : 'items-start'}`}
                    >
                        <div
                            className={`max-w-[80%] sm:max-w-[70%] rounded-2xl px-4 py-3 shadow-xs border ${
                                isOutbound
                                    ? 'bg-[#00a884] text-white border-emerald-600/20 rounded-tr-xs'
                                    : 'bg-white text-gray-800 border-gray-200/80 rounded-tl-xs'
                            }`}
                        >
                            {/* Sender Header */}
                            <div className="flex items-center justify-between gap-4 mb-1">
                                <span className={`text-[10px] font-bold uppercase tracking-wider ${isOutbound ? 'text-emerald-100' : 'text-gray-400'}`}>
                                    {isOutbound ? 'Outbound Agent / Bot' : customerName}
                                </span>
                                {parsedContent?.type && (
                                    <span className={`text-[9px] px-1.5 py-0.5 rounded font-bold uppercase tracking-wider ${
                                        isOutbound ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'
                                    }`}>
                                        {parsedContent.type}
                                    </span>
                                )}
                            </div>

                            {/* Media Attachment if present */}
                            {mediaUrl && (
                                <div className="mb-2 rounded-lg overflow-hidden border border-black/10">
                                    <a href={mediaUrl} target="_blank" rel="noopener noreferrer" className="block relative group">
                                        <img src={mediaUrl} alt="Attachment" className="max-h-48 w-full object-cover" />
                                        <div className="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-xs font-bold">
                                            View Full Media ↗
                                        </div>
                                    </a>
                                </div>
                            )}

                            {/* Text Body */}
                            {messageText && (
                                <p className="text-sm whitespace-pre-wrap leading-relaxed break-words font-normal">
                                    {messageText}
                                </p>
                            )}

                            {/* Footer: Time + Status */}
                            <div className={`flex items-center justify-end gap-1.5 mt-1.5 text-[10px] ${
                                isOutbound ? 'text-emerald-100' : 'text-gray-400'
                            }`}>
                                <span>
                                    {new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                </span>
                                {isOutbound && renderStatusBadge(msg.status)}
                            </div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
