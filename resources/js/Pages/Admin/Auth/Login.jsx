import React, { useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

/**
 * Renders the admin portal login form.
 * @param {string} [status] - Optional status message displayed above the form.
 * @return {JSX.Element} The admin login page.
 */
export default function AdminLogin({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    useEffect(() => {
        return () => {
            reset('password');
        };
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.login.store'));
    };

    return (
        <div className="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div className="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                <div className="flex justify-center mb-6">
                    <h2 className="text-2xl font-bold text-gray-900">Admin Portal</h2>
                </div>

                {status && <div className="mb-4 font-medium text-sm text-green-600">{status}</div>}

                <form onSubmit={submit}>
                    <div>
                        <label htmlFor="email" className="block font-medium text-sm text-gray-700">
                            Email
                        </label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            autoComplete="username"
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                        {errors.email && <div className="mt-2 text-sm text-red-600">{errors.email}</div>}
                    </div>

                    <div className="mt-4">
                        <label htmlFor="password" className="block font-medium text-sm text-gray-700">
                            Password
                        </label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            autoComplete="current-password"
                            onChange={(e) => setData('password', e.target.value)}
                            required
                        />
                        {errors.password && <div className="mt-2 text-sm text-red-600">{errors.password}</div>}
                    </div>

                    <div className="block mt-4">
                        <label className="flex items-center">
                            <input
                                type="checkbox"
                                name="remember"
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            />
                            <span className="ms-2 text-sm text-gray-600">Remember me</span>
                        </label>
                    </div>

                    <div className="flex items-center justify-end mt-4">
                        <button
                            type="submit"
                            className="ms-4 inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            disabled={processing}
                        >
                            Log in as Admin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
