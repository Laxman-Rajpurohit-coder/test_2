import React from 'react';

export default function StatusBadge({ status }) {
    const getStatusColors = (status) => {
        switch (status?.toLowerCase()) {
            case 'approved':
                return 'bg-emerald-100 text-emerald-900 border-emerald-300';
            case 'rejected':
                return 'bg-rose-100 text-rose-900 border-rose-300';
            case 'pending':
            default:
                return 'bg-amber-100 text-amber-950 border-amber-300';
        }
    };

    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${getStatusColors(status)} capitalize`}>
            {status || 'pending'}
        </span>
    );
}
