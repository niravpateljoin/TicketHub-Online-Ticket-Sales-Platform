import React from 'react';

export default function Pagination({ page, totalPages, onPageChange }) {
    if (totalPages <= 1) return null;

    const pages = [];
    for (let i = 1; i <= totalPages; i++) pages.push(i);

    return (
        <div className="flex items-center justify-center gap-1 mt-6">
            <button
                onClick={() => onPageChange(page - 1)}
                disabled={page === 1}
                className="btn btn-secondary btn-sm"
            >
                ← Prev
            </button>

            {pages.map(p => (
                <button
                    key={p}
                    onClick={() => onPageChange(p)}
                    className={`w-9 h-9 rounded-btn text-sm font-medium transition-colors ${
                        p === page
                            ? 'bg-primary text-white'
                            : 'bg-white border border-border text-gray-600 hover:bg-surface-page'
                    }`}
                >
                    {p}
                </button>
            ))}

            <button
                onClick={() => onPageChange(page + 1)}
                disabled={page === totalPages}
                className="btn btn-secondary btn-sm"
            >
                Next →
            </button>
        </div>
    );
}
