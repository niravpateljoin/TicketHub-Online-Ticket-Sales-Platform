import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { registerOrganizer } from '../../api/authApi';

export default function RegisterOrganizerPage() {
    const navigate = useNavigate();
    const [form, setForm] = useState({
        email: '', password: '', confirmPassword: '',
        organizationName: '', phone: '', description: '',
    });
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(false);
    const [serverError, setServerError] = useState('');

    const validate = () => {
        const errs = {};
        if (!form.email.trim()) errs.email = 'Email is required';
        else if (!/\S+@\S+\.\S+/.test(form.email)) errs.email = 'Invalid email';
        if (!form.password || form.password.length < 8) errs.password = 'Password must be at least 8 characters';
        if (form.confirmPassword !== form.password) errs.confirmPassword = 'Passwords do not match';
        if (!form.organizationName.trim()) errs.organizationName = 'Organization name is required';
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
        if (apiErrors.organizationName) mapped.organizationName = apiErrors.organizationName;
        if (apiErrors.phone) mapped.phone = apiErrors.phone;
        if (apiErrors.description) mapped.description = apiErrors.description;

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
            await registerOrganizer({
                email: form.email,
                password: form.password,
                organizationName: form.organizationName,
                phone: form.phone,
                description: form.description,
            });
            navigate('/pending-approval');
        } catch (err) {
            const payload = err.response?.data;
            if (!applyServerValidation(payload)) {
                setServerError(payload?.message ?? 'Registration failed. Please try again.');
            }
        } finally {
            setLoading(false);
        }
    };

    const f = (key, type = 'text', placeholder = '') => ({
        type,
        className: `form-input${errors[key] ? ' error' : ''}`,
        placeholder,
        value: form[key],
        onChange: e => {
            const value = e.target.value;
            setForm(prev => ({ ...prev, [key]: value }));
            setServerError('');
            setErrors((current) => ({ ...current, [key]: undefined }));
        },
    });

    return (
        <div className="min-h-screen flex items-center justify-center py-10" style={{ background: '#F7F8FA' }}>
            <div className="w-full" style={{ maxWidth: '520px', padding: '16px' }}>
                <div className="card" style={{ borderRadius: '12px', boxShadow: '0 4px 24px rgba(0,0,0,0.08)' }}>
                    <div style={{ padding: '40px' }}>
                        
                        <h1 className="text-center font-bold text-gray-900 mb-2" style={{ fontSize: '22px' }}>
                            Register as Organizer
                        </h1>
                        <p className="text-center text-sm text-gray-500 mb-6">
                            Your account will be reviewed before activation
                        </p>

                        {serverError && (
                            <div className="mb-4 p-3 rounded-btn text-sm"
                                style={{ background: '#FFF5F5', border: '1px solid #FEB2B2', color: '#E53E3E' }}>
                                {serverError}
                            </div>
                        )}

                        <form onSubmit={handleSubmit}>
                            <div className="mb-4">
                                <label className="form-label">Email <span className="required">*</span></label>
                                <input {...f('email', 'email', 'organizer@example.com')} />
                                {errors.email && <div className="field-error">{errors.email}</div>}
                            </div>

                            <div className="grid grid-cols-2 gap-3 mb-4">
                                <div>
                                    <label className="form-label">Password <span className="required">*</span></label>
                                    <input {...f('password', 'password', 'Min 8 chars')} />
                                    {errors.password && <div className="field-error">{errors.password}</div>}
                                </div>
                                <div>
                                    <label className="form-label">Confirm Password <span className="required">*</span></label>
                                    <input {...f('confirmPassword', 'password', 'Repeat')} />
                                    {errors.confirmPassword && <div className="field-error">{errors.confirmPassword}</div>}
                                </div>
                            </div>

                            <div className="mb-4">
                                <label className="form-label">Organization Name <span className="required">*</span></label>
                                <input {...f('organizationName', 'text', 'e.g. EventCo Productions')} />
                                {errors.organizationName && <div className="field-error">{errors.organizationName}</div>}
                            </div>

                            <div className="mb-4">
                                <label className="form-label">Phone</label>
                                <input {...f('phone', 'tel', '+91 98765 43210')} />
                            </div>

                            <div className="mb-6">
                                <label className="form-label">Description</label>
                                <textarea
                                    className="form-input"
                                    rows={3}
                                    placeholder="Tell us about your organization…"
                                    value={form.description}
                                    onChange={e => {
                                        const value = e.target.value;
                                        setForm(p => ({ ...p, description: value }));
                                        setServerError('');
                                        setErrors((current) => ({ ...current, description: undefined }));
                                    }}
                                />
                                <div className="field-hint">Helps the admin approve your account faster</div>
                            </div>

                            <button type="submit" className="btn btn-primary btn-full" disabled={loading}>
                                {loading ? 'Submitting…' : 'Submit Application'}
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
