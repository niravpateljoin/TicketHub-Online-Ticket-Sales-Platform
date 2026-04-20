import React, { useState } from 'react';
import { UserCircle, Mail, Lock, CheckCircle } from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { updateMyProfile } from '../../api/authApi';
import { useToast } from '../../context/ToastContext';
import { ROLES } from '../../utils/constants';

export default function UserProfilePage() {
    const { user, setCurrentUser } = useAuth();
    const { success, error: showError } = useToast();
    const isAdmin = user?.roles?.includes(ROLES.ADMIN);

    const [form, setForm] = useState({
        name: user?.name ?? '',
        email: user?.email ?? '',
        newEmail: user?.pendingEmail ?? '',
        password: '',
    });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    const setField = (key) => (e) => {
        setForm((prev) => ({ ...prev, [key]: e.target.value }));
        setErrors((prev) => ({ ...prev, [key]: undefined }));
    };

    const validate = () => {
        const errs = {};
        if (!form.name.trim()) errs.name = 'Name is required.';
        if (!form.email.trim()) errs.email = 'Email is required.';
        if (form.newEmail && form.newEmail === form.email)
            errs.newEmail = 'New email must differ from current email.';
        if (form.password && form.password.length < 8)
            errs.password = 'Password must be at least 8 characters.';
        return errs;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        const errs = validate();
        if (Object.keys(errs).length > 0) { setErrors(errs); return; }

        setSaving(true);
        try {
            const updated = await updateMyProfile(form);
            setCurrentUser(updated);
            setForm((prev) => ({ ...prev, password: '', newEmail: updated.pendingEmail ?? '' }));
            success(updated.message ?? 'Profile updated successfully.');
        } catch (err) {
            const responseErrors = err.response?.data?.errors;
            if (responseErrors && typeof responseErrors === 'object') {
                setErrors(responseErrors);
            } else {
                showError(err.response?.data?.message ?? 'Could not update profile.');
            }
        } finally {
            setSaving(false);
        }
    };

    const displayName = user?.name?.trim() || user?.email?.split('@')[0] || 'User';
    const initials = displayName.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]?.toUpperCase() ?? '').join('') || 'U';

    return (
        <div className="user-profile-page">
            <div className="user-profile-header">
                <div className="user-profile-avatar-lg">{initials}</div>
                <div>
                    <h1 className="user-profile-title">{displayName}</h1>
                    <p className="user-profile-subtitle">{user?.email}</p>
                </div>
            </div>

            <div className="user-profile-grid">
                {/* Personal Info */}
                <div className="card">
                    <div className="card-header">
                        <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <UserCircle size={18} strokeWidth={1.8} style={{ color: '#2EC4A1' }} />
                            Personal Information
                        </span>
                    </div>
                    <form onSubmit={handleSubmit} className="card-body" style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                        <div>
                            <label className="form-label">Full Name <span className="required">*</span></label>
                            <input
                                className={`form-input${errors.name ? ' error' : ''}`}
                                placeholder="Your full name"
                                value={form.name}
                                onChange={setField('name')}
                            />
                            {errors.name && <div className="field-error">{errors.name}</div>}
                        </div>

                        <div>
                            <label className="form-label">Email Address <span className="required">*</span></label>
                            <input
                                type="email"
                                className={`form-input${errors.email ? ' error' : ''}`}
                                placeholder="you@example.com"
                                value={form.email}
                                onChange={setField('email')}
                                readOnly={isAdmin}
                                disabled={isAdmin}
                            />
                            {errors.email && <div className="field-error">{errors.email}</div>}
                        </div>

                        {isAdmin && (
                            <div>
                                <label className="form-label">New Email Address</label>
                                <input
                                    type="email"
                                    className={`form-input${errors.newEmail ? ' error' : ''}`}
                                    placeholder="Enter a new email address"
                                    value={form.newEmail}
                                    onChange={setField('newEmail')}
                                />
                                {errors.newEmail && <div className="field-error">{errors.newEmail}</div>}
                                {!errors.newEmail && user?.pendingEmail && (
                                    <div className="field-hint">
                                        Pending verification for <strong>{user.pendingEmail}</strong>.
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="user-profile-actions">
                            <button type="submit" className="btn btn-primary" disabled={saving}>
                                {saving ? 'Saving…' : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Change Password */}
                <div className="card">
                    <div className="card-header">
                        <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <Lock size={18} strokeWidth={1.8} style={{ color: '#2EC4A1' }} />
                            Change Password
                        </span>
                    </div>
                    <form onSubmit={handleSubmit} className="card-body" style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                        <div>
                            <label className="form-label">New Password</label>
                            <input
                                type="password"
                                className={`form-input${errors.password ? ' error' : ''}`}
                                placeholder="Leave blank to keep current password"
                                value={form.password}
                                onChange={setField('password')}
                            />
                            {errors.password
                                ? <div className="field-error">{errors.password}</div>
                                : <div className="field-hint">Minimum 8 characters. Leave blank to keep the current password.</div>
                            }
                        </div>

                        <div className="user-profile-actions">
                            <button type="submit" className="btn btn-primary" disabled={saving}>
                                {saving ? 'Saving…' : 'Update Password'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Account Info */}
                <div className="card">
                    <div className="card-header">
                        <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <Mail size={18} strokeWidth={1.8} style={{ color: '#2EC4A1' }} />
                            Account Details
                        </span>
                    </div>
                    <div className="card-body" style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <div className="user-profile-detail-row">
                            <span className="user-profile-detail-label">Role</span>
                            <span className="badge badge-active">
                                {(user?.roles?.[0] ?? 'ROLE_USER').replace('ROLE_', '').charAt(0).toUpperCase() +
                                 (user?.roles?.[0] ?? 'ROLE_USER').replace('ROLE_', '').slice(1).toLowerCase()}
                            </span>
                        </div>
                        <div className="user-profile-detail-row">
                            <span className="user-profile-detail-label">Credit Balance</span>
                            <span style={{ fontWeight: 700, color: '#1A202C' }}>
                                {(user?.creditBalance ?? 0).toLocaleString()} credits
                            </span>
                        </div>
                        {user?.pendingEmail && (
                            <div className="user-profile-detail-row">
                                <span className="user-profile-detail-label">Pending Email</span>
                                <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, color: '#D69E2E' }}>
                                    <CheckCircle size={14} />
                                    {user.pendingEmail} (awaiting verification)
                                </span>
                            </div>
                        )}
                        <div className="user-profile-detail-row">
                            <span className="user-profile-detail-label">Email Verified</span>
                            <span style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, color: '#38A169' }}>
                                <CheckCircle size={14} />
                                Verified
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
