import React from 'react';

export default function ConfirmModal({ open, title, message, warning, onConfirm, onCancel, confirmLabel = 'Confirm', danger = false }) {
    if (!open) return null;

    return (
        <div className="modal-overlay" onClick={onCancel}>
            <div className="modal" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <span>{title}</span>
                    <button onClick={onCancel} className="text-gray-400 hover:text-gray-600 text-2xl leading-none">×</button>
                </div>
                <div className="modal-body">
                    <p className="text-sm text-gray-700">{message}</p>
                    {warning && (
                        <div className="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-btn text-sm text-yellow-800 flex gap-2">
                            <span>⚠</span>
                            <span>{warning}</span>
                        </div>
                    )}
                </div>
                <div className="modal-footer">
                    <button onClick={onCancel} className="btn btn-secondary">
                        Cancel
                    </button>
                    <button
                        onClick={onConfirm}
                        className={`btn ${danger ? 'btn-danger' : 'btn-primary'}`}
                    >
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
