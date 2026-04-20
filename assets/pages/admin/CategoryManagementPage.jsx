import React, { useEffect, useState } from 'react';
import { Tag, Plus, Pencil, Trash2 } from 'lucide-react';
import { getAdminCategories, createCategory, updateCategory, deleteCategory } from '../../api/adminApi';
import Modal from '../../components/common/Modal';
import ConfirmModal from '../../components/common/ConfirmModal';
import { useToast } from '../../context/ToastContext';

export default function CategoryManagementPage() {
    const { success, error: showError } = useToast();
    const [categories, setCategories]   = useState([]);
    const [loading, setLoading]         = useState(true);
    const [error, setError]             = useState(null);

    // Add modal state
    const [addModalOpen, setAddModalOpen] = useState(false);
    const [newName, setNewName]         = useState('');
    const [addError, setAddError]       = useState('');
    const [addSaving, setAddSaving]     = useState(false);

    // Edit modal state
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [editingId, setEditingId]     = useState(null);
    const [editName, setEditName]       = useState('');
    const [editError, setEditError]     = useState('');
    const [editSaving, setEditSaving]   = useState(false);

    // Delete confirm
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleteError, setDeleteError]   = useState('');
    const [deleteSaving, setDeleteSaving] = useState(false);

    const fetchCategories = () => {
        setLoading(true);
        getAdminCategories()
            .then((data) => setCategories(Array.isArray(data) ? data : (data?.items ?? [])))
            .catch(() => setError('Failed to load categories.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => { fetchCategories(); }, []);

    // ── Add modal ────────────────────────────────────────────────────────────
    const handleAddStart = () => {
        setAddModalOpen(true);
        setNewName('');
        setAddError('');
    };

    const handleAddCancel = () => {
        setAddModalOpen(false);
        setNewName('');
        setAddError('');
    };

    const handleAddSave = async () => {
        const name = newName.trim();
        if (!name) { setAddError('Name is required.'); return; }
        setAddSaving(true);
        setAddError('');
        try {
            await createCategory({ name });
            setAddModalOpen(false);
            setNewName('');
            success('Category created successfully.');
            fetchCategories();
        } catch (err) {
            const message = err?.response?.data?.message ?? 'Failed to create category.';
            setAddError(message);
            showError(message);
        } finally {
            setAddSaving(false);
        }
    };

    // ── Edit modal ───────────────────────────────────────────────────────────
    const handleEditStart = (cat) => {
        setEditModalOpen(true);
        setEditingId(cat.id);
        setEditName(cat.name);
        setEditError('');
    };

    const handleEditCancel = () => {
        setEditModalOpen(false);
        setEditingId(null);
        setEditName('');
        setEditError('');
    };

    const handleEditSave = async (id) => {
        const name = editName.trim();
        if (!name) { setEditError('Name is required.'); return; }
        setEditSaving(true);
        setEditError('');
        try {
            await updateCategory(id, { name });
            setEditModalOpen(false);
            setEditingId(null);
            success('Category updated successfully.');
            fetchCategories();
        } catch (err) {
            const message = err?.response?.data?.message ?? 'Failed to update category.';
            setEditError(message);
            showError(message);
        } finally {
            setEditSaving(false);
        }
    };

    // ── Delete ───────────────────────────────────────────────────────────────
    const handleDeleteConfirm = async () => {
        if (!deleteTarget) return;
        setDeleteSaving(true);
        setDeleteError('');
        try {
            await deleteCategory(deleteTarget.id);
            setDeleteTarget(null);
            success('Category deleted successfully.');
            fetchCategories();
        } catch (err) {
            const message = err?.response?.data?.message ?? 'Failed to delete category.';
            setDeleteError(message);
            showError(message);
        } finally {
            setDeleteSaving(false);
        }
    };

    return (
        <div>
            {/* Header */}
            <div className="flex flex-wrap items-center justify-between gap-3 mb-6">
                <div>
                    <h1 className="page-title mb-1">Categories</h1>
                    <p className="text-sm text-gray-500">Manage event categories available to organizers.</p>
                </div>
                <button className="btn btn-primary" onClick={handleAddStart} disabled={addModalOpen || addSaving}>
                    <Plus size={15} strokeWidth={2} className="mr-1.5" />
                    Add Category
                </button>
            </div>

            <div className="card">
                {loading ? (
                    <div className="card-body text-sm text-gray-400">Loading…</div>
                ) : error ? (
                    <div className="card-body text-sm text-red-500">{error}</div>
                ) : (
                    <div className="table-wrapper" style={{ border: 'none', borderRadius: 0 }}>
                        <table>
                            <thead>
                                <tr>
                                    <th style={{ width: '40%' }}>Name</th>
                                    <th style={{ width: '20%' }}>Events</th>
                                    <th style={{ width: '40%', textAlign: 'right' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {categories.length === 0 ? (
                                    <tr>
                                        <td colSpan={3}>
                                            <div className="empty-state">
                                                <div className="empty-state-icon-wrap">
                                                    <Tag size={32} strokeWidth={1.4} className="empty-state-svg-icon" />
                                                </div>
                                                <div className="empty-state-title">No categories yet</div>
                                                <div className="empty-state-text">Click "Add Category" to create the first one.</div>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    categories.map((cat) => (
                                        <tr key={cat.id}>
                                            <td>
                                                <div className="flex items-center gap-2">
                                                    <Tag size={14} strokeWidth={1.8} className="text-primary flex-shrink-0" />
                                                    <span className="text-sm font-medium text-gray-800">{cat.name}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span className="text-sm text-gray-500">
                                                    {cat.eventCount ?? 0} event{cat.eventCount !== 1 ? 's' : ''}
                                                </span>
                                            </td>
                                            <td style={{ textAlign: 'right' }}>
                                                <div className="flex items-center justify-end gap-2">
                                                    <button
                                                        className="btn btn-secondary btn-sm"
                                                        onClick={() => handleEditStart(cat)}
                                                    >
                                                        <Pencil size={13} strokeWidth={2} className="mr-1" />
                                                        Edit
                                                    </button>
                                                    <button
                                                        className="btn btn-danger btn-sm"
                                                        onClick={() => { setDeleteTarget(cat); setDeleteError(''); }}
                                                    >
                                                        <Trash2 size={13} strokeWidth={2} className="mr-1" />
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Modal
                isOpen={addModalOpen}
                onClose={handleAddCancel}
                title="Add Category"
            >
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        handleAddSave();
                    }}
                    className="space-y-4"
                >
                    <div>
                        <label className="form-label">Category Name <span className="required">*</span></label>
                        <input
                            className={`form-input${addError ? ' error' : ''}`}
                            placeholder="Category name…"
                            value={newName}
                            autoFocus
                            onChange={(e) => setNewName(e.target.value)}
                        />
                        {addError && <div className="field-error">{addError}</div>}
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" className="btn btn-secondary" onClick={handleAddCancel} disabled={addSaving}>
                            Cancel
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={addSaving}>
                            {addSaving ? 'Saving…' : 'Create Category'}
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal
                isOpen={editModalOpen}
                onClose={handleEditCancel}
                title="Edit Category"
            >
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (editingId) {
                            handleEditSave(editingId);
                        }
                    }}
                    className="space-y-4"
                >
                    <div>
                        <label className="form-label">Category Name <span className="required">*</span></label>
                        <input
                            className={`form-input${editError ? ' error' : ''}`}
                            placeholder="Category name…"
                            value={editName}
                            autoFocus
                            onChange={(e) => setEditName(e.target.value)}
                        />
                        {editError && <div className="field-error">{editError}</div>}
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" className="btn btn-secondary" onClick={handleEditCancel} disabled={editSaving}>
                            Cancel
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={editSaving}>
                            {editSaving ? 'Saving…' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* Delete confirmation modal */}
            <ConfirmModal
                open={deleteTarget !== null}
                title="Delete Category"
                message={`Are you sure you want to delete "${deleteTarget?.name}"?`}
                warning={
                    deleteError ||
                    (deleteTarget?.eventCount > 0
                        ? `This category has ${deleteTarget.eventCount} event(s) and cannot be deleted.`
                        : null)
                }
                confirmLabel={deleteSaving ? 'Deleting…' : 'Delete'}
                danger
                onConfirm={deleteTarget?.eventCount > 0 ? undefined : handleDeleteConfirm}
                onCancel={() => { setDeleteTarget(null); setDeleteError(''); setDeleteSaving(false); }}
            />
        </div>
    );
}
