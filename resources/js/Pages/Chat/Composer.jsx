import { useState, useRef } from 'react';

export default function Composer({ conversation, onSent }) {
    const [content, setContent] = useState('');
    const [sending, setSending] = useState(false);
    const [selectedImage, setSelectedImage] = useState(null);
    const [imagePreview, setImagePreview] = useState(null);

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

        // 2. Send Photo / Image
        if (selectedImage) {
            sendMediaFile(selectedImage, 'image', selectedImage.name, content);
            return;
        }

        // 3. Send Text Message
        if (!content.trim()) return;

        setSending(true);
        window.axios.post(`/api/conversations/${conversation.id}/messages`, {
            content: content,
            type: 'text'
        }).then((res) => {
            setContent('');
            onSent(res.data?.message);
        }).catch((err) => {
            const msg = err.response?.data?.error || 'Failed to send message.';
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
            const msg = err.response?.data?.error || 'Failed to send media file.';
            alert(msg);
        }).finally(() => {
            setSending(false);
        });
    };

    const handleImageSelect = (e) => {
        const file = e.target.files[0];
        if (file) {
            setSelectedImage(file);
            setImagePreview(URL.createObjectURL(file));
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
        <div className="flex flex-col bg-[#202c33] w-full">
            {/* Image Preview Overlay Bar */}
            {imagePreview && (
                <div className="flex items-center justify-between px-4 py-2 bg-[#111b21] border-b border-[#222d34]">
                    <div className="flex items-center gap-3">
                        <img src={imagePreview} alt="Preview" className="h-12 w-12 object-cover rounded-md border border-[#222d34]" />
                        <span className="text-xs text-[#e9edef] truncate max-w-[200px]">{selectedImage?.name}</span>
                    </div>
                    <button 
                        type="button" 
                        onClick={() => { setSelectedImage(null); setImagePreview(null); }}
                        className="text-[#8696a0] hover:text-[#e9edef] text-sm font-bold p-1"
                    >
                        ✕
                    </button>
                </div>
            )}

            {/* Audio Recording Active Bar */}
            {isRecording ? (
                <div className="flex h-[62px] items-center justify-between bg-[#202c33] px-4 w-full">
                    <div className="flex items-center gap-3 text-red-500 font-medium text-sm animate-pulse">
                        <span className="w-3 h-3 rounded-full bg-red-500"></span>
                        <span>Recording Voice Note... ({formatTime(recordingTime)})</span>
                    </div>
                    <div className="flex items-center gap-3">
                        <button type="button" onClick={cancelRecording} className="text-[#8696a0] hover:text-[#e9edef] text-xs font-semibold px-3 py-1.5 rounded bg-[#2a3942]">
                            Cancel
                        </button>
                        <button type="button" onClick={stopRecording} className="bg-[#00a884] text-[#111b21] text-xs font-bold px-3 py-1.5 rounded hover:bg-[#00a884]/90">
                            Done
                        </button>
                    </div>
                </div>
            ) : (
                /* Standard Composer Footer */
                <form onSubmit={handleSend} className="flex h-[62px] items-center gap-3 bg-[#202c33] px-4 w-full">
                    <input 
                        type="file" 
                        id="chat-media-file"
                        name="media_file"
                        ref={fileInputRef} 
                        onChange={handleImageSelect} 
                        accept="image/*" 
                        className="hidden" 
                    />

                    {/* Emoji Action Button */}
                    <button type="button" title="Emoji" className="text-[#8696a0] hover:text-[#e9edef] transition-colors p-1">
                        <svg className="w-6 h-6 fill-current" viewBox="0 0 24 24">
                            <path d="M12 2a10 10 0 1010 10A10 10 0 0012 2zm0 18a8 8 0 118-8 8 8 0 01-8 8zm-3.5-9a1.5 1.5 0 111.5-1.5A1.5 1.5 0 018.5 11zm7 0a1.5 1.5 0 111.5-1.5 1.5 1.5 0 01-1.5 1.5zm-7.5 3a5.5 5.5 0 008 0z"/>
                        </svg>
                    </button>

                    {/* Attachment Button (Photo Picker) */}
                    <button 
                        type="button" 
                        title="Attach Photo" 
                        onClick={() => fileInputRef.current?.click()}
                        className="text-[#8696a0] hover:text-[#e9edef] transition-colors p-1"
                    >
                        <svg className="w-6 h-6 fill-current" viewBox="0 0 24 24">
                            <path d="M1.992 11.997l8.485-8.485a5.5 5.5 0 017.779 7.778l-9.9 9.9a3.5 3.5 0 01-4.95-4.95l8.485-8.485a1.5 1.5 0 012.121 2.121l-7.424 7.425-1.415-1.414 7.425-7.425a3.5 3.5 0 00-4.95-4.95l-8.485 8.485a5.5 5.5 0 007.778 7.778l9.9-9.9a7.5 7.5 0 00-10.607-10.607l-8.485 8.485z"/>
                        </svg>
                    </button>

                    {/* Input Box */}
                    <input
                        type="text"
                        id="chat-message-input"
                        name="message_content"
                        autoComplete="off"
                        value={content}
                        onChange={(e) => setContent(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder={selectedImage ? "Add a caption..." : audioBlob ? "Voice note ready to send" : "Type a message"}
                        className="flex-1 rounded-lg bg-[#2a3942] px-4 py-2.5 text-sm text-[#e9edef] outline-none placeholder-[#8696a0] focus:ring-1 focus:ring-[#00a884]"
                    />

                    {/* Voice Note Microphone Button */}
                    {!content.trim() && !selectedImage && !audioBlob ? (
                        <button
                            type="button"
                            title="Record Voice Note"
                            onClick={startRecording}
                            className="rounded-full p-2.5 text-[#8696a0] hover:text-[#e9edef] hover:bg-[#2a3942] transition-colors"
                        >
                            <svg className="w-6 h-6 fill-current" viewBox="0 0 24 24">
                                <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/>
                            </svg>
                        </button>
                    ) : (
                        /* WhatsApp Green Send Button */
                        <button
                            type="submit"
                            disabled={sending}
                            className="rounded-full bg-[#00a884] p-2.5 text-[#111b21] hover:bg-[#00a884]/90 shadow-md transition-all flex items-center justify-center"
                        >
                            {sending ? (
                                <svg className="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            ) : (
                                <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                </svg>
                            )}
                        </button>
                    )}
                </form>
            )}
        </div>
    );
}
