import React from 'react';

export default function PhonePreview({ components }) {
    if (!components || !Array.isArray(components)) {
        return null;
    }

    const header = components.find(c => c.type === 'HEADER');
    const body = components.find(c => c.type === 'BODY');
    const footer = components.find(c => c.type === 'FOOTER');
    const buttons = components.find(c => c.type === 'BUTTONS')?.buttons || [];

    // Helper to highlight variables {{1}}, {{2}} in body text
    const formatBodyText = (text) => {
        if (!text) return null;
        const parts = text.split(/(\{\{\d+\}\})/g);
        return parts.map((part, i) => {
            if (part.match(/\{\{\d+\}\}/)) {
                return <span key={i} className="bg-blue-100 text-blue-800 px-1 rounded font-mono text-xs">{part}</span>;
            }
            return part;
        });
    };

    return (
        <div className="w-full max-w-sm mx-auto bg-gray-100 rounded-3xl overflow-hidden border-[8px] border-gray-800 shadow-xl pb-6">
            {/* Phone Top Bar */}
            <div className="bg-[#075e54] text-white p-3 flex items-center space-x-3 shadow-md relative z-10">
                <div className="w-8 h-8 bg-gray-300 rounded-full flex-shrink-0 flex items-center justify-center text-gray-500">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <div>
                    <h3 className="font-semibold text-sm">Business Account</h3>
                </div>
            </div>

            {/* Chat Area */}
            <div className="bg-[#efeae2] p-4 min-h-[300px] flex flex-col justify-end relative">
                {/* WhatsApp Chat Background Pattern */}
                <div className="absolute inset-0 opacity-5" style={{ backgroundImage: 'url("https://web.whatsapp.com/img/bg-chat-tile-dark_a4be512e7195b6b733d9110b408f075d.png")' }}></div>
                
                {/* Message Bubble */}
                <div className="bg-white rounded-lg rounded-tl-none p-2 shadow-sm max-w-[90%] relative z-10 text-sm mb-2">
                    {/* Header */}
                    {header && (
                        <div className="font-bold mb-1">
                            {header.format === 'TEXT' ? header.text : <span className="italic text-gray-500">[{header.format} Media]</span>}
                        </div>
                    )}
                    
                    {/* Body */}
                    {body && (
                        <div className="text-gray-800 whitespace-pre-wrap leading-relaxed">
                            {formatBodyText(body.text)}
                        </div>
                    )}

                    {/* Footer */}
                    {footer && (
                        <div className="text-xs text-gray-500 mt-2">
                            {footer.text}
                        </div>
                    )}

                    <div className="text-[10px] text-gray-400 text-right mt-1">10:42 AM</div>
                </div>

                {/* Buttons Stack */}
                {buttons.length > 0 && (
                    <div className="flex flex-col space-y-1 relative z-10 w-full max-w-[90%]">
                        {buttons.map((btn, idx) => (
                            <div key={idx} className="bg-white rounded shadow-sm py-2 text-center text-[#00a884] text-sm font-medium border-t border-gray-100 flex items-center justify-center cursor-pointer hover:bg-gray-50">
                                {btn.type === 'URL' && <svg className="w-4 h-4 mr-1.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>}
                                {btn.type === 'PHONE_NUMBER' && <svg className="w-4 h-4 mr-1.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>}
                                {btn.type === 'QUICK_REPLY' && <svg className="w-4 h-4 mr-1.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>}
                                {btn.text}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
