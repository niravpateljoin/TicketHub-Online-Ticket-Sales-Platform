import React from 'react';
import { Link, useLocation } from 'react-router-dom';

export default function VerificationPendingPage() {
    const location = useLocation();
    const email = location.state?.email ?? '';
    const role = location.state?.role ?? 'administrator';
    const isUserFlow = role === 'user';
    const title = isUserFlow ? 'Email Verification Pending' : 'Administrator Verification Pending';
    const body = isUserFlow
        ? 'Your account is created, but you need to verify your email before you can log in. Check your inbox and click the verification link.'
        : 'Your administrator account has been created, but you need to verify your email before you can log in. Check your inbox and click the verification link to activate admin access.';
    const warning = isUserFlow
        ? 'Until your email is verified, login will remain blocked.'
        : 'Until the email is verified, administrator login stays blocked.';

    return (
        <div className="min-h-screen flex items-center justify-center" style={{ background: '#F7F8FA' }}>
            <div className="card text-center" style={{ maxWidth: '520px', width: '100%', margin: '16px', padding: '48px 40px', borderRadius: '12px', boxShadow: '0 4px 24px rgba(0,0,0,0.08)' }}>
                <div style={{ fontSize: '56px', marginBottom: '16px' }}>📧</div>
                <h1 className="font-bold text-gray-900 mb-3" style={{ fontSize: '22px' }}>
                    {title}
                </h1>
                <p className="text-sm text-gray-600 mb-4" style={{ lineHeight: 1.7 }}>
                    {body}
                </p>
                {email && (
                    <div className="mb-4 text-sm text-gray-700">
                        Verification email sent to <span className="font-semibold">{email}</span>
                    </div>
                )}
                <div className="mb-6 p-3 rounded-btn text-sm"
                    style={{ background: '#FFFBEB', border: '1px solid #FAF089', color: '#D69E2E' }}>
                    ⚠ {warning}
                </div>
                <Link to="/login" className="btn btn-secondary btn-full">
                    Back to Login
                </Link>
            </div>
        </div>
    );
}
