import React, { useEffect, useState } from 'react';
import { CheckCircle2, AlertTriangle, XCircle, Info, X } from 'lucide-react';

const VARIANTS = {
    success: {
        icon: CheckCircle2,
        bg: '#F0FFF4', border: '#C6F6D5', color: '#276749',
    },
    error: {
        icon: XCircle,
        bg: '#FFF5F5', border: '#FEB2B2', color: '#C53030',
    },
    warning: {
        icon: AlertTriangle,
        bg: '#FFFBEB', border: '#F6E05E', color: '#B7791F',
    },
    info: {
        icon: Info,
        bg: '#EBF8FF', border: '#BEE3F8', color: '#2B6CB0',
    },
};

export default function AlertMessage({ variant = 'info', message, autoDismissMs, onDismiss }) {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        if (!autoDismissMs) return;
        const id = setTimeout(() => {
            setVisible(false);
            onDismiss?.();
        }, autoDismissMs);
        return () => clearTimeout(id);
    }, [autoDismissMs, onDismiss]);

    if (!visible || !message) return null;

    const { icon: Icon, bg, border, color } = VARIANTS[variant] ?? VARIANTS.info;

    return (
        <div
            role="alert"
            style={{
                display: 'flex', alignItems: 'flex-start', gap: 10,
                padding: '10px 14px', borderRadius: 8,
                background: bg, border: `1px solid ${border}`, color,
                fontSize: '0.875rem', lineHeight: 1.5,
            }}
        >
            <Icon size={16} strokeWidth={2} style={{ flexShrink: 0, marginTop: 1 }} />
            <span style={{ flex: 1 }}>{message}</span>
            {onDismiss && (
                <button
                    onClick={() => { setVisible(false); onDismiss(); }}
                    style={{ background: 'none', border: 'none', cursor: 'pointer', color, padding: 0, lineHeight: 1 }}
                    aria-label="Dismiss"
                >
                    <X size={14} strokeWidth={2} />
                </button>
            )}
        </div>
    );
}
