export default function AuthenticatedLayout({ children }) {
    return (
        <div className="h-screen w-screen overflow-hidden bg-[#0c1317] font-sans antialiased text-[#e9edef]">
            {children}
        </div>
    );
}
