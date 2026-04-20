import React, { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { login as apiLogin } from '../../api/authApi';
import { ROLES } from '../../utils/constants';

export default function LoginPage() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const flashMessage = location.state?.flash ?? '';

    const [form, setForm] = useState({ email: '', password: '', remember: false });
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(false);
    const [serverError, setServerError] = useState('');

    const validate = () => {
        const errs = {};
        if (!form.email.trim()) errs.email = 'Email is required';
        if (!form.password) errs.password = 'Password is required';
        return errs;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        const errs = validate();
        if (Object.keys(errs).length) { setErrors(errs); return; }

        setLoading(true);
        setServerError('');
        try {
            const data = await apiLogin(form.email, form.password);
            login(data.token, data.user);

            // Redirect based on role from token
            const payload = JSON.parse(atob(data.token.split('.')[1]));
            const roles = payload.roles ?? [];
            if (roles.includes(ROLES.ADMIN)) navigate('/admin/dashboard');
            else if (roles.includes(ROLES.ORGANIZER)) {
                if (payload.approvalStatus === 'approved') navigate('/organizer/dashboard');
                else navigate('/organizer/pending');
            } else navigate('/user/dashboard');
        } catch (err) {
            const message = err.response?.data?.message ?? 'Invalid credentials. Please try again.';

            if (message === 'Your administrator account is pending email verification.') {
                navigate('/verification-pending', { state: { email: form.email, role: 'administrator' } });
                return;
            }
            if (message === 'Your account is pending email verification. Please check your inbox.') {
                navigate('/verification-pending', { state: { email: form.email, role: 'user' } });
                return;
            }

            setServerError(message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center" style={{ background: '#F7F8FA' }}>
            <div className="w-full" style={{ maxWidth: '480px', padding: '16px' }}>
                <div className="card" style={{ borderRadius: '12px', boxShadow: '0 4px 24px rgba(0,0,0,0.08)' }}>
                    <div style={{ padding: '40px' }}>
                        

                        {/* Title */}
                        <h1 className="text-center font-bold text-gray-900 mb-6" style={{ fontSize: '22px' }}>
                            Login to your account
                        </h1>

                        {/* Flash success (e.g. after password reset) */}
                        {flashMessage && (
                            <div className="mb-4 p-3 rounded-btn text-sm font-medium"
                                style={{ background: '#F0FDF4', border: '1px solid #86EFAC', color: '#166534' }}>
                                {flashMessage}
                            </div>
                        )}

                        {/* Server error */}
                        {serverError && (
                            <div className="mb-4 p-3 rounded-btn text-sm font-medium"
                                style={{ background: '#FFF5F5', border: '1px solid #FEB2B2', color: '#E53E3E' }}>
                                {serverError}
                            </div>
                        )}

                        <form onSubmit={handleSubmit}>
                            {/* Email */}
                            <div className="mb-4">
                                <label className="form-label">
                                    Email <span className="required">*</span>
                                </label>
                                <input
                                    type="email"
                                    className={`form-input${errors.email ? ' error' : ''}`}
                                    placeholder="you@example.com"
                                    value={form.email}
                                    onChange={e => setForm(f => ({ ...f, email: e.target.value }))}
                                    autoFocus
                                />
                                {errors.email && <div className="field-error">{errors.email}</div>}
                            </div>

                            {/* Password */}
                            <div className="mb-4">
                                <label className="form-label">
                                    Password <span className="required">*</span>
                                </label>
                                <input
                                    type="password"
                                    className={`form-input${errors.password ? ' error' : ''}`}
                                    placeholder="••••••••"
                                    value={form.password}
                                    onChange={e => setForm(f => ({ ...f, password: e.target.value }))}
                                />
                                {errors.password && <div className="field-error">{errors.password}</div>}
                            </div>

                            {/* Remember me + Forgot password */}
                            <div className="flex items-center justify-between mb-6">
                                <div className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="remember"
                                        className="w-4 h-4 accent-primary cursor-pointer"
                                        checked={form.remember}
                                        onChange={e => setForm(f => ({ ...f, remember: e.target.checked }))}
                                    />
                                    <label htmlFor="remember" className="text-sm text-gray-600 cursor-pointer">
                                        Remember me
                                    </label>
                                </div>
                                <Link to="/forgot-password" className="text-sm text-primary font-medium hover:underline">
                                    Forgot password?
                                </Link>
                            </div>

                            {/* Buttons */}
                            <button
                                type="submit"
                                className="btn btn-primary btn-full"
                                disabled={loading}
                                style={{ marginBottom: '10px' }}
                            >
                                {loading ? 'Logging in…' : 'Login'}
                            </button>
                        </form>

                        {/* Divider */}
                        <div className="relative my-4">
                            <div className="absolute inset-0 flex items-center">
                                <div className="w-full border-t border-border" />
                            </div>
                            <div className="relative flex justify-center text-xs text-gray-400 bg-white px-2">
                                New here? Create your account
                            </div>
                        </div>

                        <Link to="/register" className="btn btn-secondary btn-full">
                            Create an account
                        </Link>

                        <p className="text-center text-xs text-gray-500 mt-4">
                            Are you an organizer?{' '}
                            <Link to="/register/organizer" className="text-primary font-medium hover:underline">
                                Register as organizer
                            </Link>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
