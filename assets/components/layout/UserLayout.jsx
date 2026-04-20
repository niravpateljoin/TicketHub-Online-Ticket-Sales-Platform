import React, { useState } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import {
    LayoutDashboard, Ticket, UserCircle, CalendarDays,
    LogOut, ShoppingCart, Wallet, Menu, X,
} from 'lucide-react';
import PublicNavbar from './PublicNavbar';
import { useAuth } from '../../hooks/useAuth';

function UserNavItem({ to, icon: Icon, label }) {
    return (
        <NavLink
            to={to}
            className={({ isActive }) => `user-nav-item${isActive ? ' active' : ''}`}
            end={to === '/user/dashboard'}
        >
            {Icon && <Icon size={17} strokeWidth={1.8} className="flex-shrink-0" />}
            <span>{label}</span>
        </NavLink>
    );
}

export default function UserLayout({ children }) {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const displayName = user?.name?.trim() || user?.email?.split('@')[0] || 'User';
    const initials = displayName
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase() ?? '')
        .join('') || 'U';

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    return (
        <div className="user-account-layout">
            <PublicNavbar />

            {/* Mobile overlay */}
            {sidebarOpen && (
                <div
                    className="user-sidebar-overlay"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Centered grid container — same pattern as Events page */}
            <div className="user-account-body">

                {/* Left sidebar */}
                <aside className={`user-account-sidebar${sidebarOpen ? ' is-open' : ''}`}>
                    {/* User profile header */}
                    <div className="user-sidebar-profile">
                        <div className="user-sidebar-avatar">{initials}</div>
                        <div className="user-sidebar-name">{displayName}</div>
                        <div className="user-sidebar-email">{user?.email}</div>
                        {user?.creditBalance != null && (
                            <div className="user-sidebar-credits">
                                <Wallet size={13} strokeWidth={1.8} />
                                <span>{(user.creditBalance).toLocaleString()} credits</span>
                            </div>
                        )}
                    </div>

                    {/* Nav */}
                    <nav className="user-sidebar-nav">
                        <div className="user-nav-section-label">My Account</div>
                        <UserNavItem to="/user/dashboard" icon={LayoutDashboard} label="Dashboard" />
                        <UserNavItem to="/user/bookings" icon={Ticket} label="My Tickets" />
                        <UserNavItem to="/user/profile" icon={UserCircle} label="Profile Settings" />

                        <div className="user-nav-section-label" style={{ marginTop: 8 }}>Explore</div>
                        <UserNavItem to="/events" icon={CalendarDays} label="Browse Events" />
                        <UserNavItem to="/cart" icon={ShoppingCart} label="My Cart" />
                    </nav>

                    <div className="user-sidebar-footer">
                        <button className="user-nav-logout" onClick={handleLogout}>
                            <LogOut size={16} strokeWidth={1.8} />
                            <span>Sign Out</span>
                        </button>
                    </div>
                </aside>

                {/* Right main content */}
                <main className="user-account-main">
                    {children}
                </main>
            </div>

            {/* Mobile toggle button */}
            <button
                className="user-sidebar-toggle"
                onClick={() => setSidebarOpen(o => !o)}
                aria-label="Toggle menu"
            >
                {sidebarOpen ? <X size={20} /> : <Menu size={20} />}
            </button>
        </div>
    );
}
