import React, { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { CalendarDays, MapPin, ArrowRight, Zap, QrCode, Headphones, Search } from 'lucide-react';
import { getEvents } from '../api/eventsApi';
import { getCategories } from '../api/categoriesApi';

export default function HomePage() {
    const navigate = useNavigate();
    const [query, setQuery] = useState('');
    const [featuredEvents, setFeaturedEvents] = useState([]);
    const [categories, setCategories] = useState([]);
    const [loadingEvents, setLoadingEvents] = useState(true);

    useEffect(() => {
        getEvents({ perPage: 8, availableOnly: true })
            .then(d => setFeaturedEvents(d.items ?? []))
            .catch(() => setFeaturedEvents([]))
            .finally(() => setLoadingEvents(false));

        getCategories()
            .then(d => setCategories(Array.isArray(d) ? d : (d?.items ?? [])))
            .catch(() => setCategories([]));
    }, []);

    const handleSearch = (e) => {
        e.preventDefault();
        if (query.trim()) navigate(`/events?search=${encodeURIComponent(query.trim())}`);
        else navigate('/events');
    };

    return (
        <div className="home-page">
            {/* ── Hero ── */}
            <section className="home-hero">
                <div className="home-hero-overlay" />
                <div className="home-hero-content">
                    <p className="home-hero-eyebrow">
                        <span style={{ width: 6, height: 6, borderRadius: '50%', background: '#2EC4A1', display: 'inline-block', flexShrink: 0 }} />
                        Concerts · Sports · Theatre · Conferences
                    </p>
                    <h1 className="home-hero-title">Discover &amp; Book<br /><span>Amazing Events</span></h1>
                    <p className="home-hero-sub">
                        Find the best experiences near you — all in one place.
                    </p>
                    <form onSubmit={handleSearch} className="home-hero-search">
                        <div className="home-hero-search-wrap">
                            <Search size={18} className="home-hero-search-icon" />
                            <input
                                type="text"
                                placeholder="Search events, artists, venues…"
                                value={query}
                                onChange={e => setQuery(e.target.value)}
                                autoComplete="off"
                            />
                            <button type="submit" className="home-hero-search-btn">Search</button>
                        </div>
                    </form>
                </div>
            </section>

            {/* ── Category chips ── */}
            {categories.length > 0 && (
                <section className="home-section">
                    <div className="home-section-inner">
                        <h2 className="home-section-title">Browse by Category</h2>
                        <div className="home-category-chips">
                            <Link to="/events" className="home-cat-chip home-cat-chip-all">
                                All Events
                            </Link>
                            {categories.map(cat => (
                                <Link
                                    key={cat.id}
                                    to={`/events?category=${cat.id}`}
                                    className="home-cat-chip"
                                >
                                    {cat.name}
                                </Link>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* ── Featured Events ── */}
            <section className="home-section" style={{ paddingTop: categories.length > 0 ? '0' : undefined }}>
                <div className="home-section-inner">
                    <div className="home-section-header">
                        <h2 className="home-section-title">Upcoming Events</h2>
                        <Link to="/events" className="home-see-all">
                            See all <ArrowRight size={14} style={{ display: 'inline', marginLeft: 4 }} />
                        </Link>
                    </div>

                    {loadingEvents ? (
                        <div className="home-events-grid">
                            {[1,2,3,4,5,6,7,8].map(i => <HomeEventSkeleton key={i} />)}
                        </div>
                    ) : featuredEvents.length === 0 ? (
                        <div className="home-empty">
                            <span className="text-4xl">🎭</span>
                            <p className="mt-3 text-gray-500">No upcoming events right now. Check back soon!</p>
                            <Link to="/events" className="home-browse-btn mt-4">Browse All Events</Link>
                        </div>
                    ) : (
                        <div className="home-events-grid">
                            {featuredEvents.map(ev => <HomeEventCard key={ev.id} event={ev} />)}
                        </div>
                    )}
                </div>
            </section>

            {/* ── Why TicketHub ── */}
            <section className="home-why">
                <div className="home-section-inner">
                    <h2 className="home-section-title" style={{ textAlign: 'center' }}>Why TicketHub?</h2>
                    <div className="home-why-grid">
                        <WhyCard icon={<Zap size={28} strokeWidth={1.8} />} title="Instant Booking" desc="Add tickets to cart, pay with credits — done in seconds." />
                        <WhyCard icon={<QrCode size={28} strokeWidth={1.8} />} title="E-Tickets" desc="QR-code tickets delivered directly to your inbox." />
                        <WhyCard icon={<Headphones size={28} strokeWidth={1.8} />} title="Diverse Events" desc="Concerts, conferences, sports, comedy and more." />
                    </div>
                </div>
            </section>

            {/* ── How it works ── */}
            <section className="home-section">
                <div className="home-section-inner">
                    <h2 className="home-section-title" style={{ textAlign: 'center' }}>How it works</h2>
                    <div className="home-steps">
                        {[
                            { num: '01', title: 'Browse Events', desc: 'Explore by category, date, or location.' },
                            { num: '02', title: 'Pick Your Tickets', desc: 'Choose tier and quantity, add to cart.' },
                            { num: '03', title: 'Checkout', desc: 'Pay instantly with your credit balance.' },
                            { num: '04', title: 'Get Your Ticket', desc: 'Receive QR e-ticket straight to your email.' },
                        ].map(s => (
                            <div key={s.num} className="home-step">
                                <div className="home-step-num">{s.num}</div>
                                <div className="home-step-title">{s.title}</div>
                                <div className="home-step-desc">{s.desc}</div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── CTA Banner ── */}
            <section className="home-cta">
                <div className="home-section-inner" style={{ textAlign: 'center' }}>
                    <h2 className="home-cta-title">Host Your Own Event?</h2>
                    <p className="home-cta-sub">Join TicketHub as an organizer and reach thousands of attendees.</p>
                    <div className="home-cta-btns">
                        <Link to="/register/organizer" className="home-cta-primary">Become an Organizer</Link>
                        <Link to="/register" className="home-cta-secondary">Create Account</Link>
                    </div>
                </div>
            </section>
        </div>
    );
}

function HomeEventCard({ event }) {
    const startDate = event.startDate ? new Date(event.startDate) : null;
    const isSoldOut = event.status === 'sold_out' || (event.totalSeats > 0 && (event.soldTickets ?? 0) >= event.totalSeats);

    return (
        <Link to={`/events/${event.slug ?? event.id}`} className="home-event-card">
            <div className="home-event-img">
                {event.bannerUrl ? (
                    <img src={event.bannerUrl} alt={event.name} />
                ) : (
                    <div className="home-event-img-placeholder">🎭</div>
                )}
                {event.category && (
                    <span className="home-event-cat">{event.category}</span>
                )}
                {isSoldOut && <span className="home-event-soldout">Sold Out</span>}
            </div>
            <div className="home-event-body">
                <h3 className="home-event-name">{event.name}</h3>
                <div className="home-event-meta">
                    {startDate && (
                        <span>
                            <CalendarDays size={13} strokeWidth={1.8} style={{ display: 'inline', marginRight: 4 }} />
                            {startDate.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}
                        </span>
                    )}
                    <span>
                        <MapPin size={13} strokeWidth={1.8} style={{ display: 'inline', marginRight: 4 }} />
                        {event.isOnline ? 'Online' : (event.venueName ?? 'TBA')}
                    </span>
                </div>
                <div className="home-event-footer">
                    <span className="home-event-price">
                        {isSoldOut
                            ? 'Sold Out'
                            : event.lowestPrice != null
                                ? `From ${event.lowestPrice.toLocaleString()} cr`
                                : 'Free / TBA'}
                    </span>
                    <span className="home-event-cta">Book Now →</span>
                </div>
            </div>
        </Link>
    );
}

function HomeEventSkeleton() {
    return (
        <div className="home-event-card animate-pulse">
            <div className="home-event-img" style={{ background: '#E2E8F0' }} />
            <div className="home-event-body">
                <div style={{ height: 16, background: '#E2E8F0', borderRadius: 4, marginBottom: 8 }} />
                <div style={{ height: 12, background: '#E2E8F0', borderRadius: 4, width: '70%', marginBottom: 6 }} />
                <div style={{ height: 12, background: '#E2E8F0', borderRadius: 4, width: '50%' }} />
            </div>
        </div>
    );
}

function WhyCard({ icon, title, desc }) {
    return (
        <div className="home-why-card">
            <div className="home-why-icon">{icon}</div>
            <h3 className="home-why-title">{title}</h3>
            <p className="home-why-desc">{desc}</p>
        </div>
    );
}
