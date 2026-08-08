import React, { useState, useEffect } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function TeamIndex({ teamMembers }) {
    const { auth, flash, errors } = usePage().props;
    const currentUser = auth.user;
    const isOwner = currentUser.role === 'owner';

    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [isPasswordModalOpen, setIsPasswordModalOpen] = useState(false);
    const [generatedPassword, setGeneratedPassword] = useState('');

    const { data, setData, post, processing, reset, clearErrors } = useForm({
        name: '',
        email: '',
        role: 'member',
    });

    useEffect(() => {
        if (flash?.generated_password) {
            setGeneratedPassword(flash.generated_password);
            setIsPasswordModalOpen(true);
            setIsAddModalOpen(false);
        }
    }, [flash]);

    const submitAdd = (e) => {
        e.preventDefault();
        post(route('team.store'), {
            onSuccess: () => reset(),
        });
    };

    const handleRoleChange = (userId, newRole) => {
        if (confirm(`Change role to ${newRole}?`)) {
            router.put(route('team.role.update', userId), { role: newRole });
        }
    };

    const handleRemove = (user) => {
        if (confirm(`Are you sure you want to permanently remove ${user.name}? This action cannot be undone.`)) {
            router.delete(route('team.remove', user.id));
        }
    };

    const getRoleBadgeColor = (role) => {
        switch (role) {
            case 'owner': return 'bg-purple-100 text-purple-800 border-purple-200';
            case 'admin': return 'bg-blue-100 text-blue-800 border-blue-200';
            default: return 'bg-emerald-100 text-[#00a884] border-emerald-200';
        }
    };

    return (
        <AppLayout>
            <Head title="Team Management" />

            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h1 className="text-2xl font-extrabold tracking-tight text-gray-900">Team Management</h1>
                    <p className="text-sm text-gray-500 mt-1">Manage tenant access, assign roles, and onboard new members.</p>
                </div>
                {isOwner && (
                    <button
                        onClick={() => { clearErrors(); reset(); setIsAddModalOpen(true); }}
                        className="px-4 py-2 bg-[#00a884] text-white text-sm font-bold rounded-xl shadow-md shadow-emerald-500/20 hover:bg-[#009272] transition"
                    >
                        + Add Team Member
                    </button>
                )}
            </div>

            {/* Error Display */}
            {Object.keys(errors).length > 0 && !isAddModalOpen && (
                <div className="mb-6 p-4 bg-rose-50 border border-rose-200 rounded-xl">
                    <h3 className="text-sm font-bold text-rose-800">Please fix the following errors:</h3>
                    <ul className="list-disc list-inside text-sm text-rose-700 mt-2">
                        {Object.values(errors).map((error, index) => (
                            <li key={index}>{error}</li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm whitespace-nowrap">
                        <thead className="bg-gray-50/50 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider text-xs">
                            <tr>
                                <th className="px-6 py-4">Name</th>
                                <th className="px-6 py-4">Email</th>
                                <th className="px-6 py-4">Role</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {teamMembers.map((member) => (
                                <tr key={member.id} className="hover:bg-gray-50/50 transition">
                                    <td className="px-6 py-4 font-bold text-gray-900">{member.name}</td>
                                    <td className="px-6 py-4 text-gray-600">{member.email}</td>
                                    <td className="px-6 py-4">
                                        <span className={`px-2.5 py-1 rounded-full text-xs font-bold border capitalize ${getRoleBadgeColor(member.role)}`}>
                                            {member.role}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        {isOwner ? (
                                            <div className="flex justify-end items-center gap-3">
                                                <select
                                                    className="text-xs border border-gray-200 rounded-lg bg-gray-50 py-1 pl-2 pr-6 focus:ring-[#00a884] focus:border-[#00a884]"
                                                    value={member.role}
                                                    onChange={(e) => handleRoleChange(member.id, e.target.value)}
                                                    disabled={member.id === currentUser.id}
                                                >
                                                    <option value="owner">Owner</option>
                                                    <option value="admin">Admin</option>
                                                    <option value="member">Member</option>
                                                </select>
                                                <button
                                                    onClick={() => {
                                                        if (confirm('Are you sure you want to reset this user\'s password? A new password will be generated.')) {
                                                            router.post(route('team.reset-password', member.id));
                                                        }
                                                    }}
                                                    className="p-1.5 rounded-lg transition text-gray-400 hover:text-blue-600 hover:bg-blue-50"
                                                    title="Reset Password"
                                                >
                                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                    </svg>
                                                </button>
                                                <button
                                                    onClick={() => handleRemove(member)}
                                                    disabled={member.id === currentUser.id}
                                                    className={`p-1.5 rounded-lg transition ${
                                                        member.id === currentUser.id
                                                            ? 'text-gray-300 cursor-not-allowed'
                                                            : 'text-gray-400 hover:text-rose-600 hover:bg-rose-50'
                                                    }`}
                                                    title="Remove Member"
                                                >
                                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        ) : (
                                            <span className="text-gray-400 text-xs italic">Read Only</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {teamMembers.length === 0 && (
                                <tr>
                                    <td colSpan="4" className="px-6 py-8 text-center text-gray-500 font-medium">
                                        No team members found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Add Member Modal */}
            {isAddModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" onClick={() => setIsAddModalOpen(false)}></div>
                    <div className="relative bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                        <div className="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                            <h2 className="text-lg font-bold text-gray-900">Add Team Member</h2>
                            <button onClick={() => setIsAddModalOpen(false)} className="text-gray-400 hover:text-gray-600">✕</button>
                        </div>
                        <form onSubmit={submitAdd} className="p-6 space-y-4">
                            <div>
                                <label className="block text-sm font-semibold text-gray-700 mb-1">Name</label>
                                <input
                                    type="text"
                                    required
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none transition text-sm"
                                    placeholder="John Doe"
                                />
                                {errors.name && <p className="text-rose-500 text-xs mt-1">{errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-semibold text-gray-700 mb-1">Email Address</label>
                                <input
                                    type="email"
                                    required
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="w-full border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none transition text-sm"
                                    placeholder="john@example.com"
                                />
                                {errors.email && <p className="text-rose-500 text-xs mt-1">{errors.email}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-semibold text-gray-700 mb-1">Role</label>
                                <select
                                    value={data.role}
                                    onChange={(e) => setData('role', e.target.value)}
                                    className="w-full border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#00a884]/20 focus:border-[#00a884] outline-none transition text-sm bg-white"
                                >
                                    <option value="owner">Owner (Full Access)</option>
                                    <option value="admin">Admin (Read-Only Management)</option>
                                    <option value="member">Member (Assigned Chats Only)</option>
                                </select>
                                {errors.role && <p className="text-rose-500 text-xs mt-1">{errors.role}</p>}
                            </div>

                            <div className="pt-4 flex gap-3">
                                <button
                                    type="button"
                                    onClick={() => setIsAddModalOpen(false)}
                                    className="flex-1 px-4 py-2 border border-gray-200 text-gray-600 font-bold text-sm rounded-xl hover:bg-gray-50 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 px-4 py-2 bg-[#00a884] text-white font-bold text-sm rounded-xl shadow-md hover:bg-[#009272] transition disabled:opacity-50"
                                >
                                    {processing ? 'Creating...' : 'Create Account'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Generated Password Modal */}
            {isPasswordModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" onClick={() => setIsPasswordModalOpen(false)}></div>
                    <div className="relative bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden p-8 text-center animate-fade-in-up">
                        <div className="w-16 h-16 bg-emerald-100 text-[#00a884] rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                        </div>
                        <h2 className="text-xl font-extrabold text-gray-900 mb-2">Account Created!</h2>
                        <p className="text-sm text-gray-500 mb-6">
                            Copy this password and securely send it to the new team member. 
                            <strong className="block text-gray-900 mt-1">It will not be shown again.</strong>
                        </p>
                        
                        <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-6 flex items-center justify-between group">
                            <code className="text-lg font-mono font-bold text-gray-900 tracking-wide select-all">{generatedPassword}</code>
                            <button 
                                onClick={() => navigator.clipboard.writeText(generatedPassword)}
                                className="text-gray-400 hover:text-[#00a884] transition"
                                title="Copy to clipboard"
                            >
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                        </div>

                        <button
                            onClick={() => setIsPasswordModalOpen(false)}
                            className="w-full px-4 py-3 bg-gray-900 text-white font-bold text-sm rounded-xl hover:bg-gray-800 transition"
                        >
                            I've copied the password
                        </button>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
