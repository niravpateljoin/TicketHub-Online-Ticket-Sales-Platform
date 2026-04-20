import React, { useState, useRef, useEffect, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Menu, Search, Bell, ChevronDown, LogOut, CalendarDays, MapPin, ArrowRight, ShoppingCart, Wallet, Ticket, UserCircle } from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { useCart } from '../../hooks/useCart';
import { ROLES } from '../../utils/constants';
import { getEvents } from '../../api/eventsApi';
import { updateMyProfile } from '../../api/authApi';
import { useToast } from '../../context/ToastContext';
import Modal from '../common/Modal';

export default function Topbar({ onMenuToggle }) {
    const { user, logout, setCurrentUser } = useAuth();
    const { itemCount } = useCart();
    const { success, error: showError } = useToast();
    const navigate = useNavigate();
    const [dropdownOpen, setDropdownOpen] = useState(false);
    const dropdownRef = useRef(null);
    const [profileOpen, setProfileOpen] = useState(false);
    const [profileForm, setProfileForm] = useState({ name: '', email: '', newEmail: '', password: '' });
    const [profileErrors, setProfileErrors] = useState({});
    const [savingProfile, setSavingProfile] = useState(false);

    // Search state
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [searchLoading, setSearchLoading] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const searchRef = useRef(null);
    const debounceRef = useRef(null);

    const displayName = user?.name?.trim() || user?.email || 'User';
    const initials = (user?.name?.trim() || user?.email?.split('@')[0] || 'U')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || 'U';
    const isUser = user?.roles?.includes(ROLES.USER);
    const isAdmin = user?.roles?.includes(ROLES.ADMIN);

    const roleLabel = () => {
        if (!user) return '';
        if (user.roles.includes(ROLES.ADMIN)) return 'Admin';
        if (user.roles.includes(ROLES.ORGANIZER)) return 'Organizer';
        return 'User';
    };

    const handleLogout = () => {
        setDropdownOpen(false);
        logout();
        navigate('/login');
    };

    const openProfile = () => {
        setProfileForm({
            name: user?.name ?? '',
            email: user?.email ?? '',
            newEmail: user?.pendingEmail ?? '',
            password: '',
        });
        setProfileErrors({});
        setDropdownOpen(false);
        setProfileOpen(true);
    };

    const setProfileField = (key) => (event) => {
        const value = event.target.value;
        setProfileForm((current) => ({ ...current, [key]: value }));
        setProfileErrors((current) => ({ ...current, [key]: undefined }));
    };

    const validateProfile = () => {
        const errors = {};

        if (!profileForm.name.trim()) {
            errors.name = 'Name is required.';
        }

        if (!profileForm.email.trim()) {
            errors.email = 'Email is required.';
        }

        if (profileForm.newEmail && profileForm.newEmail === profileForm.email) {
            errors.newEmail = 'New email must be different from the current email.';
        }

        if (profileForm.password && profileForm.password.length < 8) {
            errors.password = 'Password must be at least 8 characters.';
        }

        return errors;
    };

    const handleProfileSave = async (event) => {
        event.preventDefault();

        const errors = validateProfile();
        if (Object.keys(errors).length > 0) {
            setProfileErrors(errors);
            return;
        }

        setSavingProfile(true);
        try {
            const updatedUser = await updateMyProfile(profileForm);
            setCurrentUser(updatedUser);
            setProfileOpen(false);
            setProfileForm((current) => ({ ...current, password: '', newEmail: updatedUser.pendingEmail ?? '' }));
            success(updatedUser.message ?? 'Profile updated.');
        } catch (err) {
            const responseErrors = err.response?.data?.errors;
            if (responseErrors && typeof responseErrors === 'object') {
                setProfileErrors(responseErrors);
            } else {
                showError(err.response?.data?.message ?? 'Could not update profile.');
            }
        } finally {
            setSavingProfile(false);
        }
    };

    // Close user dropdown on outside click
    useEffect(() => {
        const handler = (e) => {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
                setDropdownOpen(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    // Close search dropdown on outside click
    useEffect(() => {
        const handler = (e) => {
            if (searchRef.current && !searchRef.current.contains(e.target)) {
                setSearchOpen(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    // Debounced search
    const runSearch = useCallback((q) => {
        if (!q.trim()) {
            setResults([]);
            setSearchOpen(false);
            return;
        }
        setSearchLoading(true);
        setSearchOpen(true);
        getEvents({ search: q, perPage: 6 })
            .then((data) => {
                setResults(data.items ?? data.events ?? []);
            })
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
        if (e.key === 'Escape') {
            setSearchOpen(false);
            setQuery('');
        }
    };

    const handleResultClick = (identifier) => {
        setSearchOpen(false);
        setQuery('');
        navigate(`/events/${identifier}`);
    };

    const handleSeeAll = () => {
        setSearchOpen(false);
        navigate(`/events?search=${encodeURIComponent(query.trim())}`);
    };

    return (
        <header className="topbar">
            {/* Hamburger (mobile) */}
            <button
                className="lg:hidden text-gray-500 hover:text-gray-800 mr-2"
                onClick={onMenuToggle}
                aria-label="Toggle sidebar"
            >
                <Menu size={22} strokeWidth={1.8} />
            </button>

            {/* Search bar */}
            <div className="topbar-search" ref={searchRef}>
                <Search size={15} className="search-icon" />
                <input
                    type="text"
                    placeholder="Search events..."
                    value={query}
                    onChange={handleQueryChange}
                    onKeyDown={handleKeyDown}
                    onFocus={() => query.trim() && setSearchOpen(true)}
                    autoComplete="off"
                />

                {/* Dropdown */}
                {searchOpen && (
                    <div className="search-dropdown">
                        {searchLoading ? (
                            <div className="search-dropdown-empty">Searching…</div>
                        ) : results.length === 0 ? (
                            <div className="search-dropdown-empty">No events found for "{query}"</div>
                        ) : (
                            <>
                                {results.map(ev => (
                                    <button
                                        key={ev.id}
                                        className="search-result-item"
                                        onClick={() => handleResultClick(ev.slug ?? ev.id)}
                                    >
                                        <div className="search-result-thumb">
                                            {ev.bannerUrl
                                                ? <img src={ev.bannerUrl} alt="" />
                                                : <CalendarDays size={16} className="text-gray-400" />
                                            }
                                        </div>
                                        <div className="search-result-info">
                                            <div className="search-result-name">{ev.name}</div>
                                            <div className="search-result-meta">
                                                {ev.startDate && (
                                                    <span>
                                                        {new Date(ev.startDate).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}
                                                    </span>
                                                )}
                                                {ev.venueName && <span><MapPin size={11} style={{ display:'inline', marginRight: 2 }} />{ev.venueName}</span>}
                                            </div>
                                        </div>
                                        {ev.category && (
                                            <span className="search-result-badge">{ev.category}</span>
                                        )}
                                    </button>
                                ))}
                                <button className="search-see-all" onClick={handleSeeAll}>
                                    See all results for "{query}" <ArrowRight size={13} style={{ display:'inline', marginLeft: 4 }} />
                                </button>
                            </>
                        )}
                    </div>
                )}
            </div>

            <div className="flex items-center gap-3 ml-auto">
                <Link to="/events" className="hidden md:inline-flex items-center gap-2 text-sm text-gray-600 hover:text-primary">
                    <CalendarDays size={15} strokeWidth={1.8} />
                    <span>Browse Events</span>
                </Link>

                {isUser && (
                    <Link to="/user/bookings" className="hidden md:inline-flex items-center gap-2 text-sm text-gray-600 hover:text-primary">
                        <Ticket size={15} strokeWidth={1.8} />
                        <span>My Bookings</span>
                    </Link>
                )}

                {isUser && (
                    <div className="topbar-credits">
                        <Wallet size={14} strokeWidth={1.8} />
                        <span>{(user.creditBalance ?? 0).toLocaleString()} cr</span>
                    </div>
                )}

                {isUser && (
                    <Link to="/cart" className="relative p-2 rounded-lg hover:bg-gray-100 text-gray-600">
                        <ShoppingCart size={18} strokeWidth={1.8} />
                        {itemCount > 0 && (
                            <span
                                className="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-primary text-white text-[10px] font-semibold flex items-center justify-center"
                            >
                                {itemCount}
                            </span>
                        )}
                    </Link>
                )}

                {/* Notification bell */}
                <button className="relative p-2 rounded-lg hover:bg-gray-100 text-gray-500">
                    <Bell size={18} strokeWidth={1.8} />
                </button>

                {/* User menu */}
                {user ? (
                    <div className="relative" ref={dropdownRef}>
                        <button
                            className="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                            onClick={() => setDropdownOpen(o => !o)}
                        >
                            <div className="w-8 h-8 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">
                                {initials}
                            </div>
                            {user?.name?.trim() && (
                                <div className="topbar-user-info">
                                    <span className="topbar-user-name">{user.name.trim()}</span>
                                    <span className="topbar-user-role">{roleLabel()}</span>
                                </div>
                            )}
                            <ChevronDown size={15} className="text-gray-400" />
                        </button>

                        {dropdownOpen && (
                            <div className="user-dropdown">
                                {/* Avatar + info header */}
                                <div className="user-dropdown-header">
                                    <div className="user-dropdown-avatar">{initials}</div>
                                    <div className="user-dropdown-info">
                                        <div className="user-dropdown-name">{displayName}</div>
                                        <div className="user-dropdown-email">{user.email}</div>
                                    </div>
                                </div>

                                <div className="user-dropdown-role-row">
                                    <span className="user-dropdown-role-badge">{roleLabel()}</span>
                                </div>

                                <div className="user-dropdown-divider" />

                                <button onClick={openProfile} className="user-dropdown-action">
                                    <UserCircle size={14} strokeWidth={1.8} />
                                    <span>Profile</span>
                                </button>

                                {/* Logout */}
                                <button onClick={handleLogout} className="user-dropdown-logout">
                                    <LogOut size={14} strokeWidth={1.8} />
                                    <span>Sign out</span>
                                </button>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="flex items-center gap-2">
                        <Link to="/login" className="btn btn-secondary btn-sm">Login</Link>
                        <Link to="/register" className="btn btn-primary btn-sm">Sign Up</Link>
                    </div>
                )}
            </div>

            <Modal
                isOpen={profileOpen}
                onClose={() => !savingProfile && setProfileOpen(false)}
                title="My Profile"
            >
                <form onSubmit={handleProfileSave} className="space-y-4">
                    <div>
                        <label className="form-label">Name <span className="required">*</span></label>
                        <input
                            className={`form-input${profileErrors.name ? ' error' : ''}`}
                            placeholder="Your name"
                            value={profileForm.name}
                            onChange={setProfileField('name')}
                        />
                        {profileErrors.name && <div className="field-error">{profileErrors.name}</div>}
                    </div>

                    <div>
                        <label className="form-label">Email <span className="required">*</span></label>
                        <input
                            type="email"
                            className={`form-input${profileErrors.email ? ' error' : ''}`}
                            placeholder="you@example.com"
                            value={profileForm.email}
                            onChange={setProfileField('email')}
                            readOnly={isAdmin}
                            disabled={isAdmin}
                        />
                        {profileErrors.email && <div className="field-error">{profileErrors.email}</div>}
                        {isAdmin && !profileErrors.email && (
                            <div className="field-hint">Administrators keep the current email active until a new email is verified.</div>
                        )}
                    </div>

                    {isAdmin && (
                        <div>
                            <label className="form-label">New Email</label>
                            <input
                                type="email"
                                className={`form-input${profileErrors.newEmail ? ' error' : ''}`}
                                placeholder="Enter a new email address"
                                value={profileForm.newEmail}
                                onChange={setProfileField('newEmail')}
                            />
                            {profileErrors.newEmail && <div className="field-error">{profileErrors.newEmail}</div>}
                            {!profileErrors.newEmail && user?.pendingEmail && (
                                <div className="field-hint">
                                    Pending verification for <strong>{user.pendingEmail}</strong>. It will replace the current email after verification.
                                </div>
                            )}
                            {!profileErrors.newEmail && !user?.pendingEmail && (
                                <div className="field-hint">Enter a new email address to send a verification link before switching the admin login email.</div>
                            )}
                        </div>
                    )}

                    <div>
                        <label className="form-label">New Password</label>
                        <input
                            type="password"
                            className={`form-input${profileErrors.password ? ' error' : ''}`}
                            placeholder="Leave blank to keep current password"
                            value={profileForm.password}
                            onChange={setProfileField('password')}
                        />
                        {!profileErrors.password && (
                            <div className="field-hint">Leave blank if you do not want to change your password.</div>
                        )}
                        {profileErrors.password && <div className="field-error">{profileErrors.password}</div>}
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            className="btn btn-secondary"
                            onClick={() => setProfileOpen(false)}
                            disabled={savingProfile}
                        >
                            Cancel
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={savingProfile}>
                            {savingProfile ? 'Saving…' : 'Save Profile'}
                        </button>
                    </div>
                </form>
            </Modal>
        </header>
    );
}
