import React, { useEffect, useState } from 'react';
import { useParams, Link, useLocation } from 'react-router-dom';
import {
    getOrganizerEvent,
    updateEvent,
    createTier,
    updateTier,
    deleteTier,
    updateEventStatus,
    cancelEvent,
} from '../../api/organizerApi';
import {
    getAdminEvent,
    adminUpdateEvent,
    adminCreateTier,
    adminUpdateTier,
    adminDeleteTier,
    adminCancelEvent,
} from '../../api/adminApi';
import { getCategories } from '../../api/categoriesApi';
import { useToast } from '../../context/ToastContext';
import { useAuth } from '../../hooks/useAuth';
import Badge from '../../components/common/Badge';
import ConfirmModal from '../../components/common/ConfirmModal';

const FALLBACK_CATEGORIES = ['Concert', 'Sports', 'Theater', 'Conference', 'Festival', 'Online'];
const EMPTY_TIER_FORM = { name: '', price: '', totalSeats: '', saleStartsAt: '', saleEndsAt: '' };

export default function EventEditPage() {
    const { id } = useParams();
    const location = useLocation();
    const { user } = useAuth();
    const { success, error: showError } = useToast();
    const isAdminMode = location.pathname.startsWith('/admin/') || user?.roles?.includes('ROLE_ADMIN');
    const eventsBasePath = isAdminMode ? '/admin/events' : '/organizer/events';

    const [event, setEvent] = useState(null);
    const [form, setForm] = useState({});
    const [bannerFile, setBannerFile] = useState(null);
    const [slugDirty, setSlugDirty] = useState(false);
    const [tiers, setTiers] = useState([]);
    const [categories, setCategories] = useState(FALLBACK_CATEGORIES);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [statusSaving, setStatusSaving] = useState(false);
    const [statusValue, setStatusValue] = useState('');
    const [tierForm, setTierForm] = useState(EMPTY_TIER_FORM);
    const [editingTierId, setEditingTierId] = useState(null);
    const [tierSaving, setTierSaving] = useState(false);
    const [deleteTierTarget, setDeleteTierTarget] = useState(null);
    const [cancelOpen, setCancelOpen] = useState(false);

    useEffect(() => {
        const fetchEvent = isAdminMode ? getAdminEvent : getOrganizerEvent;

        Promise.all([
            fetchEvent(id),
            getCategories().catch(() => FALLBACK_CATEGORIES.map((name) => ({ name }))),
        ])
            .then(([data, categoryData]) => {
                setEvent(data);
                setForm({
                    name: data.name ?? '',
                    slug: data.slug ?? '',
                    description: data.description ?? '',
                    category: data.categoryData?.name ?? data.category ?? '',
                    startDate: data.startDate?.slice(0, 10) ?? '',
                    startTime: data.startTime ?? '',
                    endDate: data.endDate?.slice(0, 10) ?? '',
                    maxAttendees: data.maxAttendees ? String(data.maxAttendees) : '',
                    venueName: data.isOnline ? '' : (data.venueName ?? ''),
                    venueAddress: data.venueAddress ?? '',
                    isOnline: data.isOnline ?? false,
                });
                setTiers(data.tiers ?? []);
                setStatusValue(data.status ?? '');
                setBannerFile(null);
                setSlugDirty(false);

                const categoryNames = (categoryData ?? []).map((category) => category.name).filter(Boolean);
                if (categoryNames.length > 0) {
                    setCategories(categoryNames);
                }
            })
            .catch(() => showError('Could not load event'))
            .finally(() => setLoading(false));
    }, [id, isAdminMode]);

    const set = (key) => (e) => {
        const value = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
        setForm((current) => ({ ...current, [key]: value }));
    };

    const handleNameChange = (e) => {
        const value = e.target.value;
        setForm((current) => ({
            ...current,
            name: value,
            slug: slugDirty ? current.slug : slugify(value),
        }));
    };

    const handleSlugChange = (e) => {
        const value = slugify(e.target.value);
        setSlugDirty(true);
        setForm((current) => ({ ...current, slug: value }));
    };

    const reloadEventTiers = async () => {
        const fetchEvent = isAdminMode ? getAdminEvent : getOrganizerEvent;
        const latest = await fetchEvent(id);
        setTiers(latest.tiers ?? []);
    };

    const handleSave = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const saveEvent = isAdminMode ? adminUpdateEvent : updateEvent;
            const updated = await saveEvent(id, buildEventPayload(form, bannerFile));
            setEvent(updated);
            setForm((current) => ({
                ...current,
                slug: updated.slug ?? current.slug,
                category: updated.categoryData?.name ?? updated.category ?? current.category,
            }));
            setBannerFile(null);
            success('Event updated!');
        } catch (err) {
            showError(err.response?.data?.message ?? 'Failed to update.');
        } finally {
            setSaving(false);
        }
    };

    const handleTierSubmit = async (e) => {
        if (e?.preventDefault) {
            e.preventDefault();
        }

        setTierSaving(true);
        try {
            const addTier = isAdminMode ? adminCreateTier : createTier;
            const saveTier = isAdminMode ? adminUpdateTier : updateTier;

            if (editingTierId) {
                await saveTier(id, editingTierId, tierForm);
                await reloadEventTiers();
                success('Tier updated!');
            } else {
                await addTier(id, tierForm);
                await reloadEventTiers();
                success('Tier added!');
            }

            setTierForm(EMPTY_TIER_FORM);
            setEditingTierId(null);
        } catch (err) {
            showError(err.response?.data?.message ?? 'Could not save tier.');
        } finally {
            setTierSaving(false);
        }
    };

    const handleDeleteTier = async () => {
        try {
            const removeTier = isAdminMode ? adminDeleteTier : deleteTier;
            await removeTier(id, deleteTierTarget.id);
            await reloadEventTiers();
            setDeleteTierTarget(null);
            success('Tier removed.');
        } catch (err) {
            showError(err.response?.data?.message ?? 'Could not delete tier.');
        }
    };

    const handleStatusSave = async () => {
        if (isAdminMode) {
            return;
        }

        if (!statusValue || statusValue === event?.status) {
            return;
        }

        setStatusSaving(true);
        try {
            const updated = await updateEventStatus(id, statusValue);
            setEvent(updated);
            setStatusValue(updated.status);
            success('Event status updated.');
        } catch (err) {
            setStatusValue(event?.status ?? '');
            showError(err.response?.data?.message ?? 'Could not update event status.');
        } finally {
            setStatusSaving(false);
        }
    };

    const handleCancelEvent = async () => {
        try {
            const cancel = isAdminMode ? adminCancelEvent : cancelEvent;
            const response = await cancel(id);
            if (response?.event) {
                setEvent((current) => ({ ...(current ?? {}), ...response.event }));
                setStatusValue(response.event.status ?? 'cancelled');
            }
            setCancelOpen(false);
            success('Event cancelled.');
        } catch (err) {
            showError(err.response?.data?.message ?? 'Could not cancel event.');
        }
    };

    const startEditingTier = (tier) => {
        setEditingTierId(tier.id);
        setTierForm({
            name: tier.name ?? '',
            price: String(tier.basePrice ?? tier.price ?? ''),
            totalSeats: String(tier.totalSeats ?? ''),
            saleStartsAt: formatDateTimeLocal(tier.saleStartsAt),
            saleEndsAt: formatDateTimeLocal(tier.saleEndsAt),
        });
    };

    if (loading) {
        return <div className="card card-body text-sm text-gray-400">Loading…</div>;
    }

    return (
        <div>
            <nav className="breadcrumb">
                <Link to={eventsBasePath} className="breadcrumb-link">My Events</Link>
                <span className="breadcrumb-sep">›</span>
                <span className="breadcrumb-current">Edit: {event?.name}</span>
            </nav>
            <div className="flex items-center gap-3 mb-6">
                <h1 className="page-title mb-0">Edit: {event?.name}</h1>
                {event?.status && <Badge status={event.status} />}
            </div>

            <form onSubmit={handleSave}>
                <div className="grid gap-6" style={{ gridTemplateColumns: '1fr 320px', alignItems: 'start' }}>
                    <div className="space-y-4">
                        <div className="card">
                            <div className="card-header">Basic Information</div>
                            <div className="card-body space-y-4">
                                <div>
                                    <label className="form-label">Event Name <span className="required">*</span></label>
                                    <input className="form-input" value={form.name ?? ''} onChange={handleNameChange} />
                                </div>
                                <div>
                                    <label className="form-label">Slug</label>
                                    <input className="form-input" value={form.slug ?? ''} onChange={handleSlugChange} />
                                    <div className="field-hint">Public URL: /events/{form.slug || 'your-event-slug'}</div>
                                </div>
                                <div>
                                    <label className="form-label">Category</label>
                                    <select className="form-input" value={form.category ?? ''} onChange={set('category')}>
                                        <option value="">Select…</option>
                                        {categories.map((category) => (
                                            <option key={category} value={category}>{category}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="form-label">Description</label>
                                    <textarea className="form-input" rows={4} value={form.description ?? ''} onChange={set('description')} />
                                </div>
                                <div>
                                    <label className="form-label">Banner Image</label>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        className="form-input"
                                        onChange={(e) => setBannerFile(e.target.files?.[0] ?? null)}
                                    />
                                    <div className="field-hint">Upload JPG, PNG, WEBP, or GIF (max 5 MB).</div>
                                    {bannerFile && (
                                        <div className="field-hint">Selected: {bannerFile.name}</div>
                                    )}
                                    {!bannerFile && event?.bannerUrl && (
                                        <div className="mt-2">
                                            <img
                                                src={event.bannerUrl}
                                                alt="Event banner"
                                                style={{ width: '100%', maxHeight: '180px', objectFit: 'cover', borderRadius: '8px', border: '1px solid #E2E8F0' }}
                                            />
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="card">
                            <div className="card-header">Date & Time</div>
                            <div className="card-body grid grid-cols-2 gap-4">
                                <div>
                                    <label className="form-label">Start Date</label>
                                    <input type="date" className="form-input" value={form.startDate ?? ''} onChange={set('startDate')} />
                                </div>
                                <div>
                                    <label className="form-label">Start Time</label>
                                    <input type="time" className="form-input" value={form.startTime ?? ''} onChange={set('startTime')} />
                                </div>
                                <div>
                                    <label className="form-label">End Date</label>
                                    <input type="date" className="form-input" value={form.endDate ?? ''} onChange={set('endDate')} />
                                </div>
                                <div>
                                    <label className="form-label">Max Attendees</label>
                                    <input
                                        type="number"
                                        min="1"
                                        className="form-input"
                                        placeholder="e.g. 500"
                                        value={form.maxAttendees ?? ''}
                                        onChange={set('maxAttendees')}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="card">
                            <div className="card-header">Venue</div>
                            <div className="card-body space-y-4">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" className="w-4 h-4 accent-primary" checked={form.isOnline ?? false} onChange={set('isOnline')} />
                                    <span className="text-sm font-medium text-gray-700">Online event</span>
                                </label>
                                {!form.isOnline && (
                                    <>
                                        <div>
                                            <label className="form-label">Venue Name</label>
                                            <input className="form-input" value={form.venueName ?? ''} onChange={set('venueName')} />
                                        </div>
                                        <div>
                                            <label className="form-label">Venue Address</label>
                                            <input className="form-input" value={form.venueAddress ?? ''} onChange={set('venueAddress')} />
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>

                        <div className="card">
                            <div className="card-header">Ticket Tiers</div>
                            {tiers.length > 0 && (
                                <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Tier Name</th>
                                                <th>Price (cr)</th>
                                                <th>Total Seats</th>
                                                <th>Available</th>
                                                <th>Sales Window</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {tiers.map((tier) => (
                                                <tr key={tier.id}>
                                                    <td className="font-medium">{tier.name}</td>
                                                    <td>{tier.finalPrice ?? tier.price}</td>
                                                    <td>{tier.totalSeats}</td>
                                                    <td>{tier.availableSeats}</td>
                                                    <td className="text-xs text-gray-500">{renderSaleWindow(tier)}</td>
                                                    <td>
                                                        <div className="flex gap-2">
                                                            <button type="button" className="btn btn-secondary btn-sm" onClick={() => startEditingTier(tier)}>
                                                                Edit
                                                            </button>
                                                            <button type="button" className="btn btn-danger btn-sm" onClick={() => setDeleteTierTarget(tier)}>
                                                                ✕
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <div className="card-body" style={{ borderTop: tiers.length > 0 ? '1px solid #E2E8F0' : 'none' }}>
                                <div className="font-medium text-gray-700 text-sm mb-3">
                                    {editingTierId ? 'Edit Tier' : 'Add New Tier'}
                                </div>
                                <div className="grid grid-cols-2 gap-3 mb-3">
                                    <div>
                                        <label className="form-label" style={{ fontSize: '12px' }}>Tier Name</label>
                                        <input
                                            className="form-input"
                                            placeholder="e.g. VIP"
                                            value={tierForm.name}
                                            onChange={(e) => setTierForm((current) => ({ ...current, name: e.target.value }))}
                                        />
                                    </div>
                                    <div>
                                        <label className="form-label" style={{ fontSize: '12px' }}>Base Price (credits)</label>
                                        <input
                                            type="number"
                                            className="form-input"
                                            placeholder="200"
                                            value={tierForm.price}
                                            onChange={(e) => setTierForm((current) => ({ ...current, price: e.target.value }))}
                                        />
                                    </div>
                                    <div>
                                        <label className="form-label" style={{ fontSize: '12px' }}>Total Seats</label>
                                        <input
                                            type="number"
                                            className="form-input"
                                            placeholder="100"
                                            value={tierForm.totalSeats}
                                            onChange={(e) => setTierForm((current) => ({ ...current, totalSeats: e.target.value }))}
                                        />
                                    </div>
                                    <div>
                                        <label className="form-label" style={{ fontSize: '12px' }}>Sale Starts At</label>
                                        <input
                                            type="datetime-local"
                                            className="form-input"
                                            value={tierForm.saleStartsAt}
                                            onChange={(e) => setTierForm((current) => ({ ...current, saleStartsAt: e.target.value }))}
                                        />
                                    </div>
                                    <div>
                                        <label className="form-label" style={{ fontSize: '12px' }}>Sale Ends At</label>
                                        <input
                                            type="datetime-local"
                                            className="form-input"
                                            value={tierForm.saleEndsAt}
                                            onChange={(e) => setTierForm((current) => ({ ...current, saleEndsAt: e.target.value }))}
                                        />
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    <button type="button" className="btn btn-secondary btn-sm" onClick={handleTierSubmit} disabled={tierSaving}>
                                        {tierSaving ? 'Saving…' : editingTierId ? 'Save Tier' : '+ Add Tier'}
                                    </button>
                                    {editingTierId && (
                                        <button
                                            type="button"
                                            className="btn btn-ghost btn-sm"
                                            onClick={() => { setEditingTierId(null); setTierForm(EMPTY_TIER_FORM); }}
                                        >
                                            Cancel Edit
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="card" style={{ position: 'sticky', top: '80px' }}>
                        <div className="card-header">Manage Event</div>
                        <div className="card-body space-y-3">
                            <button type="submit" className="btn btn-primary btn-full" disabled={saving}>
                                {saving ? 'Saving…' : 'Save Changes'}
                            </button>
                            {!isAdminMode && (
                                <>
                                    <div>
                                        <label className="form-label">Status</label>
                                        <select className="form-input" value={statusValue ?? ''} onChange={(e) => setStatusValue(e.target.value)}>
                                            <option value={event?.status ?? ''}>{event?.status ?? 'Current status'}</option>
                                            {event?.status === 'active' && <option value="postponed">postponed</option>}
                                            {event?.status === 'active' && <option value="sold_out">sold_out</option>}
                                        </select>
                                    </div>
                                    <button
                                        type="button"
                                        className="btn btn-secondary btn-full"
                                        disabled={statusSaving || !statusValue || statusValue === event?.status || event?.status !== 'active'}
                                        onClick={handleStatusSave}
                                    >
                                        {statusSaving ? 'Updating Status…' : 'Update Status'}
                                    </button>
                                    <Link to={`/organizer/events/${id}/bookings`} className="btn btn-secondary btn-full">
                                        View Bookings
                                    </Link>
                                    <Link to={`/organizer/events/${id}/revenue`} className="btn btn-secondary btn-full">
                                        View Revenue
                                    </Link>
                                </>
                            )}
                            {event?.status !== 'cancelled' && (
                                <button type="button" className="btn btn-danger btn-full" onClick={() => setCancelOpen(true)}>
                                    Cancel Event
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            </form>

            <ConfirmModal
                open={!!deleteTierTarget}
                title="Remove Tier"
                message={`Remove tier "${deleteTierTarget?.name}"?`}
                warning="Confirmed ticket sales on this tier will block deletion."
                confirmLabel="Remove"
                danger
                onConfirm={handleDeleteTier}
                onCancel={() => setDeleteTierTarget(null)}
            />

            <ConfirmModal
                open={cancelOpen}
                title="Cancel Event"
                message={`Cancel "${event?.name}"?`}
                warning="Confirmed bookings will be refunded and pending reservations will be released."
                confirmLabel="Cancel Event"
                danger
                onConfirm={handleCancelEvent}
                onCancel={() => setCancelOpen(false)}
            />
        </div>
    );
}

function formatDateTimeLocal(value) {
    if (!value) {
        return '';
    }

    return value.slice(0, 16);
}

function buildEventPayload(form, bannerFile) {
    const payload = new FormData();
    payload.append('name', form.name ?? '');
    payload.append('slug', form.slug ?? '');
    payload.append('description', form.description ?? '');
    payload.append('category', form.category ?? '');
    payload.append('startDate', form.startDate ?? '');
    payload.append('startTime', form.startTime ?? '');
    payload.append('endDate', form.endDate ?? '');
    payload.append('maxAttendees', form.maxAttendees ?? '');
    payload.append('venueName', form.venueName ?? '');
    payload.append('venueAddress', form.venueAddress ?? '');
    payload.append('isOnline', form.isOnline ? '1' : '0');

    if (bannerFile instanceof File) {
        payload.append('bannerImage', bannerFile);
    }

    return payload;
}

function slugify(value) {
    return String(value ?? '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function renderSaleWindow(tier) {
    const start = tier.saleStartsAt
        ? new Date(tier.saleStartsAt).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
        : null;
    const end = tier.saleEndsAt
        ? new Date(tier.saleEndsAt).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
        : null;

    if (!start && !end) {
        return 'Open';
    }

    return [start ? `From ${start}` : null, end ? `Until ${end}` : null].filter(Boolean).join(' · ');
}
