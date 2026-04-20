import React, { createContext, useContext, useState, useCallback, useRef } from 'react';
import { CheckCircle2, XCircle, AlertTriangle, Info, X } from 'lucide-react';

const ToastContext = createContext(null);

let _toastId = 0;

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);
    const timers = useRef({});

    const dismiss = useCallback((id) => {
        setToasts(prev => prev.filter(t => t.id !== id));
        clearTimeout(timers.current[id]);
        delete timers.current[id];
    }, []);

    const show = useCallback((message, type = 'info', duration = 4000) => {
        const id = ++_toastId;
        setToasts(prev => [...prev, { id, message, type }]);
        timers.current[id] = setTimeout(() => dismiss(id), duration);
        return id;
    }, [dismiss]);

    const success = useCallback((msg, dur) => show(msg, 'success', dur), [show]);
    const error   = useCallback((msg, dur) => show(msg, 'error',   dur), [show]);
    const warning = useCallback((msg, dur) => show(msg, 'warning', dur), [show]);
    const info    = useCallback((msg, dur) => show(msg, 'info',    dur), [show]);

    return (
        <ToastContext.Provider value={{ show, success, error, warning, info, dismiss }}>
            {children}
            <ToastContainer toasts={toasts} onDismiss={dismiss} />
        </ToastContext.Provider>
    );
}

const TOAST_ICONS = {
    success: <CheckCircle2 size={18} strokeWidth={2} className="toast-icon-success" />,
    error:   <XCircle      size={18} strokeWidth={2} className="toast-icon-error"   />,
    warning: <AlertTriangle size={18} strokeWidth={2} className="toast-icon-warning" />,
    info:    <Info          size={18} strokeWidth={2} className="toast-icon-info"    />,
};

function ToastContainer({ toasts, onDismiss }) {
    if (toasts.length === 0) return null;

    return (
        <div className="toast-container">
            {toasts.map(t => (
                <div key={t.id} className={`toast ${t.type}`}>
                    <span className="flex-shrink-0">{TOAST_ICONS[t.type]}</span>
                    <span className="flex-1 text-sm">{t.message}</span>
                    <button
                        onClick={() => onDismiss(t.id)}
                        className="toast-close"
                        aria-label="Dismiss"
                    >
                        <X size={14} strokeWidth={2} />
                    </button>
                </div>
            ))}
        </div>
    );
}

export function useToast() {
    const ctx = useContext(ToastContext);
    if (!ctx) throw new Error('useToast must be used within ToastProvider');
    return ctx;
}
