import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { forgotPassword } from '../../api/authApi';

export default function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const [loading, setLoading] = useState(false);
    const [submitted, setSubmitted] = useState(false);
    const [error, setError] = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!email.trim()) { setError('Email is required.'); return; }

        setLoading(true);
        setError('');
        try {
            await forgotPassword(email.trim());
            setSubmitted(true);
        } catch {
            setError('Something went wrong. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center" style={{ background: '#F7F8FA' }}>
            <div className="w-full" style={{ maxWidth: '480px', padding: '16px' }}>
                <div className="card" style={{ borderRadius: '12px', boxShadow: '0 4px 24px rgba(0,0,0,0.08)' }}>
                    <div style={{ padding: '40px' }}>
                        <h1 className="text-center font-bold text-gray-900 mb-2" style={{ fontSize: '22px' }}>
                            Forgot your password?
                        </h1>
                        <p className="text-center text-sm text-gray-500 mb-6">
                            Enter your email and we'll send you a reset link.
                        </p>

                        {submitted ? (
                            <div>
                                <div className="mb-6 p-4 rounded-btn text-sm"
                                    style={{ background: '#F0FDF4', border: '1px solid #86EFAC', color: '#166534' }}>
                                    If an account with that email exists, a password reset link has been sent. Check your inbox.
                                </div>
                                <Link to="/login" className="btn btn-primary btn-full">
                                    Back to Login
                                </Link>
                            </div>
                        ) : (
                            <form onSubmit={handleSubmit}>
                                {error && (
                                    <div className="mb-4 p-3 rounded-btn text-sm font-medium"
                                        style={{ background: '#FFF5F5', border: '1px solid #FEB2B2', color: '#E53E3E' }}>
                                        {error}
                                    </div>
                                )}

                                <div className="mb-6">
                                    <label className="form-label">
                                        Email <span className="required">*</span>
                                    </label>
                                    <input
                                        type="email"
                                        className="form-input"
                                        placeholder="you@example.com"
                                        value={email}
                                        onChange={e => setEmail(e.target.value)}
                                        autoFocus
                                    />
                                </div>

                                <button
                                    type="submit"
                                    className="btn btn-primary btn-full"
                                    disabled={loading}
                                    style={{ marginBottom: '12px' }}
                                >
                                    {loading ? 'Sending…' : 'Send Reset Link'}
                                </button>

                                <Link to="/login" className="btn btn-secondary btn-full">
                                    Back to Login
                                </Link>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
