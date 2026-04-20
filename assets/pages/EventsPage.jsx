import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import {
    CalendarDays,
    MapPin,
    Search,
    SlidersHorizontal,
    Ticket,
    UserRound,
    Wallet,
    X,
} from 'lucide-react';
import { getEvents, getEventFilterOptions } from '../api/eventsApi';
import Badge from '../components/common/Badge';
import Pagination from '../components/common/Pagination';

const EMPTY_FILTERS = {
    search: '',
    category: '',
    dateFrom: '',
    dateTo: '',
    locationType: '',
    priceMin: '',
    priceMax: '',
    organizer: '',
    availableOnly: true,
};

export default function EventsPage() {
    const [searchParams, setSearchParams] = useSearchParams();
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [total, setTotal] = useState(0);
    const [totalPages, setTotalPages] = useState(1);
    const [filterOptions, setFilterOptions] = useState({ categories: [], organizers: [] });
    const [optionsLoaded, setOptionsLoaded] = useState(false);
    const [form, setForm] = useState(readFilters(searchParams));
    const [filtersVisible, setFiltersVisible] = useState(hasActiveFilters(searchParams));

    const page = Math.max(1, Number(searchParams.get('page') ?? 1));
    const activeFilterTags = getActiveFilterTags(searchParams, filterOptions);
    const publicCategoryCount = filterOptions.categories.length;
    const publicOrganizerCount = filterOptions.organizers.length;

    useEffect(() => {
        setForm(readFilters(searchParams));
        setFiltersVisible(hasActiveFilters(searchParams));
    }, [searchParams]);

    useEffect(() => {
        setOptionsLoaded(false);

        getEventFilterOptions()
            .then((data) => {
                setFilterOptions({
                    categories: data?.categories ?? [],
                    organizers: data?.organizers ?? [],
                });
            })
            .catch(() => {
                setFilterOptions({ categories: [], organizers: [] });
            })
            .finally(() => {
                setOptionsLoaded(true);
            });
    }, []);

    useEffect(() => {
        if (!optionsLoaded) {
            return;
        }

        const sanitized = sanitizeSearchParams(searchParams, filterOptions);
        if (sanitized.toString() !== searchParams.toString()) {
            setSearchParams(sanitized, { replace: true });
        }
    }, [filterOptions, optionsLoaded, searchParams, setSearchParams]);

    useEffect(() => {
        setLoading(true);

        getEvents(Object.fromEntries(searchParams.entries()))
            .then((data) => {
                setEvents(data.items ?? []);
                setTotal(data.total ?? 0);
                setTotalPages(data.totalPages ?? 1);
            })
            .catch(() => {
                setEvents([]);
                setTotal(0);
                setTotalPages(1);
            })
            .finally(() => setLoading(false));
    }, [searchParams]);

    const applyFilters = (e) => {
        e.preventDefault();
        setSearchParams(buildSearchParams(form));
    };

    const resetFilters = () => {
        setForm(EMPTY_FILTERS);
        setSearchParams(new URLSearchParams());
    };

    const handlePageChange = (nextPage) => {
        const nextParams = buildSearchParams(form);
        if (nextPage > 1) {
            nextParams.set('page', String(nextPage));
        }
        setSearchParams(nextParams);
    };

    const clearSingleFilter = (key) => {
        const current = readFilters(searchParams);
        const nextFilters = {
            ...current,
            [key]: typeof current[key] === 'boolean' ? false : '',
        };

        setForm(nextFilters);
        setSearchParams(buildSearchParams(nextFilters));
    };

    const applyQuickCategory = (categoryValue) => {
        const nextFilters = {
            ...form,
            category: categoryValue,
        };

        setForm(nextFilters);
        setSearchParams(buildSearchParams(nextFilters));
    };

    const resultsLabel = formatEventCount(total);
    const selectedCategory = getSelectedCategory(form.category, filterOptions.categories);
    const emptyStateText = activeFilterTags.length > 0
        ? 'Try clearing category, organizer, date, or price filters to widen the catalog.'
        : 'No public events are available right now. Publish or reactivate an event to populate this page.';

    return (
        <div className="events-browse-page">
            <section className="events-browse-hero">
                <div className="events-browse-hero__inner">
                    <div className="events-browse-hero__eyebrow">Public event discovery</div>
                    <div className="events-browse-hero__header">
                        <div>
                            <h1 className="events-browse-hero__title">Browse Events</h1>
                            <p className="events-browse-hero__subtitle">
                                Explore live listings, compare ticket tiers, and book upcoming experiences from one clean catalog.
                            </p>
                        </div>

                        <div className="events-browse-hero__stats">
                            <div className="events-browse-stat">
                                <span>Catalog size</span>
                                <strong>{loading ? 'Loading...' : resultsLabel}</strong>
                            </div>
                            <div className="events-browse-stat">
                                <span>Public categories</span>
                                <strong>{publicCategoryCount}</strong>
                            </div>
                            <div className="events-browse-stat">
                                <span>Active organizers</span>
                                <strong>{publicOrganizerCount}</strong>
                            </div>
                        </div>
                    </div>

                    {filterOptions.categories.length > 0 && (
                        <div className="events-browse-hero__chips">
                            <button
                                type="button"
                                className={`events-quick-chip ${form.category === '' ? 'is-active' : ''}`}
                                onClick={() => applyQuickCategory('')}
                            >
                                All public events
                            </button>

                            {filterOptions.categories.slice(0, 6).map((category) => (
                                <button
                                    key={category.id}
                                    type="button"
                                    className={`events-quick-chip ${String(category.id) === form.category ? 'is-active' : ''}`}
                                    onClick={() => applyQuickCategory(String(category.id))}
                                >
                                    {category.name}
                                    {category.eventCount > 0 ? <span>{category.eventCount}</span> : null}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </section>

            <div className="events-browse-body">
            <div className="events-browse-layout">
                <aside className={`events-filter-sidebar ${filtersVisible ? 'is-open' : ''}`}>
                    <div className="events-filter-sidebar__header">
                        <div>
                            <div className="events-filter-sidebar__kicker">Search & filter</div>
                            <div className="events-filter-sidebar__title">Refine the catalog</div>
                        </div>

                        <button
                            type="button"
                            className="events-filter-sidebar__close"
                            onClick={() => setFiltersVisible(false)}
                            aria-label="Close filters"
                        >
                            <X size={16} strokeWidth={2} />
                        </button>
                    </div>

                    <form onSubmit={applyFilters} className="events-filter-form">
                        <div className="events-filter-section">
                            <label className="form-label events-filter-label">Search</label>
                            <div className="events-filter-search">
                                <Search size={16} strokeWidth={1.8} />
                                <input
                                    className="form-input"
                                    placeholder="Event name, venue, organizer..."
                                    value={form.search}
                                    onChange={(e) => setForm((current) => ({ ...current, search: e.target.value }))}
                                />
                            </div>
                        </div>

                        <div className="events-filter-section">
                            <div className="events-filter-section__title">Event type</div>
                            <div className="events-filter-stack">
                                <div>
                                    <label className="form-label events-filter-label">Location format</label>
                                    <div className="events-filter-checkbox-group">
                                        <label className="events-filter-checkbox events-filter-checkbox--compact">
                                            <input
                                                type="checkbox"
                                                checked={form.locationType === ''}
                                                onChange={() => setForm((current) => ({ ...current, locationType: '' }))}
                                            />
                                            <span>All formats</span>
                                        </label>
                                        <label className="events-filter-checkbox events-filter-checkbox--compact">
                                            <input
                                                type="checkbox"
                                                checked={form.locationType === 'in_person'}
                                                onChange={() => setForm((current) => ({
                                                    ...current,
                                                    locationType: current.locationType === 'in_person' ? '' : 'in_person',
                                                }))}
                                            />
                                            <span>In-person</span>
                                        </label>
                                        <label className="events-filter-checkbox events-filter-checkbox--compact">
                                            <input
                                                type="checkbox"
                                                checked={form.locationType === 'online'}
                                                onChange={() => setForm((current) => ({
                                                    ...current,
                                                    locationType: current.locationType === 'online' ? '' : 'online',
                                                }))}
                                            />
                                            <span>Online</span>
                                        </label>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div className="events-filter-section">
                            <div className="events-filter-section__title">Date range</div>
                            <div className="events-filter-grid events-filter-grid--two">
                                <div>
                                    <label className="form-label events-filter-label">From</label>
                                    <input
                                        type="date"
                                        className="form-input"
                                        value={form.dateFrom}
                                        onChange={(e) => setForm((current) => ({ ...current, dateFrom: e.target.value }))}
                                    />
                                </div>

                                <div>
                                    <label className="form-label events-filter-label">To</label>
                                    <input
                                        type="date"
                                        className="form-input"
                                        value={form.dateTo}
                                        onChange={(e) => setForm((current) => ({ ...current, dateTo: e.target.value }))}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="events-filter-section">
                            <div className="events-filter-section__title">Ticket price</div>
                            <div className="events-filter-grid events-filter-grid--two">
                                <div>
                                    <label className="form-label events-filter-label">Min credits</label>
                                    <input
                                        type="number"
                                        min="0"
                                        className="form-input"
                                        placeholder="0"
                                        value={form.priceMin}
                                        onChange={(e) => setForm((current) => ({ ...current, priceMin: e.target.value }))}
                                    />
                                </div>

                                <div>
                                    <label className="form-label events-filter-label">Max credits</label>
                                    <input
                                        type="number"
                                        min="0"
                                        className="form-input"
                                        placeholder="2000"
                                        value={form.priceMax}
                                        onChange={(e) => setForm((current) => ({ ...current, priceMax: e.target.value }))}
                                    />
                                </div>
                            </div>
                        </div>

                        <label className="events-filter-checkbox">
                            <input
                                type="checkbox"
                                checked={form.availableOnly}
                                onChange={(e) => setForm((current) => ({ ...current, availableOnly: e.target.checked }))}
                            />
                            <span>
                                Show only ticketable upcoming events
                            </span>
                        </label>

                       

                        <div className="events-filter-actions">
                            <button type="button" className="btn btn-secondary" onClick={resetFilters}>
                                Reset
                            </button>
                            <button type="submit" className="btn btn-primary">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </aside>

                <section className="events-results-panel">
                    <div className="events-results-toolbar">
                        <div>
                            <div className="events-results-toolbar__eyebrow">Results</div>
                            <div className="events-results-toolbar__title">
                                {loading ? 'Refreshing event catalog...' : resultsLabel}
                            </div>
                            <div className="events-results-toolbar__subtitle">
                                {selectedCategory?.name
                                    ? `Currently filtered to ${selectedCategory.name}.`
                                    : 'Showing all public events that match your search.'}
                            </div>
                        </div>

                        <button
                            type="button"
                            className="btn btn-secondary btn-sm events-results-toolbar__filters-btn"
                            onClick={() => setFiltersVisible((value) => !value)}
                        >
                            <SlidersHorizontal size={15} strokeWidth={1.8} />
                            {filtersVisible ? 'Hide Filters' : 'Show Filters'}
                        </button>
                    </div>

                    {activeFilterTags.length > 0 && (
                        <div className="events-active-filters">
                            {activeFilterTags.map((tag) => (
                                <button
                                    key={`${tag.key}:${tag.value}`}
                                    type="button"
                                    className="events-active-filter-tag"
                                    onClick={() => clearSingleFilter(tag.key)}
                                >
                                    <span>{tag.label}: {tag.value}</span>
                                    <X size={13} strokeWidth={2} />
                                </button>
                            ))}

                            <button type="button" className="events-active-filters__clear" onClick={resetFilters}>
                                Clear all
                            </button>
                        </div>
                    )}

                    {loading ? (
                        <div className="events-results-grid">
                            {[1, 2, 3, 4, 5, 6].map((item) => <EventCardSkeleton key={item} />)}
                        </div>
                    ) : events.length === 0 ? (
                        <div className="card">
                            <div className="empty-state events-empty-state">
                                <div className="events-empty-state__icon">
                                    <Ticket size={30} strokeWidth={1.8} />
                                </div>
                                <div className="empty-state-title">No events found</div>
                                <div className="empty-state-text">{emptyStateText}</div>
                                <button onClick={resetFilters} className="btn btn-primary">
                                    Browse All Events
                                </button>
                            </div>
                        </div>
                    ) : (
                        <>
                            <div className="events-results-grid">
                                {events.map((event) => <EventCard key={event.id} event={event} />)}
                            </div>
                            <Pagination page={page} totalPages={totalPages} onPageChange={handlePageChange} />
                        </>
                    )}
                </section>
            </div>
            </div>
        </div>
    );
}

function EventCard({ event }) {
    const now = new Date();
    const startDate = event.startDate ? new Date(event.startDate) : null;
    const isPast = startDate ? startDate < now : false;
    const isSoldOut = event.status === 'sold_out' || (event.totalSeats > 0 && (event.soldTickets ?? 0) >= event.totalSeats);
    const priceLabel = isPast
        ? 'Ticket sales closed'
        : isSoldOut
            ? 'Sold Out'
            : event.lowestPrice != null
                ? `From ${event.lowestPrice.toLocaleString()} credits`
                : 'Pricing announced soon';

    return (
        <div className="event-card events-card">
            <div className="events-card__media">
                {event.bannerUrl ? (
                    <img
                        src={event.bannerUrl}
                        alt={event.name}
                        className="events-card__image"
                    />
                ) : (
                    <div className="events-card__placeholder">🎭</div>
                )}

                <div className="events-card__badges">
                    {event.category && <Badge status="active" label={event.category} />}
                    {event.status && event.status !== 'active' && <Badge status={event.status} />}
                    {isPast && <Badge status="deactivated" label="Past Event" />}
                </div>
            </div>

            <div className="events-card__body">
                <div className="events-card__headline">
                    <h3 className="events-card__title">{event.name}</h3>
                    <p className="events-card__description">
                        {event.description || 'Explore event details, ticket tiers, and venue information.'}
                    </p>
                </div>

                <div className="events-card__meta">
                    <div className="events-card__meta-item">
                        <CalendarDays size={14} strokeWidth={1.8} />
                        <span>
                            {startDate
                                ? startDate.toLocaleString('en-IN', {
                                    day: 'numeric',
                                    month: 'short',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                })
                                : 'Date TBA'}
                        </span>
                    </div>

                    <div className="events-card__meta-item">
                        <MapPin size={14} strokeWidth={1.8} />
                        <span>{event.isOnline ? 'Online event' : (event.venueName ?? 'Venue TBA')}</span>
                    </div>

                    {event.organizerName && (
                        <div className="events-card__meta-item">
                            <UserRound size={14} strokeWidth={1.8} />
                            <span>{event.organizerName}</span>
                        </div>
                    )}
                </div>

                <div className="events-card__footer">
                    <span className={`events-card__price ${isPast || isSoldOut ? 'is-muted' : ''}`}>
                        <Wallet size={14} strokeWidth={1.8} />
                        {priceLabel}
                    </span>

                    <Link to={`/events/${event.slug ?? event.id}`} className="btn btn-ghost btn-sm">
                        View Details
                    </Link>
                </div>
            </div>
        </div>
    );
}

function EventCardSkeleton() {
    return (
        <div className="event-card events-card animate-pulse">
            <div className="events-card__media events-card__media--skeleton" />
            <div className="events-card__body">
                <div style={{ height: '16px', background: '#E2E8F0', borderRadius: '4px', marginBottom: '10px' }} />
                <div style={{ height: '12px', background: '#E2E8F0', borderRadius: '4px', width: '85%', marginBottom: '8px' }} />
                <div style={{ height: '12px', background: '#E2E8F0', borderRadius: '4px', width: '60%', marginBottom: '18px' }} />
                <div style={{ height: '12px', background: '#E2E8F0', borderRadius: '4px', width: '72%', marginBottom: '6px' }} />
                <div style={{ height: '12px', background: '#E2E8F0', borderRadius: '4px', width: '56%', marginBottom: '6px' }} />
            </div>
        </div>
    );
}

function readFilters(searchParams) {
    return {
        search: searchParams.get('search') ?? '',
        category: searchParams.get('category') ?? '',
        dateFrom: searchParams.get('dateFrom') ?? '',
        dateTo: searchParams.get('dateTo') ?? '',
        locationType: searchParams.get('locationType') ?? '',
        priceMin: searchParams.get('priceMin') ?? '',
        priceMax: searchParams.get('priceMax') ?? '',
        organizer: searchParams.get('organizer') ?? '',
        availableOnly: searchParams.has('availableOnly') ? searchParams.get('availableOnly') === 'true' : true,
    };
}

function buildSearchParams(filters) {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (typeof value === 'boolean') {
            if (value) {
                params.set(key, 'true');
            }
            return;
        }

        if (String(value).trim() !== '') {
            params.set(key, String(value).trim());
        }
    });

    return params;
}

function hasActiveFilters(searchParams) {
    return Array.from(searchParams.keys()).some((key) => key !== 'page');
}

function getActiveFilterTags(searchParams, options) {
    const filters = readFilters(searchParams);
    const categoryName = getSelectedCategory(filters.category, options.categories)?.name;
    const organizerName = getSelectedOrganizer(filters.organizer, options.organizers)?.name;

    return [
        filters.search ? { key: 'search', label: 'Search', value: filters.search } : null,
        filters.category ? { key: 'category', label: 'Category', value: categoryName ?? filters.category } : null,
        filters.dateFrom ? { key: 'dateFrom', label: 'From', value: filters.dateFrom } : null,
        filters.dateTo ? { key: 'dateTo', label: 'To', value: filters.dateTo } : null,
        filters.locationType ? { key: 'locationType', label: 'Format', value: filters.locationType === 'online' ? 'Online' : 'In-person' } : null,
        filters.priceMin ? { key: 'priceMin', label: 'Min Price', value: `${filters.priceMin} cr` } : null,
        filters.priceMax ? { key: 'priceMax', label: 'Max Price', value: `${filters.priceMax} cr` } : null,
        filters.organizer ? { key: 'organizer', label: 'Organizer', value: organizerName ?? filters.organizer } : null,
        filters.availableOnly ? { key: 'availableOnly', label: 'Availability', value: 'Ticketable now' } : null,
    ].filter(Boolean);
}

function sanitizeSearchParams(searchParams, options) {
    const nextParams = new URLSearchParams(searchParams);
    const filters = readFilters(searchParams);

    if (filters.category && !getSelectedCategory(filters.category, options.categories)) {
        nextParams.delete('category');
    }

    if (filters.organizer && !getSelectedOrganizer(filters.organizer, options.organizers)) {
        nextParams.delete('organizer');
    }

    const page = Number(searchParams.get('page') ?? 1);
    if (!Number.isFinite(page) || page < 1) {
        nextParams.delete('page');
    }

    return nextParams;
}

function getSelectedCategory(value, categories) {
    if (!value) {
        return null;
    }

    const normalized = String(value).trim().toLowerCase();

    return categories.find((category) => {
        return String(category.id) === value
            || String(category.slug ?? '').toLowerCase() === normalized
            || String(category.name ?? '').toLowerCase() === normalized;
    }) ?? null;
}

function getSelectedOrganizer(value, organizers) {
    if (!value) {
        return null;
    }

    const normalized = String(value).trim().toLowerCase();

    return organizers.find((organizer) => {
        return String(organizer.id) === value
            || String(organizer.name ?? '').toLowerCase() === normalized
            || String(organizer.email ?? '').toLowerCase() === normalized;
    }) ?? null;
}

function formatEventCount(count) {
    const normalized = Number.isFinite(count) ? count : 0;
    return `${normalized.toLocaleString('en-IN')} event${normalized === 1 ? '' : 's'}`;
}
