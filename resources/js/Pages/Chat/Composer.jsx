import { useState, useRef } from 'react';

export default function Composer({ conversation, onSent }) {
    const [content, setContent] = useState('');
    const [sending, setSending] = useState(false);
    const [selectedImage, setSelectedImage] = useState(null);
    const [imagePreview, setImagePreview] = useState(null);

    // Interactive Button States
    const [showButtonsPanel, setShowButtonsPanel] = useState(false);
    const [interactiveButtons, setInteractiveButtons] = useState(['', '', '']);

    // Voice Recorder States
    const [isRecording, setIsRecording] = useState(false);
    const [recordingTime, setRecordingTime] = useState(0);
    const [audioBlob, setAudioBlob] = useState(null);
    const mediaRecorderRef = useRef(null);
    const audioChunksRef = useRef([]);
    const timerRef = useRef(null);
    const fileInputRef = useRef(null);

    const handleSend = (e) => {
        if (e) e.preventDefault();
        if (sending) return;

        // 1. Send Audio Voice Note (Native browser MIME type)
        if (audioBlob) {
            const typeStr = audioBlob.type || 'audio/webm';
            const ext = typeStr.includes('ogg') ? 'ogg' : (typeStr.includes('mp4') || typeStr.includes('m4a') ? 'm4a' : 'webm');
            sendMediaFile(audioBlob, 'audio', `voicenote.${ext}`);
            return;
        }

        // 2. Send Photo / Media
        if (selectedImage) {
            let mediaType = 'image';
            if (selectedImage.type.startsWith('video/')) mediaType = 'video';
            else if (!selectedImage.type.startsWith('image/')) mediaType = 'document';

            sendMediaFile(selectedImage, mediaType, selectedImage.name, content);
            return;
        }

        // 3. Send Text Message (or Interactive if buttons are present)
        if (!content.trim()) return;

        const validButtons = interactiveButtons.filter(b => b.trim() !== '').map(b => ({
            type: 'reply',
            reply: { id: `btn_${Date.now()}_${Math.random().toString(36).substr(2,9)}`, title: b.trim().substring(0, 20) }
        }));

        const payload = {
            content: content,
            type: validButtons.length > 0 ? 'interactive' : 'text',
        };

        if (validButtons.length > 0) {
            payload.interactive_type = 'button';
            payload.buttons = validButtons;
        }

        setSending(true);
        window.axios.post(`/api/conversations/${conversation.id}/messages`, payload).then((res) => {
            setContent('');
            setInteractiveButtons(['', '', '']);
            setShowButtonsPanel(false);
            onSent(res.data?.message);
        }).catch((err) => {
            const msg = err.response?.data?.message || err.response?.data?.error || 'Failed to send message.';
            alert(msg);
        }).finally(() => {
            setSending(false);
        });
    };

    const sendMediaFile = (fileOrBlob, type, filename, caption = '') => {
        setSending(true);
        const formData = new FormData();
        formData.append('file', fileOrBlob, filename);
        formData.append('type', type);
        if (caption) formData.append('caption', caption);

        window.axios.post(`/api/conversations/${conversation.id}/media`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        }).then((res) => {
            setContent('');
            setSelectedImage(null);
            setImagePreview(null);
            setAudioBlob(null);
            onSent(res.data?.message);
        }).catch((err) => {
            const msg = err.response?.data?.message || err.response?.data?.error || 'Failed to send media file.';
            alert(msg);
        }).finally(() => {
            setSending(false);
        });
    };

    const handleImageSelect = (e) => {
        const file = e.target.files[0];
        if (file) {
            setSelectedImage(file);
            if (file.type.startsWith('image/')) {
                setImagePreview(URL.createObjectURL(file));
            } else {
                setImagePreview(null); // No preview for docs/videos
            }
        }
    };

    const startRecording = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            
            let mimeType = 'audio/webm';
            if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                mimeType = 'audio/webm;codecs=opus';
            } else if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
                mimeType = 'audio/ogg;codecs=opus';
            } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
                mimeType = 'audio/mp4';
            }

            mediaRecorderRef.current = new MediaRecorder(stream, { mimeType });
            audioChunksRef.current = [];

            mediaRecorderRef.current.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    audioChunksRef.current.push(event.data);
                }
            };

            mediaRecorderRef.current.onstop = () => {
                const actualMime = mediaRecorderRef.current?.mimeType || 'audio/webm';
                const blob = new Blob(audioChunksRef.current, { type: actualMime });
                setAudioBlob(blob);
                stream.getTracks().forEach(track => track.stop());
            };

            mediaRecorderRef.current.start();
            setIsRecording(true);
            setRecordingTime(0);

            timerRef.current = setInterval(() => {
                setRecordingTime(prev => prev + 1);
            }, 1000);
        } catch (err) {
            alert('Microphone access denied or not available.');
        }
    };

    const stopRecording = () => {
        if (mediaRecorderRef.current && isRecording) {
            mediaRecorderRef.current.stop();
            setIsRecording(false);
            clearInterval(timerRef.current);
        }
    };

    const cancelRecording = () => {
        if (mediaRecorderRef.current && isRecording) {
            mediaRecorderRef.current.stop();
            setIsRecording(false);
            clearInterval(timerRef.current);
            setAudioBlob(null);
            setRecordingTime(0);
        }
    };

    const formatTime = (seconds) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend(e);
        }
    };

    return (
        <div className="flex flex-col bg-[#202c33] w-full px-4 py-3 relative">
            
            {/* Image / Media Preview Pop-up (Shows above input when file selected) */}
            {selectedImage && (
                <div className="absolute bottom-[100%] left-0 w-full p-4 bg-[#202c33] border-t border-[#222d34] shadow-lg animate-fade-in flex flex-col items-center z-20">
                    <div className="w-full max-w-md flex flex-col items-center bg-[#111b21] rounded-lg p-4 border border-[#222d34]">
                        <div className="flex w-full justify-between items-center mb-4">
                            <span className="text-sm font-medium text-[#e9edef] truncate px-2">{selectedImage.name}</span>
                            <button type="button" onClick={() => { setSelectedImage(null); setImagePreview(null); }} className="text-[#8696a0] hover:text-[#e9edef] p-1 bg-[#2a3942] rounded-full">
                                <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                            </button>
                        </div>
                        {imagePreview ? (
                            <img src={imagePreview} alt="Preview" className="max-h-[250px] rounded-lg object-contain bg-black/50" />
                        ) : (
                            <div className="h-32 w-full flex items-center justify-center bg-[#2a3942] rounded-lg">
                                <svg className="w-12 h-12 text-[#8696a0]" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Interactive Buttons Panel */}
            {showButtonsPanel && (
                <div className="absolute bottom-[100%] left-0 w-full bg-[#111b21] border-t border-[#222d34] p-3 flex flex-wrap gap-2 z-10 shadow-lg">
                    <div className="flex w-full justify-between items-center mb-1 px-1">
                        <span className="text-xs text-[#00a884] font-medium uppercase tracking-wider">Quick Reply Buttons</span>
                        <button type="button" onClick={() => setShowButtonsPanel(false)} className="text-[#8696a0] hover:text-[#e9edef] text-xs font-bold px-2">✕</button>
                    </div>
                    {interactiveButtons.map((btn, idx) => (
                        <input
                            key={idx}
                            type="text"
                            placeholder={`Button ${idx + 1} (optional)`}
                            maxLength={20}
                            value={btn}
                            onChange={(e) => {
                                const newBtns = [...interactiveButtons];
                                newBtns[idx] = e.target.value;
                                setInteractiveButtons(newBtns);
                            }}
                            className="flex-1 min-w-[100px] bg-[#2a3942] rounded-lg border border-[#3b4a54] px-3 py-2 text-sm text-[#e9edef] focus:border-[#00a884] focus:ring-1 focus:ring-[#00a884] outline-none placeholder-[#8696a0]"
                        />
                    ))}
                </div>
            )}

            <form onSubmit={handleSend} className="flex items-center gap-2 w-full relative z-30">
                <input type="file" ref={fileInputRef} onChange={handleImageSelect} accept="image/*,video/mp4,application/pdf,.doc,.docx,.xls,.xlsx,.csv,.txt" className="hidden" />

                {/* Left actions: Attach & Emoji */}
                <div className="flex items-center gap-1">
                    <button type="button" onClick={() => setShowButtonsPanel(!showButtonsPanel)} className={`p-2 rounded-full transition-colors ${showButtonsPanel || interactiveButtons.some(b => b.trim() !== '') ? 'text-[#00a884] bg-[#2a3942]' : 'text-[#8696a0] hover:bg-[#2a3942] hover:text-[#e9edef]'}`} title="Interactive Buttons">
                        <svg className="w-6 h-6 fill-current" viewBox="0 0 24 24"><path d="M4 6h16v4H4zm0 8h16v4H4z"/></svg>
                    </button>
                    <button type="button" className="p-2 rounded-full text-[#8696a0] hover:bg-[#2a3942] hover:text-[#e9edef] transition-colors hidden sm:block" title="Emoji">
                        <svg className="w-6 h-6 fill-current" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1010 10A10 10 0 0012 2zm0 18a8 8 0 118-8 8 8 0 01-8 8zm-3.5-9a1.5 1.5 0 111.5-1.5A1.5 1.5 0 018.5 11zm7 0a1.5 1.5 0 111.5-1.5 1.5 1.5 0 01-1.5 1.5zm-7.5 3a5.5 5.5 0 008 0z"/></svg>
                    </button>
                    <button type="button" onClick={() => fileInputRef.current?.click()} className="p-2 rounded-full text-[#8696a0] hover:bg-[#2a3942] hover:text-[#e9edef] transition-colors" title="Attach Media">
                        <svg className="w-6 h-6 fill-current" viewBox="0 0 24 24"><path d="M1.992 11.997l8.485-8.485a5.5 5.5 0 017.779 7.778l-9.9 9.9a3.5 3.5 0 01-4.95-4.95l8.485-8.485a1.5 1.5 0 012.121 2.121l-7.424 7.425-1.415-1.414 7.425-7.425a3.5 3.5 0 00-4.95-4.95l-8.485 8.485a5.5 5.5 0 007.778 7.778l9.9-9.9a7.5 7.5 0 00-10.607-10.607l-8.485 8.485z"/></svg>
                    </button>
                </div>

                {/* Main Input Pill */}
                <div className="flex-1 bg-[#2a3942] rounded-3xl overflow-hidden flex items-center min-h-[44px] shadow-sm">
                    {isRecording ? (
                        <div className="flex flex-1 items-center justify-between px-4 w-full h-[44px] animate-fade-in">
                            <div className="flex items-center gap-3 text-red-500 font-medium text-sm animate-pulse">
                                <span className="w-2.5 h-2.5 rounded-full bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)]"></span>
                                <span>{formatTime(recordingTime)}</span>
                            </div>
                            <button type="button" onClick={cancelRecording} className="text-[#8696a0] hover:text-red-400 text-sm font-medium transition-colors">
                                Cancel
                            </button>
                        </div>
                    ) : audioBlob ? (
                        <div className="flex flex-1 items-center justify-between px-4 w-full h-[44px] animate-fade-in">
                            <div className="flex items-center gap-2 text-[#00a884] font-medium text-sm">
                                <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/></svg>
                                <span>Voice note ready</span>
                            </div>
                            <button type="button" onClick={cancelRecording} className="text-[#8696a0] hover:text-red-400 text-sm font-medium transition-colors">
                                🗑
                            </button>
                        </div>
                    ) : (
                        <input
                            type="text"
                            value={content}
                            onChange={(e) => setContent(e.target.value)}
                            onKeyDown={handleKeyDown}
                            placeholder={selectedImage ? "Add a caption..." : "Type a message"}
                            className="flex-1 w-full bg-transparent px-4 py-3 text-sm text-[#e9edef] outline-none placeholder-[#8696a0]"
                        />
                    )}
                </div>

                {/* Right action: Mic OR Send Button */}
                <div>
                    {!content.trim() && !selectedImage && !audioBlob && !isRecording ? (
                        <button type="button" onClick={startRecording} className="h-10 w-10 flex items-center justify-center rounded-full bg-[#00a884] text-[#111b21] hover:bg-[#00a884]/90 shadow-md transition-transform active:scale-95 shrink-0" title="Record Voice Note">
                            <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/></svg>
                        </button>
                    ) : (
                        <button type="submit" disabled={sending} className="h-10 w-10 flex items-center justify-center rounded-full bg-[#00a884] text-[#111b21] hover:bg-[#00a884]/90 shadow-md transition-transform active:scale-95 shrink-0">
                            {sending ? (
                                <svg className="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            ) : isRecording ? (
                                <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24" onClick={(e) => { e.preventDefault(); stopRecording(); }}><path d="M9 16h6v-6h-6v6zm-4 4h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2z"/></svg>
                            ) : (
                                <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                            )}
                        </button>
                    )}
                </div>
            </form>
        </div>
    );
}
