import React, { useEffect } from 'react';

export default function AuthenticatedLayout({ children }) {
    // Back-button / bfcache / SPA History cache protection
    // Force a fresh request to the server whenever back/forward buttons are pressed
    // or when restoring from the back-forward cache.
    useEffect(() => {
        localStorage.setItem('is_logged_in', 'true');
        
        // Dummy unload listener to completely disable browser bfcache (Back-Forward Cache)
        const handleUnload = () => {};
        window.addEventListener('unload', handleUnload);

        return () => {
            window.removeEventListener('unload', handleUnload);
        };
    }, []);


    return (
        <div className="h-screen w-screen overflow-hidden bg-[#0c1317] font-sans antialiased text-[#e9edef]">
            {children}
        </div>
    );
}

