import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { CheckCircle2, XCircle, Loader2 } from 'lucide-react';
import { verifyEmail } from '../../api/authApi';

const STATES = {
    success: {
        icon: <CheckCircle2 size={52} strokeWidth={1.6} color="#38A169" />,
        iconBg: '#F0FFF4',
        iconRing: '#C6F6D5',
        title: 'Email Verified',
        msgBg: '#F0FFF4',
        msgBorder: '#C6F6D5',
        msgColor: '#276749',
    },
    error: {
        icon: <XCircle size={52} strokeWidth={1.6} color="#E53E3E" />,
        iconBg: '#FFF5F5',
        iconRing: '#FEB2B2',
        title: 'Verification Failed',
        msgBg: '#FFF5F5',
        msgBorder: '#FEB2B2',
        msgColor: '#9B2C2C',
    },
    loading: {
        icon: <Loader2 size={52} strokeWidth={1.6} color="#2EC4A1" className="animate-spin" />,
        iconBg: '#E6F8F4',
        iconRing: '#B2F0E3',
        title: 'Verifying Email',
        msgBg: '#E6F8F4',
        msgBorder: '#B2F0E3',
        msgColor: '#1A7A63',
    },
};

export default function VerifyEmailPage() {
    const [searchParams] = useSearchParams();
    const [status, setStatus] = useState('loading');
    const [email, setEmail] = useState('');
    const [message, setMessage] = useState('Verifying your email, please wait…');

    useEffect(() => {
        const token = searchParams.get('token') ?? '';

        if (!token) {
            setStatus('error');
            setMessage('Verification token is missing.');
            return;
        }

        verifyEmail(token)
            .then((data) => {
                setEmail(data?.email ?? '');
                setStatus('success');
                setMessage(data?.message ?? 'Your administrator email has been verified. You can now log in.');
            })
            .catch((err) => {
                setStatus('error');
                setMessage(err.response?.data?.message ?? 'Verification link is invalid or expired.');
            });
    }, [searchParams]);

    const s = STATES[status] ?? STATES.loading;

    return (
        <div className="verify-email-page">
            <div className="verify-email-card">

                {/* Brand */}
                <div className="verify-email-brand">
                    <span className="verify-email-brand-dot" />
                    <span className="verify-email-brand-name">TicketHub</span>
                </div>

                {/* Icon circle */}
                <div
                    className="verify-email-icon-wrap"
                    style={{ background: s.iconBg, boxShadow: `0 0 0 8px ${s.iconRing}` }}
                >
                    {s.icon}
                </div>

                {/* Title */}
                <h1 className="verify-email-title">{s.title}</h1>

                {/* Message box */}
                <div
                    className="verify-email-msg"
                    style={{ background: s.msgBg, border: `1px solid ${s.msgBorder}`, color: s.msgColor }}
                >
                    <p>{message}</p>
                    {status === 'success' && email && (
                        <p className="verify-email-addr">{email}</p>
                    )}
                </div>

                {/* CTA */}
                {status !== 'loading' && (
                    <Link to="/login" className="btn btn-primary btn-full verify-email-btn">
                        Go to Login
                    </Link>
                )}
            </div>
        </div>
    );
}
