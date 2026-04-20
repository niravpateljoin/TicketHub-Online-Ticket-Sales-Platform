import React, { useEffect, useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { createEvent } from '../../api/organizerApi';
import { getCategories } from '../../api/categoriesApi';
import { useToast } from '../../context/ToastContext';

const FALLBACK_CATEGORIES = ['Concert', 'Sports', 'Theater', 'Conference', 'Festival', 'Online'];

const INITIAL = {
    name: '',
    slug: '',
    description: '',
    category: '',
    startDate: '',
    startTime: '',
    endDate: '',
    maxAttendees: '',
    venueName: '',
    venueAddress: '',
    isOnline: false,
};

export default function EventCreatePage() {
    const navigate = useNavigate();
    const { success, error: showError } = useToast();
    const [form, setForm] = useState(INITIAL);
    const [bannerFile, setBannerFile] = useState(null);
    const [slugDirty, setSlugDirty] = useState(false);
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(false);
    const [categories, setCategories] = useState(FALLBACK_CATEGORIES);

    useEffect(() => {
        getCategories()
            .then((data) => {
                const names = (data ?? []).map((category) => category.name).filter(Boolean);
                if (names.length > 0) {
                    setCategories(names);
                }
            })
            .catch(() => {});
    }, []);

    const set = (key) => (e) => {
        const val = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
        setForm((current) => ({ ...current, [key]: val }));
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

    const validate = () => {
        const nextErrors = {};
        if (!form.name.trim()) nextErrors.name = 'Event name is required';
        if (!form.startDate) nextErrors.startDate = 'Start date is required';
        if (!form.category) nextErrors.category = 'Category is required';
        if (form.maxAttendees && !/^\d+$/.test(form.maxAttendees)) nextErrors.maxAttendees = 'Max attendees must be a whole number';
        if (!form.isOnline && !form.venueName.trim()) nextErrors.venueName = 'Venue name is required for in-person events';
        return nextErrors;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        const nextErrors = validate();
        if (Object.keys(nextErrors).length > 0) {
            setErrors(nextErrors);
            return;
        }

        setLoading(true);
        try {
            const data = await createEvent(buildEventPayload(form, bannerFile));
            success('Event created!');
            navigate(`/organizer/events/${data.id}/edit`);
        } catch (err) {
            showError(err.response?.data?.message ?? 'Failed to create event.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div>
            <nav className="breadcrumb">
                <Link to="/organizer/events" className="breadcrumb-link">My Events</Link>
                <span className="breadcrumb-sep">›</span>
                <span className="breadcrumb-current">Create Event</span>
            </nav>
            <h1 className="page-title">Create Event</h1>

            <form onSubmit={handleSubmit}>
                <div className="grid gap-6" style={{ gridTemplateColumns: '1fr 320px', alignItems: 'start' }}>
                    <div className="space-y-4">
                        <div className="card">
                            <div className="card-header">Basic Information</div>
                            <div className="card-body space-y-4">
                                <div>
                                    <label className="form-label">Event Name <span className="required">*</span></label>
                                    <input
                                        className={`form-input${errors.name ? ' error' : ''}`}
                                        placeholder="e.g. Rock Night 2026"
                                        value={form.name}
                                        onChange={handleNameChange}
                                    />
                                    {errors.name && <div className="field-error">{errors.name}</div>}
                                </div>
                                <div>
                                    <label className="form-label">Slug</label>
                                    <input
                                        className="form-input"
                                        placeholder="auto-generated-from-name"
                                        value={form.slug}
                                        onChange={handleSlugChange}
                                    />
                                    <div className="field-hint">Public URL: /events/{form.slug || 'your-event-slug'}</div>
                                </div>

                                <div>
                                    <label className="form-label">Category <span className="required">*</span></label>
                                    <select className={`form-input${errors.category ? ' error' : ''}`} value={form.category} onChange={set('category')}>
                                        <option value="">Select category…</option>
                                        {categories.map((category) => (
                                            <option key={category} value={category}>{category}</option>
                                        ))}
                                    </select>
                                    {errors.category && <div className="field-error">{errors.category}</div>}
                                </div>

                                <div>
                                    <label className="form-label">Description</label>
                                    <textarea
                                        className="form-input"
                                        rows={4}
                                        placeholder="Describe your event…"
                                        value={form.description}
                                        onChange={set('description')}
                                    />
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
                                </div>
                            </div>
                        </div>

                        <div className="card">
                            <div className="card-header">Date & Time</div>
                            <div className="card-body">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="form-label">Start Date <span className="required">*</span></label>
                                        <input
                                            type="date"
                                            className={`form-input${errors.startDate ? ' error' : ''}`}
                                            value={form.startDate}
                                            onChange={set('startDate')}
                                        />
                                        {errors.startDate && <div className="field-error">{errors.startDate}</div>}
                                    </div>
                                    <div>
                                        <label className="form-label">Start Time</label>
                                        <input type="time" className="form-input" value={form.startTime} onChange={set('startTime')} />
                                    </div>
                                    <div>
                                        <label className="form-label">End Date</label>
                                        <input type="date" className="form-input" value={form.endDate} onChange={set('endDate')} />
                                    </div>
                                    <div>
                                        <label className="form-label">Max Attendees</label>
                                        <input
                                            type="number"
                                            min="1"
                                            className={`form-input${errors.maxAttendees ? ' error' : ''}`}
                                            placeholder="e.g. 500"
                                            value={form.maxAttendees}
                                            onChange={set('maxAttendees')}
                                        />
                                        {errors.maxAttendees && <div className="field-error">{errors.maxAttendees}</div>}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="card">
                            <div className="card-header">Venue</div>
                            <div className="card-body space-y-4">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" className="w-4 h-4 accent-primary" checked={form.isOnline} onChange={set('isOnline')} />
                                    <span className="text-sm font-medium text-gray-700">This is an online event</span>
                                </label>
                                {!form.isOnline && (
                                    <>
                                        <div>
                                            <label className="form-label">Venue Name</label>
                                            <input
                                                className={`form-input${errors.venueName ? ' error' : ''}`}
                                                placeholder="e.g. Arena X"
                                                value={form.venueName}
                                                onChange={set('venueName')}
                                            />
                                            {errors.venueName && <div className="field-error">{errors.venueName}</div>}
                                        </div>
                                        <div>
                                            <label className="form-label">Venue Address</label>
                                            <input
                                                className="form-input"
                                                placeholder="e.g. 123 Main St, Mumbai"
                                                value={form.venueAddress}
                                                onChange={set('venueAddress')}
                                            />
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="card" style={{ position: 'sticky', top: '80px' }}>
                        <div className="card-header">Publish</div>
                        <div className="card-body space-y-3">
                            <button type="submit" className="btn btn-primary btn-full" disabled={loading}>
                                {loading ? 'Creating…' : 'Create Event'}
                            </button>
                            <Link to="/organizer/events" className="btn btn-secondary btn-full">
                                Cancel
                            </Link>
                            <div className="field-hint">You can add ticket tiers after creating the event.</div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    );
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
