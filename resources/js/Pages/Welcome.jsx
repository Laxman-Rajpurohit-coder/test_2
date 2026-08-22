import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';

export default function Welcome({ auth }) {
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const handleScroll = () => {
            setScrolled(window.scrollY > 20);
        };
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    return (
        <>
            <Head title="Welcome | MSG91 WhatsApp SaaS">
                <meta name="robots" content="index, follow" />
                <meta name="description" content="All-in-one multi-tenant WhatsApp Business platform with live team inbox, automated bot triggers, and analytics." />
            </Head>
            
            <div className="min-h-screen bg-slate-950 text-slate-200 font-sans selection:bg-indigo-500 selection:text-white overflow-hidden">
                {/* Abstract Background Effects */}
                <div className="fixed inset-0 z-0 overflow-hidden pointer-events-none">
                    <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] rounded-full bg-indigo-600/20 blur-[120px]" />
                    <div className="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] rounded-full bg-cyan-600/20 blur-[150px]" />
                </div>

                {/* Navbar */}
                <header className={`fixed top-0 w-full z-50 transition-all duration-300 ${scrolled ? 'bg-slate-950/80 backdrop-blur-md border-b border-slate-800 py-3' : 'bg-transparent py-5'}`}>
                    <div className="max-w-7xl mx-auto px-6 lg:px-8 flex justify-between items-center">
                        <div className="flex items-center gap-2">
                            <div className="size-8 rounded-lg bg-gradient-to-br from-indigo-500 to-cyan-400 flex items-center justify-center">
                                <svg className="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                            </div>
                            <span className="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-slate-400">MSG91 SaaS</span>
                        </div>
                        <nav className="flex gap-4 items-center">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="px-5 py-2.5 rounded-full bg-white/10 hover:bg-white/20 border border-white/10 transition-all font-medium text-sm text-white"
                                >
                                    Go to Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="px-5 py-2.5 rounded-full bg-indigo-600 hover:bg-indigo-500 transition-all font-medium text-sm text-white shadow-[0_0_20px_rgba(79,70,229,0.3)] hover:shadow-[0_0_25px_rgba(79,70,229,0.5)]"
                                    >
                                        Log In
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero Section */}
                <main className="relative z-10 pt-32 pb-16 lg:pt-48 lg:pb-32 flex flex-col items-center justify-center min-h-[90vh]">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8 text-center">
                        <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-slate-900/50 border border-indigo-500/30 text-indigo-300 text-xs font-semibold uppercase tracking-wider mb-8 shadow-inner">
                            <span className="relative flex h-2 w-2">
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                                <span className="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                            </span>
                            v2.0 Flow Builder is Live
                        </div>
                        
                        <h1 className="text-5xl md:text-7xl font-extrabold tracking-tight mb-6 leading-tight">
                            Automate WhatsApp <br className="hidden md:block" />
                            <span className="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-cyan-400 to-emerald-400">Like Never Before</span>
                        </h1>
                        
                        <p className="mt-6 text-lg md:text-xl text-slate-400 max-w-3xl mx-auto leading-relaxed">
                            Build conversational flows, manage multi-tenant workspaces, and track real-time analytics with the most powerful WhatsApp business API platform.
                        </p>
                        
                        <div className="mt-10 flex flex-col sm:flex-row gap-4 justify-center items-center">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="px-8 py-4 rounded-full bg-indigo-600 hover:bg-indigo-500 transition-all font-bold text-white shadow-[0_0_30px_rgba(79,70,229,0.4)] hover:shadow-[0_0_40px_rgba(79,70,229,0.6)] flex items-center gap-2"
                                >
                                    Enter Workspace
                                    <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </Link>
                            ) : (
                                <a
                                    href="mailto:sales@msg91.test"
                                    className="px-8 py-4 rounded-full bg-indigo-600 hover:bg-indigo-500 transition-all font-bold text-white shadow-[0_0_30px_rgba(79,70,229,0.4)] hover:shadow-[0_0_40px_rgba(79,70,229,0.6)] flex items-center gap-2"
                                >
                                    Contact Sales
                                    <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </a>
                            )}
                            
                            <a href="#features" className="px-8 py-4 rounded-full bg-slate-800/50 hover:bg-slate-800 border border-slate-700 hover:border-slate-600 transition-all font-medium text-slate-300 backdrop-blur-sm">
                                View Features
                            </a>
                        </div>
                    </div>
                </main>

                {/* Features Grid */}
                <section id="features" className="relative z-10 py-24 bg-slate-950/50 border-t border-slate-900">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-3xl font-bold text-white mb-4">Everything you need to scale</h2>
                            <p className="text-slate-400">Powerful features designed for SaaS operators and their clients.</p>
                        </div>
                        
                        <div className="grid md:grid-cols-3 gap-8">
                            {/* Feature 1 */}
                            <div className="p-8 rounded-2xl bg-gradient-to-b from-slate-900 to-slate-950 border border-slate-800 hover:border-indigo-500/50 transition-colors group">
                                <div className="size-14 rounded-xl bg-indigo-500/10 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                                    <svg className="size-7 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                                    </svg>
                                </div>
                                <h3 className="text-xl font-semibold text-white mb-3">Multi-Tenant Architecture</h3>
                                <p className="text-slate-400 leading-relaxed">
                                    Manage hundreds of clients from a single dashboard. Total data isolation and role-based access control built in from day one.
                                </p>
                            </div>

                            {/* Feature 2 */}
                            <div className="p-8 rounded-2xl bg-gradient-to-b from-slate-900 to-slate-950 border border-slate-800 hover:border-cyan-500/50 transition-colors group">
                                <div className="size-14 rounded-xl bg-cyan-500/10 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                                    <svg className="size-7 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                    </svg>
                                </div>
                                <h3 className="text-xl font-semibold text-white mb-3">Visual Flow Builder</h3>
                                <p className="text-slate-400 leading-relaxed">
                                    Drag and drop interface to build complex conversational bots. Condition nodes, API calls, and smart replies without writing code.
                                </p>
                            </div>

                            {/* Feature 3 */}
                            <div className="p-8 rounded-2xl bg-gradient-to-b from-slate-900 to-slate-950 border border-slate-800 hover:border-emerald-500/50 transition-colors group">
                                <div className="size-14 rounded-xl bg-emerald-500/10 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                                    <svg className="size-7 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                </div>
                                <h3 className="text-xl font-semibold text-white mb-3">Advanced Analytics</h3>
                                <p className="text-slate-400 leading-relaxed">
                                    Real-time dashboards tracking message volume, engagement rates, and bot resolution metrics at both tenant and platform levels.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
                
                {/* Footer */}
                <footer className="relative z-10 border-t border-slate-900 py-10 bg-slate-950">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-4">
                        <div className="flex items-center gap-2 opacity-50 hover:opacity-100 transition-opacity">
                            <svg className="size-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <span className="text-sm font-semibold text-white">MSG91 SaaS</span>
                        </div>
                        <p className="text-sm text-slate-500">
                            &copy; {new Date().getFullYear()} MSG91 SaaS. All rights reserved.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
