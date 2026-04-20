import React, { useEffect, useState } from 'react';
import {
    getAdministrators,
    createAdministrator,
    updateAdministrator,
    resendAdministratorVerification,
    deleteAdministrator,
} from '../../api/adminApi';
import Modal from '../../components/common/Modal';
import ConfirmModal from '../../components/common/ConfirmModal';
import Pagination from '../../components/common/Pagination';
import { useToast } from '../../context/ToastContext';
import { useAuth } from '../../hooks/useAuth';

const INITIAL_FORM = {
    email: '',
    password: '',
};

export default function AdministratorManagementPage() {
    const { user } = useAuth();
    const { success, error: showError } = useToast();
    const [administrators, setAdministrators] = useState([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [totalAdmins, setTotalAdmins] = useState(0);
    const [search, setSearch] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editingAdmin, setEditingAdmin] = useState(null);
    const [form, setForm] = useState(INITIAL_FORM);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [resendingId, setResendingId] = useState(null);

    const fetchAdministrators = () => {
        setLoading(true);
        getAdministrators({ page, search })
            .then((data) => {
                setAdministrators(data.items ?? []);
                setTotalPages(data.totalPages ?? 1);
                setTotalAdmins(data.total ?? 0);
            })
            .catch(() => {
                setAdministrators([]);
                setTotalPages(1);
                setTotalAdmins(0);
            })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        fetchAdministrators();
    }, [page, search]);

    useEffect(() => {
        setPage(1);
    }, [search]);

    const resetForm = () => {
        setForm(INITIAL_FORM);
        setErrors({});
        setEditingAdmin(null);
        setModalOpen(false);
    };

    const openCreateModal = () => {
        setEditingAdmin(null);
        setForm(INITIAL_FORM);
        setErrors({});
        setModalOpen(true);
    };

    const openEditModal = (administrator) => {
        setEditingAdmin(administrator);
        setForm({
            email: administrator.email ?? '',
            password: '',
        });
        setErrors({});
        setModalOpen(true);
    };

    const setField = (key) => (event) => {
        const value = event.target.value;
        setForm((current) => ({ ...current, [key]: value }));
        setErrors((current) => ({ ...current, [key]: undefined }));
    };

    const validate = () => {
        const nextErrors = {};

        if (!form.email.trim()) {
            nextErrors.email = 'Email is required.';
        }

        if (!editingAdmin && !form.password) {
            nextErrors.password = 'Password is required.';
        }

        if (form.password && form.password.length < 8) {
            nextErrors.password = 'Password must be at least 8 characters.';
        }

        return nextErrors;
    };

    const handleSubmit = async (event) => {
        event.preventDefault();

        const nextErrors = validate();
        if (Object.keys(nextErrors).length > 0) {
            setErrors(nextErrors);
            return;
        }

        setSaving(true);
        try {
            if (editingAdmin) {
                await updateAdministrator(editingAdmin.id, form);
                success('Administrator updated.');
            } else {
                await createAdministrator(form);
                success('Administrator created. Verification email sent.');
            }

            resetForm();
            fetchAdministrators();
        } catch (err) {
            const responseErrors = err.response?.data?.errors;
            if (responseErrors && typeof responseErrors === 'object') {
                setErrors(responseErrors);
            } else {
                showError(err.response?.data?.message ?? 'Unable to save administrator.');
            }
        } finally {
            setSaving(false);
        }
    };

    const handleResendVerification = async (administrator) => {
        setResendingId(administrator.id);
        try {
            await resendAdministratorVerification(administrator.id);
            success('Verification email sent.');
            fetchAdministrators();
        } catch (err) {
            showError(err.response?.data?.message ?? 'Unable to resend verification email.');
        } finally {
            setResendingId(null);
        }
    };

    const handleDelete = async () => {
        if (!deleteTarget) {
            return;
        }

        try {
            await deleteAdministrator(deleteTarget.id);
            success('Administrator deleted.');
            setDeleteTarget(null);

            if (administrators.length === 1 && page > 1) {
                setPage((current) => current - 1);
                return;
            }

            fetchAdministrators();
        } catch (err) {
            showError(err.response?.data?.message ?? 'Unable to delete administrator.');
        }
    };

    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-3 mb-6">
                <div>
                    <h1 className="page-title mb-1">Administrators</h1>
                    <div className="text-sm text-gray-500">
                        Manage administrator accounts for the admin panel.
                    </div>
                </div>
                <button className="btn btn-primary" onClick={openCreateModal}>
                    + Add Administrator
                </button>
            </div>

            <div className="card mb-4">
                <div className="card-body" style={{ padding: '12px 20px' }}>
                    <div className="flex flex-wrap items-end gap-3 justify-between">
                        <div style={{ minWidth: '260px', flex: '1 1 320px' }}>
                            <label className="form-label" style={{ fontSize: '12px' }}>Search</label>
                            <input
                                className="form-input"
                                placeholder="Search by admin email…"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                            />
                        </div>
                        <div className="text-sm text-gray-500">
                            Total administrators: <span className="font-semibold text-gray-700">{totalAdmins}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div className="card">
                {loading ? (
                    <div className="card-body text-sm text-gray-400">Loading…</div>
                ) : administrators.length === 0 ? (
                    <div className="empty-state">
                        <div className="empty-state-icon">🛡️</div>
                        <div className="empty-state-title">No administrators found</div>
                        <div className="empty-state-text">Create an administrator account to grant panel access.</div>
                    </div>
                ) : (
                    <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                        <table>
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Verification</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {administrators.map((administrator) => {
                                    const isCurrentUser = administrator.id === user?.id;
                                    const disableDelete = isCurrentUser || totalAdmins <= 1;

                                    return (
                                        <tr key={administrator.id}>
                                            <td>
                                                <div className="font-medium text-sm">{administrator.email}</div>
                                                {isCurrentUser && (
                                                    <div className="text-xs text-gray-400 mt-1">Current signed-in administrator</div>
                                                )}
                                            </td>
                                            <td>
                                                <span className={`badge ${administrator.isVerified ? 'badge-approved' : 'badge-pending'}`}>
                                                    {administrator.isVerified ? 'Verified' : 'Pending'}
                                                </span>
                                            </td>
                                            <td className="text-sm text-gray-500">
                                                {administrator.createdAt
                                                    ? new Date(administrator.createdAt).toLocaleDateString('en-IN', {
                                                        day: 'numeric',
                                                        month: 'short',
                                                        year: 'numeric',
                                                    })
                                                    : '—'}
                                            </td>
                                            <td>
                                                <div className="flex flex-wrap gap-2">
                                                    <button
                                                        className="btn btn-secondary btn-sm"
                                                        onClick={() => openEditModal(administrator)}
                                                        disabled={isCurrentUser}
                                                        title={isCurrentUser ? 'Current administrator cannot be edited here.' : undefined}
                                                    >
                                                        Edit
                                                    </button>
                                                    {!administrator.isVerified && (
                                                        <button
                                                            className="btn btn-ghost btn-sm"
                                                            onClick={() => handleResendVerification(administrator)}
                                                            disabled={resendingId === administrator.id}
                                                        >
                                                            {resendingId === administrator.id ? 'Sending…' : 'Resend Email'}
                                                        </button>
                                                    )}
                                                    <button
                                                        className="btn btn-danger btn-sm"
                                                        onClick={() => setDeleteTarget(administrator)}
                                                        disabled={disableDelete}
                                                        title={
                                                            isCurrentUser
                                                                ? 'Current administrator cannot be deleted.'
                                                                : totalAdmins <= 1
                                                                    ? 'At least one administrator account must remain.'
                                                                    : undefined
                                                        }
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />

            <Modal
                isOpen={modalOpen}
                onClose={resetForm}
                title={editingAdmin ? 'Edit Administrator' : 'Add Administrator'}
            >
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="form-label">Email <span className="required">*</span></label>
                        <input
                            type="email"
                            className={`form-input${errors.email ? ' error' : ''}`}
                            placeholder="admin@example.com"
                            value={form.email}
                            onChange={setField('email')}
                        />
                        {errors.email && <div className="field-error">{errors.email}</div>}
                    </div>

                    <div>
                        <label className="form-label">
                            Password
                            {!editingAdmin && <span className="required"> *</span>}
                        </label>
                        <input
                            type="password"
                            className={`form-input${errors.password ? ' error' : ''}`}
                            placeholder={editingAdmin ? 'Leave blank to keep the current password' : 'Minimum 8 characters'}
                            value={form.password}
                            onChange={setField('password')}
                        />
                        {editingAdmin && !errors.password && (
                            <div className="field-hint">Leave blank to keep the current password.</div>
                        )}
                        {errors.password && <div className="field-error">{errors.password}</div>}
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" className="btn btn-secondary" onClick={resetForm} disabled={saving}>
                            Cancel
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={saving}>
                            {saving ? 'Saving…' : editingAdmin ? 'Save Changes' : 'Create Administrator'}
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmModal
                open={!!deleteTarget}
                title="Delete Administrator"
                message={`Are you sure you want to delete administrator "${deleteTarget?.email}"?`}
                warning="This administrator will immediately lose access to the admin panel."
                confirmLabel="Delete"
                danger
                onConfirm={handleDelete}
                onCancel={() => setDeleteTarget(null)}
            />
        </div>
    );
}
