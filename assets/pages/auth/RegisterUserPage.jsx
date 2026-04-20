import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { registerUser } from '../../api/authApi';

export default function RegisterUserPage() {
    const navigate = useNavigate();
    const [form, setForm] = useState({ email: '', password: '', confirmPassword: '' });
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(false);
    const [serverError, setServerError] = useState('');

    const validate = () => {
        const errs = {};
        if (!form.email.trim()) errs.email = 'Email is required';
        else if (!/\S+@\S+\.\S+/.test(form.email)) errs.email = 'Invalid email address';
        if (!form.password) errs.password = 'Password is required';
        else if (form.password.length < 8) errs.password = 'Password must be at least 8 characters';
        if (form.confirmPassword !== form.password) errs.confirmPassword = 'Passwords do not match';
        return errs;
    };

    const applyServerValidation = (payload) => {
        const apiErrors = payload?.errors;
        if (!apiErrors || typeof apiErrors !== 'object') {
            return false;
        }

        const mapped = {};
        if (apiErrors.email) mapped.email = apiErrors.email;
        if (apiErrors.password) mapped.password = apiErrors.password;
        if (apiErrors.confirmPassword) mapped.confirmPassword = apiErrors.confirmPassword;

        if (Object.keys(mapped).length > 0) {
            setErrors((current) => ({ ...current, ...mapped }));
        }

        const firstMessage = Object.values(apiErrors).find((value) => typeof value === 'string' && value.trim() !== '');
        setServerError(firstMessage || 'Please review the highlighted fields.');

        return true;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        const errs = validate();
        if (Object.keys(errs).length) { setErrors(errs); return; }
        setLoading(true);
        setErrors({});
        setServerError('');
        try {
            await registerUser({ email: form.email, password: form.password });
            navigate('/verification-pending', { state: { email: form.email, role: 'user' } });
        } catch (err) {
            const payload = err.response?.data;
            if (!applyServerValidation(payload)) {
                setServerError(payload?.message ?? 'Registration failed. Please try again.');
            }
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
                            Create your account
                        </h1>
                        <p className="text-center text-sm text-gray-500 mb-6">Start booking tickets today</p>

                        {serverError && (
                            <div className="mb-4 p-3 rounded-btn text-sm"
                                style={{ background: '#FFF5F5', border: '1px solid #FEB2B2', color: '#E53E3E' }}>
                                {serverError}
                            </div>
                        )}

                        <form onSubmit={handleSubmit}>
                            <div className="mb-4">
                                <label className="form-label">Email <span className="required">*</span></label>
                                <input type="email" className={`form-input${errors.email ? ' error' : ''}`}
                                    placeholder="you@example.com" value={form.email}
                                    onChange={e => {
                                        const value = e.target.value;
                                        setForm(f => ({ ...f, email: value }));
                                        setServerError('');
                                        setErrors((current) => ({ ...current, email: undefined }));
                                    }} />
                                {errors.email && <div className="field-error">{errors.email}</div>}
                            </div>
                            <div className="mb-4">
                                <label className="form-label">Password <span className="required">*</span></label>
                                <input type="password" className={`form-input${errors.password ? ' error' : ''}`}
                                    placeholder="Min 8 characters" value={form.password}
                                    onChange={e => {
                                        const value = e.target.value;
                                        setForm(f => ({ ...f, password: value }));
                                        setServerError('');
                                        setErrors((current) => ({ ...current, password: undefined, confirmPassword: undefined }));
                                    }} />
                                {errors.password && <div className="field-error">{errors.password}</div>}
                            </div>
                            <div className="mb-6">
                                <label className="form-label">Confirm Password <span className="required">*</span></label>
                                <input type="password" className={`form-input${errors.confirmPassword ? ' error' : ''}`}
                                    placeholder="Repeat password" value={form.confirmPassword}
                                    onChange={e => {
                                        const value = e.target.value;
                                        setForm(f => ({ ...f, confirmPassword: value }));
                                        setServerError('');
                                        setErrors((current) => ({ ...current, confirmPassword: undefined }));
                                    }} />
                                {errors.confirmPassword && <div className="field-error">{errors.confirmPassword}</div>}
                            </div>
                            <button type="submit" className="btn btn-primary btn-full" disabled={loading}>
                                {loading ? 'Creating account…' : 'Create Account'}
                            </button>
                        </form>

                        <p className="text-center text-sm text-gray-600 mt-4">
                            Already have an account?{' '}
                            <Link to="/login" className="text-primary font-medium hover:underline">Login</Link>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
