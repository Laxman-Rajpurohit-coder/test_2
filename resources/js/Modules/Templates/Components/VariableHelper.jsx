import React from 'react';

export default function VariableHelper({ text, onInsert }) {
    // Count how many variables currently exist in the text to determine the next one to add
    // E.g., if {{1}} and {{2}} are present, next is {{3}}
    const matches = text.match(/\{\{(\d+)\}\}/g) || [];
    const highestVar = matches.reduce((max, match) => {
        const num = parseInt(match.replace(/[{}]/g, ''), 10);
        return Math.max(max, num);
    }, 0);

    const nextVar = highestVar + 1;

    return (
        <div className="flex items-center space-x-2 text-sm mt-2">
            <span className="text-gray-500">Variables:</span>
            <button
                type="button"
                onClick={() => onInsert(`{{${nextVar}}}`)}
                className="inline-flex items-center px-2 py-1 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
                + Add {`{{${nextVar}}}`}
            </button>
            {highestVar > 0 && (
                <span className="text-xs text-gray-400 ml-2">
                    (You must provide mapping values for these variables in the Campaign creation)
                </span>
            )}
        </div>
    );
}
