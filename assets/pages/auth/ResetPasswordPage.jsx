import React, { useState } from 'react';
import { Link, useSearchParams, useNavigate } from 'react-router-dom';
import { resetPassword } from '../../api/authApi';

export default function ResetPasswordPage() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const token = searchParams.get('token') ?? '';

    const [form, setForm] = useState({ password: '', confirm: '' });
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(false);
    const [serverError, setServerError] = useState('');

    const validate = () => {
        const errs = {};
        if (form.password.length < 8) errs.password = 'Password must be at least 8 characters.';
        if (form.password !== form.confirm) errs.confirm = 'Passwords do not match.';
        return errs;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        const errs = validate();
        if (Object.keys(errs).length) { setErrors(errs); return; }

        setLoading(true);
        setServerError('');
        try {
            await resetPassword(token, form.password);
            navigate('/login', { state: { flash: 'Password reset successfully. You can now log in.' } });
        } catch (err) {
            setServerError(err.response?.data?.message ?? 'Something went wrong. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    if (!token) {
        return (
            <div className="min-h-screen flex items-center justify-center" style={{ background: '#F7F8FA' }}>
                <div className="w-full" style={{ maxWidth: '480px', padding: '16px' }}>
                    <div className="card" style={{ borderRadius: '12px', boxShadow: '0 4px 24px rgba(0,0,0,0.08)', padding: '40px' }}>
                        <p className="text-center text-sm text-gray-500 mb-4">Invalid or missing reset token.</p>
                        <Link to="/forgot-password" className="btn btn-primary btn-full">Request a new link</Link>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen flex items-center justify-center" style={{ background: '#F7F8FA' }}>
            <div className="w-full" style={{ maxWidth: '480px', padding: '16px' }}>
                <div className="card" style={{ borderRadius: '12px', boxShadow: '0 4px 24px rgba(0,0,0,0.08)' }}>
                    <div style={{ padding: '40px' }}>
                        <h1 className="text-center font-bold text-gray-900 mb-2" style={{ fontSize: '22px' }}>
                            Set a new password
                        </h1>
                        <p className="text-center text-sm text-gray-500 mb-6">
                            Choose a strong password for your account.
                        </p>

                        {serverError && (
                            <div className="mb-4 p-3 rounded-btn text-sm font-medium"
                                style={{ background: '#FFF5F5', border: '1px solid #FEB2B2', color: '#E53E3E' }}>
                                {serverError}{' '}
                                {serverError.includes('expired') && (
                                    <Link to="/forgot-password" className="underline font-semibold">Request a new link</Link>
                                )}
                            </div>
                        )}

                        <form onSubmit={handleSubmit}>
                            <div className="mb-4">
                                <label className="form-label">
                                    New Password <span className="required">*</span>
                                </label>
                                <input
                                    type="password"
                                    className={`form-input${errors.password ? ' error' : ''}`}
                                    placeholder="Min. 8 characters"
                                    value={form.password}
                                    onChange={e => setForm(f => ({ ...f, password: e.target.value }))}
                                    autoFocus
                                />
                                {errors.password && <div className="field-error">{errors.password}</div>}
                            </div>

                            <div className="mb-6">
                                <label className="form-label">
                                    Confirm Password <span className="required">*</span>
                                </label>
                                <input
                                    type="password"
                                    className={`form-input${errors.confirm ? ' error' : ''}`}
                                    placeholder="Repeat your password"
                                    value={form.confirm}
                                    onChange={e => setForm(f => ({ ...f, confirm: e.target.value }))}
                                />
                                {errors.confirm && <div className="field-error">{errors.confirm}</div>}
                            </div>

                            <button
                                type="submit"
                                className="btn btn-primary btn-full"
                                disabled={loading}
                                style={{ marginBottom: '12px' }}
                            >
                                {loading ? 'Saving…' : 'Reset Password'}
                            </button>

                            <Link to="/login" className="btn btn-secondary btn-full">
                                Back to Login
                            </Link>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
}
