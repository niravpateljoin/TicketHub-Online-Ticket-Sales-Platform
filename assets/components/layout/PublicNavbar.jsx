import React, { useState, useRef, useEffect, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
    Search, ShoppingCart, ChevronDown, LogOut, UserCircle,
    CalendarDays, Ticket, LayoutDashboard, ArrowRight, MapPin, Wallet,
} from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { useCart } from '../../hooks/useCart';
import { ROLES } from '../../utils/constants';
import { getEvents } from '../../api/eventsApi';

export default function PublicNavbar() {
    const { user, logout } = useAuth();
    const { itemCount } = useCart();
    const navigate = useNavigate();
    const [dropdownOpen, setDropdownOpen] = useState(false);
    const dropdownRef = useRef(null);

    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [searchLoading, setSearchLoading] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const searchRef = useRef(null);
    const debounceRef = useRef(null);

    const displayName = user?.name?.trim() || user?.email?.split('@')[0] || 'User';
    const initials = displayName
        .split(/\s+/).filter(Boolean).slice(0, 2)
        .map(p => p[0]?.toUpperCase() ?? '').join('') || 'U';

    const dashboardPath = () => {
        if (!user) return '/login';
        if (user.roles.includes(ROLES.ADMIN)) return '/admin/dashboard';
        if (user.roles.includes(ROLES.ORGANIZER)) return '/organizer/dashboard';
        return '/user/dashboard';
    };

    const handleLogout = () => {
        setDropdownOpen(false);
        logout();
        navigate('/login');
    };

    // Close dropdowns on outside click
    useEffect(() => {
        const h = (e) => {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target)) setDropdownOpen(false);
        };
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, []);

    useEffect(() => {
        const h = (e) => {
            if (searchRef.current && !searchRef.current.contains(e.target)) setSearchOpen(false);
        };
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, []);

    const runSearch = useCallback((q) => {
        if (!q.trim()) { setResults([]); setSearchOpen(false); return; }
        setSearchLoading(true);
        setSearchOpen(true);
        getEvents({ search: q, perPage: 6 })
            .then(d => setResults(d.items ?? []))
            .catch(() => setResults([]))
            .finally(() => setSearchLoading(false));
    }, []);

    const handleQueryChange = (e) => {
        const q = e.target.value;
        setQuery(q);
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => runSearch(q), 280);
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && query.trim()) {
            setSearchOpen(false);
            navigate(`/events?search=${encodeURIComponent(query.trim())}`);
        }
        if (e.key === 'Escape') { setSearchOpen(false); setQuery(''); }
    };

    const handleResultClick = (identifier) => {
        setSearchOpen(false); setQuery('');
        navigate(`/events/${identifier}`);
    };

    return (
        <header className="pub-nav">
            <div className="pub-nav-inner">
                {/* Logo */}
                <Link to="/" className="pub-nav-logo">
                    <span className="pub-nav-logo-text">TicketHub</span>
                </Link>

                {/* Search */}
                <div className="pub-nav-search" ref={searchRef}>
                    <Search size={16} className="pub-nav-search-icon" />
                    <input
                        type="text"
                        placeholder="Search events, artists, venues…"
                        value={query}
                        onChange={handleQueryChange}
                        onKeyDown={handleKeyDown}
                        onFocus={() => query.trim() && setSearchOpen(true)}
                        autoComplete="off"
                    />
                    {searchOpen && (
                        <div className="search-dropdown">
                            {searchLoading ? (
                                <div className="search-dropdown-empty">Searching…</div>
                            ) : results.length === 0 ? (
                                <div className="search-dropdown-empty">No results for "{query}"</div>
                            ) : (
                                <>
                                    {results.map(ev => (
                                        <button key={ev.id} className="search-result-item" onClick={() => handleResultClick(ev.slug ?? ev.id)}>
                                            <div className="search-result-thumb">
                                                {ev.bannerUrl
                                                    ? <img src={ev.bannerUrl} alt="" />
                                                    : <CalendarDays size={16} className="text-gray-400" />}
                                            </div>
                                            <div className="search-result-info">
                                                <div className="search-result-name">{ev.name}</div>
                                                <div className="search-result-meta">
                                                    {ev.startDate && <span>{new Date(ev.startDate).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}</span>}
                                                    {ev.venueName && <span><MapPin size={11} style={{ display: 'inline', marginRight: 2 }} />{ev.venueName}</span>}
                                                </div>
                                            </div>
                                            {ev.category && <span className="search-result-badge">{ev.category}</span>}
                                        </button>
                                    ))}
                                    <button className="search-see-all" onClick={() => { setSearchOpen(false); navigate(`/events?search=${encodeURIComponent(query.trim())}`); }}>
                                        See all results for "{query}" <ArrowRight size={13} style={{ display: 'inline', marginLeft: 4 }} />
                                    </button>
                                </>
                            )}
                        </div>
                    )}
                </div>

                {/* Right nav */}
                <nav className="pub-nav-right">
                    <Link to="/events" className="pub-nav-link">
                        <CalendarDays size={15} strokeWidth={1.8} />
                        <span>Events</span>
                    </Link>

                    {user && (
                        <Link to={dashboardPath()} className="pub-nav-link">
                            <LayoutDashboard size={15} strokeWidth={1.8} />
                            <span>Dashboard</span>
                        </Link>
                    )}

                    {user && user.creditBalance != null && (
                        <span className="pub-nav-credits">
                            <Wallet size={14} strokeWidth={1.8} />
                            <span>{user.creditBalance.toLocaleString()} cr</span>
                        </span>
                    )}

                    {user?.roles?.includes(ROLES.USER) && (
                        <>
                            <Link to="/user/bookings" className="pub-nav-link">
                                <Ticket size={15} strokeWidth={1.8} />
                                <span>My Bookings</span>
                            </Link>
                            <Link to="/cart" className="pub-nav-cart">
                                <ShoppingCart size={18} strokeWidth={1.8} />
                                {itemCount > 0 && <span className="pub-nav-cart-badge">{itemCount}</span>}
                            </Link>
                        </>
                    )}

                    {user ? (
                        <div className="relative" ref={dropdownRef}>
                            <button className="pub-nav-avatar" onClick={() => setDropdownOpen(o => !o)}>
                                <span>{initials}</span>
                                <ChevronDown size={13} />
                            </button>
                            {dropdownOpen && (
                                <div className="user-dropdown" style={{ right: 0, top: 'calc(100% + 8px)' }}>
                                    <div className="user-dropdown-header">
                                        <div className="user-dropdown-avatar">{initials}</div>
                                        <div className="user-dropdown-info">
                                            <div className="user-dropdown-name">{displayName}</div>
                                            <div className="user-dropdown-email">{user.email}</div>
                                        </div>
                                    </div>
                                    <div className="user-dropdown-divider" />
                                    <Link to={dashboardPath()} onClick={() => setDropdownOpen(false)} className="user-dropdown-action">
                                        <UserCircle size={14} strokeWidth={1.8} />
                                        <span>Dashboard</span>
                                    </Link>
                                    <button onClick={handleLogout} className="user-dropdown-logout">
                                        <LogOut size={14} strokeWidth={1.8} />
                                        <span>Sign out</span>
                                    </button>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="flex items-center gap-2">
                            <Link to="/login" className="pub-nav-login">Login</Link>
                            <Link to="/register" className="pub-nav-signup">Sign Up</Link>
                        </div>
                    )}
                </nav>
            </div>
        </header>
    );
}
