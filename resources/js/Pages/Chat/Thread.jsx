import { useState, useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import Composer from './Composer';

/**
 * Render a conversation thread with message history, real-time updates, media previews, and message composition.
 * @param {Object} props - Component properties.
 * @param {Object} props.conversation - Conversation whose messages are displayed.
 * @returns {JSX.Element} The conversation thread interface.
 */
const CustomAudioPlayer = ({ src }) => {
    const audioRef = useRef(null);
    const [isPlaying, setIsPlaying] = useState(false);
    const [progress, setProgress] = useState(0);
    const [duration, setDuration] = useState(0);

    const togglePlay = () => {
        if (!audioRef.current) return;
        if (isPlaying) {
            audioRef.current.pause();
        } else {
            audioRef.current.play().catch(console.error);
        }
        setIsPlaying(!isPlaying);
    };

    const handleTimeUpdate = () => {
        if (!audioRef.current) return;
        const current = audioRef.current.currentTime;
        const dur = audioRef.current.duration;
        if (dur && dur !== Infinity) {
            setProgress((current / dur) * 100);
            setDuration(dur);
        }
    };

    const handleLoadedMetadata = () => {
        if (!audioRef.current) return;
        if (audioRef.current.duration === Infinity || isNaN(audioRef.current.duration)) {
            audioRef.current.currentTime = 1e101;
            audioRef.current.ontimeupdate = () => {
                audioRef.current.ontimeupdate = null;
                audioRef.current.currentTime = 0;
                setDuration(audioRef.current.duration);
            };
        } else {
            setDuration(audioRef.current.duration);
        }
    };

    const handleSeek = (e) => {
        if (!audioRef.current || !duration) return;
        const rect = e.currentTarget.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const percent = clickX / rect.width;
        audioRef.current.currentTime = percent * duration;
        setProgress(percent * 100);
    };

    const formatTime = (time) => {
        if (!time || isNaN(time)) return '0:00';
        const m = Math.floor(time / 60);
        const s = Math.floor(time % 60);
        return `${m}:${s.toString().padStart(2, '0')}`;
    };

    return (
        <div className="flex items-center gap-3 w-full bg-transparent">
            <button onClick={togglePlay} className="w-8 h-8 flex-shrink-0 flex items-center justify-center text-[#8696a0] hover:text-[#e9edef] transition-colors focus:outline-none">
                {isPlaying ? (
                    <svg viewBox="0 0 24 24" className="w-6 h-6 fill-current">
                        <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                    </svg>
                ) : (
                    <svg viewBox="0 0 24 24" className="w-6 h-6 fill-current">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                )}
            </button>
            <div className="flex-1 flex flex-col justify-center">
                <div 
                    className="h-1.5 bg-[#374248] rounded-full w-full cursor-pointer relative overflow-hidden"
                    onClick={handleSeek}
                >
                    <div 
                        className="h-full bg-[#00a884] rounded-full pointer-events-none transition-all duration-75" 
                        style={{ width: `${progress}%` }}
                    />
                </div>
                <div className="text-[11px] text-[#8696a0] mt-1 font-medium">
                    {formatTime(audioRef.current?.currentTime || 0)} / {formatTime(duration)}
                </div>
            </div>
            <audio 
                ref={audioRef}
                src={src}
                onTimeUpdate={handleTimeUpdate}
                onLoadedMetadata={handleLoadedMetadata}
                onEnded={() => { setIsPlaying(false); setProgress(0); }}
                className="hidden"
                preload="metadata"
            />
        </div>
    );
};

export default function Thread({ conversation, onBack }) {
    const [messages, setMessages] = useState([]);
    const [nextCursor, setNextCursor] = useState(null);
    const messagesEndRef = useRef(null);
    const [isInitialLoad, setIsInitialLoad] = useState(true);
    const [modalImage, setModalImage] = useState(null);

    const formatTimestamp = (dateStr) => {
        if (!dateStr) return '';
        try {
            const strVal = String(dateStr).trim().replace(' ', 'T');
            const isoStr = strVal.endsWith('Z') ? strVal : strVal + 'Z';
            const date = new Date(isoStr);
            return isNaN(date.getTime()) 
                ? '' 
                : date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return '';
        }
    };

    const fetchMessages = (cursor = null) => {
        if (!conversation?.id) return;
        let url = `/api/conversations/${conversation.id}/messages`;
        if (cursor) url += `?cursor=${cursor}`;

        window.axios.get(url).then(res => {
            if (cursor) {
                setMessages(prev => [...(res.data?.data || []), ...prev]);
            } else {
                setMessages(res.data?.data || []);
                setIsInitialLoad(false);
            }
            setNextCursor(res.data?.next_cursor || null);
        }).catch(err => {
            console.error('Error fetching messages:', err);
        });
    };

    useEffect(() => {
        if (!conversation?.id) return;
        setIsInitialLoad(true);
        fetchMessages();

        const channel = window.Echo.channel(`conversations.${conversation.id}`);
        channel.listen('.message.received', (e) => {
            if (e.message && typeof e.message === 'object') {
                if (e.message.direction === 'inbound') {
                    window.axios.post(`/api/conversations/${conversation.id}/read`).catch(console.error);
                }
                setMessages(prev => {
                    const exists = prev.some(m => m.id === e.message.id);
                    if (exists) {
                        return prev.map(m => m.id === e.message.id ? e.message : m);
                    }
                    return [...prev, e.message];
                });
            } else {
                fetchMessages();
            }
        });

        return () => {
            channel.stopListening('.message.received');
        };
    }, [conversation?.id]);

    useEffect(() => {
        if (!isInitialLoad) {
            messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
        }
    }, [messages, isInitialLoad]);

    const resolveMediaUrl = (rawUrl) => {
        if (!rawUrl) return '';
        if (typeof rawUrl === 'string' && rawUrl.includes('/storage/')) {
            const pathPart = rawUrl.substring(rawUrl.indexOf('/storage/'));
            return window.location.origin + pathPart;
        }
        if (typeof rawUrl === 'string' && !rawUrl.startsWith('http')) {
            return `${window.location.origin}/storage/${rawUrl.replace(/^\/+/, '')}`;
        }
        return rawUrl;
    };

    const parseMessageContent = (rawContent) => {
        let contentData = {};
        try {
            if (typeof rawContent === 'string') {
                contentData = JSON.parse(rawContent);
                if (typeof contentData === 'string') {
                    contentData = JSON.parse(contentData);
                }
            } else if (typeof rawContent === 'object' && rawContent !== null) {
                contentData = rawContent;
            } else {
                contentData = { type: 'text', text: String(rawContent || '') };
            }
        } catch (e) {
            contentData = { type: 'text', text: String(rawContent || '') };
        }
        return contentData;
    };

    return (
        <div className="flex h-full flex-col bg-[#0b141a] relative">
            {/* Active Chat Header */}
            <header className="flex h-[60px] items-center justify-between border-b border-[#222d34] bg-[#202c33] px-4 z-10 flex-shrink-0">
                <div className="flex items-center gap-3">
                    <button 
                        onClick={onBack} 
                        className="md:hidden text-[#8696a0] hover:text-[#e9edef] transition-colors p-1 -ml-2"
                        title="Back to Conversations"
                    >
                        <svg className="w-6 h-6 fill-current" viewBox="0 0 24 24">
                            <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                        </svg>
                    </button>
                    <div className="h-10 w-10 rounded-full bg-[#374248] flex items-center justify-center text-[#e9edef] font-medium text-sm">
                        {conversation?.customer_number ? conversation.customer_number.substring(0, 2) : '??'}
                    </div>
                    <div>
                        <h3 className="font-medium text-sm text-[#e9edef]">
                            +{conversation?.customer_number || ''}
                        </h3>
                        <p className="text-xs text-[#00a884] font-medium">online</p>
                    </div>
                </div>
                <div className="flex items-center gap-5 text-[#8696a0]">
                    <button title="Search" className="hover:text-[#e9edef] transition-colors">
                        <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                            <path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                    </button>
                    <button title="Menu" className="hover:text-[#e9edef] transition-colors">
                        <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                            <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                        </svg>
                    </button>
                </div>
            </header>

            {/* Message Stream Area */}
            <div className="flex-1 overflow-y-auto p-4 lg:p-6 space-y-2 bg-[radial-gradient(#202c33_1px,transparent_1px)] [background-size:16px_16px] custom-scrollbar">
                {nextCursor && (
                    <div className="text-center pb-4">
                        <button onClick={() => fetchMessages(nextCursor)} className="px-3 py-1 rounded-md bg-[#182229] text-xs font-medium text-[#8696a0] hover:text-[#e9edef] border border-[#222d34] shadow transition-colors">
                            Load Previous Messages
                        </button>
                    </div>
                )}

                {/* Today Divider Pill */}
                {messages.length > 0 && (
                    <div className="flex justify-center my-3">
                        <span className="rounded-md bg-[#182229] px-3 py-1 text-[11px] text-[#8696a0] font-medium uppercase tracking-wider shadow border border-[#222d34]">
                            Today
                        </span>
                    </div>
                )}

                {messages.map((msg) => {
                    const isOutbound = msg.direction === 'outbound';
                    const formattedTime = formatTimestamp(msg.created_at);
                    const content = parseMessageContent(msg.content);

                    const isMedia = ['image', 'audio', 'document', 'video'].includes(content.type);

                    if (isMedia) {
                        const displayUrl = resolveMediaUrl(content.url);
                        return (
                            <div key={msg.id} className={`flex ${isOutbound ? 'justify-end' : 'justify-start'} mb-2`}>
                                <div className="flex flex-col gap-1 w-[300px] max-w-full">
                                    {content.type === 'image' ? (
                                        <img 
                                            src={displayUrl} 
                                            alt="Photo" 
                                            onClick={() => setModalImage(displayUrl)}
                                            className="rounded-xl object-cover max-h-[260px] w-full cursor-pointer hover:opacity-90 transition-opacity border border-[#222d34] shadow-md" 
                                        />
                                    ) : content.type === 'video' ? (
                                        <div className="bg-[#111b21] rounded-xl overflow-hidden border border-[#222d34] shadow-md w-full">
                                            <video src={displayUrl} controls className="max-h-[260px] w-full" />
                                        </div>
                                    ) : content.type === 'document' ? (
                                        <div className="bg-[#111b21] p-3 rounded-xl border border-[#222d34] shadow-md w-full flex items-center gap-3">
                                            <div className="bg-[#202c33] p-2 rounded-lg shrink-0">
                                                <svg className="w-8 h-8 text-[#8696a0]" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                                                </svg>
                                            </div>
                                            <a href={displayUrl} target="_blank" rel="noreferrer" className="flex-1 truncate text-sm text-[#e9edef] hover:underline font-medium" title={content.filename || 'Document'}>
                                                {content.filename || 'Document'}
                                            </a>
                                            <a href={displayUrl} download className="shrink-0 p-1 text-[#8696a0] hover:text-[#00a884] transition-colors" title="Download">
                                                <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                                            </a>
                                        </div>
                                    ) : (
                                        <div className="bg-[#111b21] p-3 rounded-xl border border-[#222d34] shadow-md w-[260px] max-w-full">
                                            <CustomAudioPlayer src={displayUrl} />
                                        </div>
                                    )}

                                    {/* Caption or Timestamp */}
                                    <div className="flex items-center justify-between px-1 text-[11px] text-[#8696a0]">
                                        <span>{content.caption || ''}</span>
                                        <div className="flex items-center gap-1 ml-auto">
                                            <span>{formattedTime}</span>
                                            {isOutbound && (
                                                <>
                                                    {msg.status === 'sent' && <span className="text-[#8696a0] font-bold">✓</span>}
                                                    {msg.status === 'delivered' && <span className="text-[#8696a0] font-bold">✓✓</span>}
                                                    {msg.status === 'read' && <span className="text-[#53bdeb] font-bold">✓✓</span>}
                                                    {msg.status === 'queued' && <span className="text-[#8696a0] animate-pulse">🕒</span>}
                                                    {msg.status === 'failed' && <span className="text-red-400 font-bold">!</span>}
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    }

                    // Safe string handling to prevent runtime .trim() crash
                    const rawText = content.text !== undefined ? content.text : (typeof content === 'string' ? content : '');
                    const textStr = String(rawText || '');
                    const displayText = textStr.trim() !== '' ? textStr : (content.caption || '');

                    if (!isOutbound) {
                        return (
                            <div key={msg.id} className="flex justify-start mb-1">
                                <div className="relative max-w-[85%] sm:max-w-[75%] lg:max-w-[65%] rounded-lg rounded-tl-none bg-[#202c33] px-3 py-1.5 text-sm text-[#e9edef] shadow-sm">
                                    <span className="mr-16 leading-relaxed break-words block">{displayText || 'Media'}</span>
                                    <span className="absolute bottom-1 right-2 text-[11px] text-[#8696a0] font-medium">{formattedTime}</span>
                                </div>
                            </div>
                        );
                    }

                    return (
                        <div key={msg.id} className="flex justify-end mb-1">
                            <div className="relative max-w-[85%] sm:max-w-[75%] lg:max-w-[65%] rounded-lg rounded-tr-none bg-[#005c4b] px-3 py-1.5 text-sm text-[#e9edef] shadow-sm">
                                <span className="mr-16 leading-relaxed break-words block">{displayText || 'Media'}</span>
                                
                                <div className="absolute bottom-1 right-2 flex items-center gap-1 text-[11px] text-[#8696a0]">
                                    <span>{formattedTime}</span>
                                    {msg.status === 'sent' && <span className="text-[#8696a0] font-bold">✓</span>}
                                    {msg.status === 'delivered' && <span className="text-[#8696a0] font-bold">✓✓</span>}
                                    {msg.status === 'read' && <span className="text-[#53bdeb] font-bold">✓✓</span>}
                                    {msg.status === 'queued' && <span className="text-[#8696a0] animate-pulse">🕒</span>}
                                    {msg.status === 'failed' && <span className="text-red-400 font-bold" title={msg.failure_reason || 'Send failed'}>!</span>}
                                </div>
                            </div>
                        </div>
                    );
                })}
                <div ref={messagesEndRef} className="h-2" />
            </div>

            {/* Photo Lightbox Modal */}
            {modalImage && (
                <div 
                    onClick={() => setModalImage(null)} 
                    className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4 cursor-pointer backdrop-blur-md"
                >
                    <img src={modalImage} alt="Expanded Photo" className="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl" />
                </div>
            )}

            {/* Bottom Message Input Bar */}
            <div className="flex-shrink-0">
                {usePage().props.impersonation?.is_impersonating ? (
                    <div className="bg-[#202c33] p-4 text-center text-[#8696a0] border-t border-[#222d34] text-sm">
                        Sending messages is disabled while impersonating.
                    </div>
                ) : (
                    <Composer conversation={conversation} onSent={(newMessage) => {
                        if (newMessage) {
                            setMessages(prev => {
                                const exists = prev.some(m => m.id === newMessage.id);
                                return exists ? prev.map(m => m.id === newMessage.id ? newMessage : m) : [...prev, newMessage];
                            });
                        } else {
                            fetchMessages();
                        }
                    }} />
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
