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

export default function Thread({ conversation, onBack, approvedTemplates, onToggleFavorite }) {
    const [messages, setMessages] = useState([]);
    const [nextCursor, setNextCursor] = useState(null);
    const messagesEndRef = useRef(null);
    const [isInitialLoad, setIsInitialLoad] = useState(true);
    const [modalImage, setModalImage] = useState(null);

    // Tasks & Reminders State
    const [tasks, setTasks] = useState([]);
    const [taskTitle, setTaskTitle] = useState('');
    const [taskDesc, setTaskDesc] = useState('');
    const [taskDue, setTaskDue] = useState('');
    const [isSavingTask, setIsSavingTask] = useState(false);
    const [showMobileDetails, setShowMobileDetails] = useState(false);

    const fetchTasks = () => {
        if (!conversation?.id) return;
        window.axios.get(`/api/conversations/${conversation.id}/tasks`).then(res => {
            setTasks(res.data || []);
        }).catch(console.error);
    };

    useEffect(() => {
        if (!conversation?.id) return;
        fetchTasks();
        const taskInterval = setInterval(fetchTasks, 20000);
        return () => clearInterval(taskInterval);
    }, [conversation?.id]);

    const handleCreateTask = (e) => {
        e.preventDefault();
        if (!taskTitle.trim() || isSavingTask) return;
        setIsSavingTask(true);
        window.axios.post('/tasks', {
            contact_id: conversation.contact_id || null,
            conversation_id: conversation.id,
            title: taskTitle,
            description: taskDesc,
            due_at: taskDue ? new Date(taskDue).toISOString() : null,
        }).then(() => {
            setTaskTitle('');
            setTaskDesc('');
            setTaskDue('');
            fetchTasks();
        }).catch(err => {
            alert('Failed to save task: ' + (err.response?.data?.message || err.message));
        }).finally(() => {
            setIsSavingTask(false);
        });
    };

    const handleResolveTask = (taskId) => {
        window.axios.patch(`/tasks/${taskId}/status`, { status: 'resolved' })
            .then(fetchTasks)
            .catch(console.error);
    };

    const handleDeleteTask = (taskId) => {
        if (!confirm('Are you sure you want to delete this reminder?')) return;
        window.axios.delete(`/tasks/${taskId}`)
            .then(fetchTasks)
            .catch(console.error);
    };

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
        setMessages([]);
        setNextCursor(null);
        setIsInitialLoad(true);
        fetchMessages();

        // Fast 15-second polling fallback ensuring backup sync when WS is idle
        const pollInterval = setInterval(() => {
            fetchMessages();
        }, 15000);

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
            clearInterval(pollInterval);
            channel.stopListening('.message.received');
        };
    }, [conversation?.id]);

    const resolveMediaUrl = (rawUrl) => {
        if (!rawUrl) return '';
        
        // If it's a full HTTP URL from an external provider (MSG91/WhatsApp), DO NOT truncate it!
        if (typeof rawUrl === 'string' && rawUrl.startsWith('http')) {
            const isExternal = !rawUrl.includes('localhost') 
                            && !rawUrl.includes('127.0.0.1')
                            && !rawUrl.includes(window.location.hostname);
                            
            if (isExternal) {
                return rawUrl;
            }
        }

        // It's a local or dev URL. Rewrite it to use the current origin so media loads even if app.url was wrong.
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
        <div className="flex h-full w-full bg-[#0b141a]">
            {/* Left Column: Chat view */}
            <div className="flex flex-1 h-full flex-col relative border-r border-[#222d34] min-w-0">
                {/* Active Chat Header */}
                <header className="flex h-[60px] items-center justify-between border-b border-[#222d34] bg-[#202c33] px-4 z-10 flex-shrink-0">
                    <div className="flex items-center gap-3">
                        <button 
                            type="button"
                            onClick={onBack} 
                            className="md:hidden text-[#8696a0] hover:text-[#e9edef] transition-colors p-1 -ml-2"
                            title="Back to Conversations"
                            aria-label="Back to Conversations"
                        >
                            <svg className="w-6 h-6 fill-current" viewBox="0 0 24 24">
                                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                            </svg>
                        </button>
                        <div className="relative">
                            <div className="h-10 w-10 rounded-full bg-[#374248] flex items-center justify-center text-[#e9edef] font-medium text-sm">
                                {conversation?.customer_name ? conversation.customer_name.substring(0, 2).toUpperCase() : (conversation?.customer_number ? conversation.customer_number.substring(0, 2) : '??')}
                            </div>
                            <span className={`absolute -bottom-0.5 -right-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full text-[8px] font-bold text-white shadow ring-1 ring-[#202c33] ${
                                conversation?.channel === 'facebook'
                                    ? 'bg-[#1877f2]'
                                    : conversation?.channel === 'instagram'
                                    ? 'bg-gradient-to-tr from-[#f09433] via-[#dc2743] to-[#bc1888]'
                                    : 'bg-[#25d366]'
                            }`} title={conversation?.channel || 'whatsapp'}>
                                {conversation?.channel === 'facebook' ? 'f' : conversation?.channel === 'instagram' ? 'ig' : 'wa'}
                            </span>
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h3 className="font-medium text-sm text-[#e9edef]">
                                    {conversation?.customer_name || (conversation?.customer_number && !conversation?.customer_number.startsWith('fb_') ? `+${conversation.customer_number}` : conversation?.customer_number)}
                                </h3>
                                {onToggleFavorite && (
                                    <button
                                        type="button"
                                        onClick={() => onToggleFavorite(conversation.id)}
                                        className={`p-1 rounded-full hover:bg-[#2a3942] transition-colors ${
                                            conversation?.is_favorite ? 'text-amber-400' : 'text-[#8696a0] hover:text-amber-400'
                                        }`}
                                        title={conversation?.is_favorite ? "Remove from favorites" : "Mark as favorite"}
                                        aria-label={conversation?.is_favorite ? "Remove from favorites" : "Mark as favorite"}
                                        aria-pressed={!!conversation?.is_favorite}
                                    >
                                        <svg className="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                            {conversation?.is_favorite ? (
                                                <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                            ) : (
                                                <path d="M22 9.24l-7.19-.62L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27 18.18 21l-1.63-7.03L22 9.24zM12 15.4l-3.76 2.27 1-4.28-3.32-2.88 4.38-.38L12 6.1l1.71 4.04 4.38.38-3.32 2.88 1 4.28L12 15.4z"/>
                                            )}
                                        </svg>
                                    </button>
                                )}
                                <span className={`text-[10px] uppercase font-bold px-1.5 py-0.5 rounded ${
                                    conversation?.channel === 'facebook'
                                        ? 'bg-[#1877f2]/20 text-[#1877f2] border border-[#1877f2]/40'
                                        : conversation?.channel === 'instagram'
                                        ? 'bg-pink-500/20 text-pink-400 border border-pink-500/40'
                                        : 'bg-[#00a884]/20 text-[#00a884] border border-[#00a884]/40'
                                }`}>
                                    {conversation?.channel || 'whatsapp'}
                                </span>
                            </div>
                            <p className="text-xs text-[#8696a0] font-medium">
                                {conversation?.channel === 'facebook' ? 'Facebook Messenger' : conversation?.channel === 'instagram' ? 'Instagram Direct' : 'WhatsApp'}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-4 text-[#8696a0]">
                        <button 
                            type="button"
                            onClick={() => setShowMobileDetails(!showMobileDetails)} 
                            className={`md:hidden p-1.5 rounded transition-colors ${showMobileDetails ? 'text-[#00a884] bg-[#2a3942]' : 'text-[#8696a0] hover:text-[#e9edef]'}`}
                            title="Toggle Reminders & Issues"
                            aria-label="Toggle Reminders and Tasks Panel"
                            aria-expanded={showMobileDetails}
                        >
                            <svg className="w-5.5 h-5.5 fill-current" viewBox="0 0 24 24">
                                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
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
                                                        {msg.status === 'failed' && <span className="text-red-400 font-bold" title={msg.failure_reason || 'Send failed'}>!</span>}
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        }

                        // Resolves actual template text or full body text
                        const getDisplayText = () => {
                            if (content.type === 'template') {
                                // 1. Show stored text if it contains actual template body (not just a placeholder)
                                const storedText = content.text || content.body || '';
                                const trimmedStored = typeof storedText === 'string' ? storedText.trim() : '';
                                
                                // Accept the stored text unless it's ONLY a placeholder like "📋 Template: name"
                                const isPlaceholder = /^(📋\s*)?Template:\s*.+$/i.test(trimmedStored) && !trimmedStored.includes(' ') === false;
                                const isJustPlaceholder = trimmedStored === `📋 Template: ${content.template_name}` 
                                    || trimmedStored === `Template: ${content.template_name}`;
                                
                                if (trimmedStored && !isJustPlaceholder) {
                                    return trimmedStored;
                                }

                                // 2. Fallback: look up the template body from approvedTemplates
                                if (content.template_name && Array.isArray(approvedTemplates)) {
                                    const found = approvedTemplates.find(t => t.name === content.template_name);
                                    if (found && found.components) {
                                        try {
                                            const components = typeof found.components === 'string' ? JSON.parse(found.components) : found.components;
                                            const bodyObj = components.find(c => c.type === 'BODY' || c.type === 'body');
                                            if (bodyObj && bodyObj.text) {
                                                return bodyObj.text;
                                            }
                                        } catch (e) {}
                                    }
                                }

                                // 3. Last resort: show the placeholder
                                return `📋 Template: ${content.template_name || 'WhatsApp Template'}`;
                            }

                            const rawText = content.text !== undefined ? content.text : (content.body !== undefined ? content.body : (typeof content === 'string' ? content : ''));
                            const textStr = String(rawText || '');
                            return textStr.trim() !== '' ? textStr : (content.caption || '');
                        };

                        // Resolves template buttons if available
                        const getTemplateButtons = () => {
                            if (content.buttons && Array.isArray(content.buttons)) {
                                return content.buttons;
                            }
                            if (content.template_name && Array.isArray(approvedTemplates)) {
                                const found = approvedTemplates.find(t => t.name === content.template_name);
                                if (found && found.components) {
                                    try {
                                        const components = typeof found.components === 'string' ? JSON.parse(found.components) : found.components;
                                        if (Array.isArray(components)) {
                                            const btnComp = components.find(c => (c.type || '').toUpperCase() === 'BUTTONS');
                                            if (btnComp && Array.isArray(btnComp.buttons)) {
                                                return btnComp.buttons;
                                            }
                                        }
                                    } catch (e) {}
                                }
                            }
                            return [];
                        };

                        const templateButtons = getTemplateButtons();
                        const displayText = getDisplayText();
                        const isButtonReply = content.type === 'button_reply' || Boolean(content.button_text);

                        if (msg.is_internal) {
                            return (
                                <div key={msg.id} className="flex justify-center mb-2">
                                    <div className="max-w-[85%] sm:max-w-[70%] rounded-xl bg-[#2b2115] border border-amber-600/30 px-4 py-2.5 text-xs text-amber-200 shadow-sm flex items-start gap-2">
                                        <span className="text-sm shrink-0">🛡️</span>
                                        <div>
                                            <div className="font-black text-[9px] uppercase tracking-wider text-amber-500 mb-1">Internal Agent Reminder</div>
                                            <p className="leading-relaxed text-gray-200">{displayText}</p>
                                            <span className="block mt-1.5 text-[9px] text-amber-600 font-bold">{formattedTime}</span>
                                        </div>
                                    </div>
                                </div>
                            );
                        }

                        if (!isOutbound) {
                            return (
                                <div key={msg.id} className="flex justify-start mb-1">
                                    <div className="relative max-w-[85%] sm:max-w-[75%] lg:max-w-[65%] rounded-lg rounded-tl-none bg-[#202c33] px-3 py-1.5 text-sm text-[#e9edef] shadow-sm">
                                        {isButtonReply ? (
                                            <div className="space-y-1 mr-14">
                                                <div className="flex items-center gap-1 text-[10px] uppercase font-bold text-[#00a884]">
                                                    <span>🔘</span>
                                                    <span>Button Selected</span>
                                                </div>
                                                <div className="py-1 px-2.5 bg-[#2a3942] rounded-md text-[#e9edef] font-semibold text-xs border border-[#374248] inline-block">
                                                    {content.button_text || displayText || 'Button Response'}
                                                </div>
                                            </div>
                                        ) : (
                                            <span className="mr-16 leading-relaxed break-words block">{displayText || 'Message'}</span>
                                        )}
                                        
                                        {content.type === 'interactive' && content.interactive?.action?.buttons && (
                                            <div className="mt-2 flex flex-col gap-1 w-full">
                                                {content.interactive.action.buttons.map((btn, i) => (
                                                    <div key={i} className="text-center py-1.5 px-3 bg-[#2a3942] rounded text-[#00a884] font-medium text-xs border border-[#374248]">
                                                        {btn.reply?.title || 'Button'}
                                                    </div>
                                                ))}
                                            </div>
                                        )}

                                        <span className="absolute bottom-1 right-2 text-[11px] text-[#8696a0] font-medium">{formattedTime}</span>
                                    </div>
                                </div>
                            );
                        }

                        return (
                            <div key={msg.id} className="flex justify-end mb-1">
                                <div className="relative max-w-[85%] sm:max-w-[75%] lg:max-w-[65%] rounded-lg rounded-tr-none bg-[#005c4b] px-3 py-1.5 text-sm text-[#e9edef] shadow-sm">
                                    <span className="mr-16 leading-relaxed break-words block">{displayText || 'Message'}</span>
                                    
                                    {/* Template Buttons */}
                                    {templateButtons.length > 0 && (
                                        <div className="mt-2 pt-2 border-t border-[#01705b] flex flex-col gap-1.5 w-full pb-4">
                                            {templateButtons.map((btn, i) => (
                                                <div key={i} className="text-center py-1.5 px-3 bg-[#01705b] rounded text-[#e9edef] font-semibold text-xs border border-[#02856c] flex items-center justify-center gap-1.5">
                                                    <span>{btn.type === 'PHONE_NUMBER' ? '📞' : btn.type === 'URL' ? '🌐' : '🔘'}</span>
                                                    <span>{btn.text || btn.label || btn.phone_number || btn.url || 'Button'}</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {/* Interactive Buttons */}
                                    {content.type === 'interactive' && content.interactive?.action?.buttons && (
                                        <div className="mt-2 flex flex-col gap-1 w-full pb-4">
                                            {content.interactive.action.buttons.map((btn, i) => (
                                                <div key={i} className="text-center py-1.5 px-3 bg-[#01705b] rounded text-[#e9edef] font-medium text-xs border border-[#02856c]">
                                                    {btn.reply?.title || 'Button'}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    
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

                {/* Bottom Input */}
                <div className="flex-shrink-0">
                    {usePage().props.impersonation?.is_impersonating ? (
                        <div className="bg-[#202c33] p-4 text-center text-[#8696a0] border-t border-[#222d34] text-sm">
                            Sending messages is disabled while impersonating.
                        </div>
                    ) : (
                        <Composer conversation={conversation} approvedTemplates={approvedTemplates} onSent={(newMessage) => {
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
            </div>

            {/* Right Column: Customer Details & Reminders sidebar */}
            <div className={`w-[320px] h-full flex-shrink-0 bg-[#111b21] flex flex-col border-l border-[#222d34] overflow-y-auto custom-scrollbar p-4 text-[#e9edef] z-40 ${showMobileDetails ? 'fixed inset-y-0 right-0 w-full max-w-[320px] border-l border-[#222d34] shadow-2xl animate-fade-in' : 'hidden md:flex'}`}>
                <h3 className="text-sm font-bold flex items-center justify-between border-b border-[#222d34] pb-3 mb-4">
                    <span className="flex items-center gap-2">📋 Reminders & Issues</span>
                    {showMobileDetails && (
                        <button type="button" onClick={() => setShowMobileDetails(false)} className="text-[#8696a0] hover:text-[#e9edef] text-xs font-bold px-2 py-1 bg-[#202c33] rounded">✕ Close</button>
                    )}
                </h3>

                {/* Tasks List (At the Top) */}
                <div className="space-y-3 mb-6">
                    <div className="text-[10px] font-black uppercase text-gray-400 tracking-wider">
                        Active Reminders ({tasks.filter(t => t.status !== 'resolved').length})
                    </div>

                    {tasks.length === 0 ? (
                        <div className="text-xs text-gray-500 text-center py-6">No tasks logged for this customer.</div>
                    ) : (
                        <div className="space-y-2.5">
                            {tasks.map(task => {
                                const isOverdue = task.due_at && new Date(task.due_at) <= new Date() && task.status !== 'resolved';
                                return (
                                    <div key={task.id} className="p-3 bg-[#202c33] rounded-xl border border-[#2d3a42] text-xs space-y-2">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="font-bold text-gray-100">{task.title}</div>
                                            <span className={`text-[8px] font-extrabold uppercase px-1.5 py-0.2 rounded-full shrink-0 ${
                                                task.status === 'resolved' ? 'bg-green-950 text-green-400 border border-green-800' : 'bg-blue-950 text-blue-400 border border-blue-800'
                                            }`}>
                                                {task.status}
                                            </span>
                                        </div>
                                        {task.description && (
                                            <p className="text-[11px] text-gray-400 leading-relaxed">{task.description}</p>
                                        )}
                                        {task.due_at && (
                                            <div className="flex items-center justify-between text-[10px] pt-1 border-t border-[#2d3a42]">
                                                <span className={isOverdue ? 'text-rose-400 font-bold' : 'text-gray-400'}>
                                                    ⏰ {new Date(task.due_at).toLocaleString()}
                                                </span>
                                            </div>
                                        )}
                                        <div className="flex items-center justify-end gap-1.5 pt-1">
                                            {task.status !== 'resolved' && (
                                                <button
                                                    onClick={() => handleResolveTask(task.id)}
                                                    className="px-2 py-0.5 bg-green-950 hover:bg-green-900 border border-green-800 text-green-400 text-[10px] font-bold rounded"
                                                >
                                                    Resolve
                                                </button>
                                            )}
                                            <button
                                                onClick={() => handleDeleteTask(task.id)}
                                                className="px-1.5 py-0.5 bg-rose-950 hover:bg-rose-900 border border-rose-800 text-rose-400 text-[10px] font-bold rounded"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Create Task Form */}
                <form onSubmit={handleCreateTask} className="space-y-3 mb-6 bg-[#202c33] p-3 rounded-xl border border-[#2d3a42] flex-shrink-0">
                    <div className="text-[10px] font-black uppercase text-[#00a884] tracking-wider">Log New Task</div>
                    <div>
                        <input
                            type="text"
                            placeholder="Reminder Title"
                            value={taskTitle}
                            onChange={e => setTaskTitle(e.target.value)}
                            required
                            className="w-full bg-[#2a3942] border border-[#2d3a42] rounded-lg text-xs text-[#e9edef] px-2.5 py-1.5 focus:ring-1 focus:ring-[#00a884] focus:outline-none placeholder-gray-500"
                        />
                    </div>
                    <div>
                        <textarea
                            placeholder="Description..."
                            value={taskDesc}
                            onChange={e => setTaskDesc(e.target.value)}
                            rows={2}
                            className="w-full bg-[#2a3942] border border-[#2d3a42] rounded-lg text-xs text-[#e9edef] px-2.5 py-1.5 focus:ring-1 focus:ring-[#00a884] focus:outline-none placeholder-gray-500 resize-none"
                        />
                    </div>
                    <div>
                        <label className="block text-[9px] text-gray-400 font-bold uppercase mb-1">Due Date & Time</label>
                        <input
                            type="datetime-local"
                            value={taskDue}
                            onChange={e => setTaskDue(e.target.value)}
                            className="w-full bg-[#2a3942] border border-[#2d3a42] rounded-lg text-xs text-[#e9edef] px-2.5 py-1.5 focus:ring-1 focus:ring-[#00a884] focus:outline-none"
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={isSavingTask || !taskTitle.trim()}
                        className="w-full py-1.5 bg-[#00a884] hover:bg-[#008f70] disabled:bg-gray-700 text-white rounded-lg text-xs font-bold transition"
                    >
                        {isSavingTask ? 'Saving...' : 'Set Reminder ⏰'}
                    </button>
                </form>
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
