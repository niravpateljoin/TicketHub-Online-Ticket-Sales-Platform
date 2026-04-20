import React, { useState } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import {
    LayoutDashboard, Building2, CalendarDays, AlertTriangle,
    Ticket, ShoppingCart, LogIn, UserPlus, LogOut, Shield,
    ChevronUp, ChevronDown, Users, Tag, ScanLine, BookOpen, Clock,
} from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { ROLES } from '../../utils/constants';
import { getPendingOrganizers } from '../../api/adminApi';
import { getOrganizerEvents } from '../../api/organizerApi';

function NavItem({ to, icon: Icon, label, badge }) {
    return (
        <NavLink
            to={to}
            className={({ isActive }) => `nav-item${isActive ? ' active' : ''}`}
        >
            {Icon && <Icon size={17} strokeWidth={1.8} className="flex-shrink-0" />}
            <span className="flex-1">{label}</span>
            {badge > 0 && (
                <span className="ml-auto text-xs font-semibold bg-primary text-white rounded-badge px-1.5 py-0.5 min-w-[20px] text-center">
                    {badge}
                </span>
            )}
        </NavLink>
    );
}

function NavSubItem({ to, label, badge }) {
    return (
        <NavLink
            to={to}
            className={({ isActive }) => `nav-sub-item${isActive ? ' active' : ''}`}
        >
            <span className="flex-1">{label}</span>
            {badge > 0 && (
                <span className="ml-auto text-xs font-semibold bg-primary text-white rounded-badge px-1.5 py-0.5 min-w-[20px] text-center">
                    {badge}
                </span>
            )}
        </NavLink>
    );
}

function NavGroup({ icon: Icon, label, children, defaultOpen = false }) {
    const [open, setOpen] = useState(defaultOpen);
    return (
        <div>
            <button
                onClick={() => setOpen(o => !o)}
                className="nav-item w-full text-left"
            >
                {Icon && <Icon size={17} strokeWidth={1.8} className="flex-shrink-0" />}
                <span className="flex-1">{label}</span>
                {open
                    ? <ChevronUp size={14} className="ml-auto opacity-60" />
                    : <ChevronDown size={14} className="ml-auto opacity-60" />
                }
            </button>
            {open && <div className="bg-black/10">{children}</div>}
        </div>
    );
}

export default function Sidebar({ open, onClose }) {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const [pendingCount, setPendingCount] = useState(0);
    const [organizerEventCount, setOrganizerEventCount] = useState(0);

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    const isAdmin = user?.roles?.includes(ROLES.ADMIN);
    const isOrganizer = user?.roles?.includes(ROLES.ORGANIZER);
    const isUser = !isAdmin && !isOrganizer;

    React.useEffect(() => {
        let active = true;

        if (!isAdmin) {
            setPendingCount(0);
            return undefined;
        }

        getPendingOrganizers({ limit: 1 })
            .then((data) => {
                if (!active) return;
                setPendingCount(data?.counts?.pending ?? data?.total ?? 0);
            })
            .catch(() => {
                if (!active) return;
                setPendingCount(0);
            });

        return () => {
            active = false;
        };
    }, [isAdmin]);

    React.useEffect(() => {
        let active = true;

        if (!isOrganizer) {
            setOrganizerEventCount(0);
            return undefined;
        }

        getOrganizerEvents({ page: 1, perPage: 1 })
            .then((data) => {
                if (!active) return;
                setOrganizerEventCount(data?.total ?? 0);
            })
            .catch(() => {
                if (!active) return;
                setOrganizerEventCount(0);
            });

        return () => {
            active = false;
        };
    }, [isOrganizer]);

    return (
        <>
            {/* Mobile overlay */}
            <div
                className={`sidebar-overlay${open ? ' visible' : ''}`}
                onClick={onClose}
            />

            <aside className={`sidebar${open ? ' sidebar-open' : ''}`}>

                {/* Brand */}
                <div className="sidebar-brand">
                    <div className="sidebar-brand-icon">
                        <Ticket size={18} strokeWidth={2} />
                    </div>
                    <div>
                        <div className="sidebar-brand-name">TicketHub</div>
                        <div className="sidebar-brand-sub">Admin Panel</div>
                    </div>
                </div>

                {/* Nav */}
                <nav className="py-3">
                    {isAdmin && (
                        <>
                            <NavItem to="/admin/dashboard" icon={LayoutDashboard} label="Dashboard" />
                            <NavItem to="/admin/administrators" icon={Shield} label="Administrators" />
                            <NavItem to="/admin/users" icon={Users} label="Users" />
                            <NavGroup icon={Building2} label="Organizers" defaultOpen>
                                <NavSubItem to="/admin/organizers?tab=pending" label="Pending Approval" badge={pendingCount} />
                                <NavSubItem to="/admin/organizers" label="All Organizers" />
                            </NavGroup>
                            <NavGroup icon={CalendarDays} label="Events" defaultOpen>
                                <NavSubItem to="/admin/events" label="All Events" />
                            </NavGroup>
                            <NavItem to="/admin/bookings" icon={BookOpen} label="Bookings" />
                            <NavItem to="/admin/categories" icon={Tag} label="Categories" />
                            <NavItem to="/admin/error-logs" icon={AlertTriangle} label="Error Logs" />
                        </>
                    )}

                    {isOrganizer && (
                        <>
                            <NavItem to="/organizer/dashboard" icon={LayoutDashboard} label="Dashboard" />
                            <NavGroup icon={CalendarDays} label="My Events" defaultOpen>
                                <NavSubItem to="/organizer/events" label="All Events" badge={organizerEventCount} />
                                <NavSubItem to="/organizer/events/new" label="Create New Event" />
                            </NavGroup>
                            <NavItem to="/organizer/checkin" icon={ScanLine} label="Check-In Scanner" />
                        </>
                    )}

                    {isUser && user && (
                        <>
                            <NavItem to="/user/dashboard" icon={LayoutDashboard} label="Dashboard" />
                            <NavItem to="/events" icon={CalendarDays} label="Browse Events" />
                            <NavItem to="/cart" icon={ShoppingCart} label="My Cart" />
                            <NavItem to="/user/bookings" icon={Ticket} label="My Bookings" />
                            <NavItem to="/user/waitlist" icon={Clock} label="My Waitlist" />
                        </>
                    )}

                    {!user && (
                        <>
                            <NavItem to="/events" icon={CalendarDays} label="Browse Events" />
                            <NavItem to="/login" icon={LogIn} label="Login" />
                            <NavItem to="/register" icon={UserPlus} label="Register" />
                        </>
                    )}

                    {/* Divider */}
                    <div className="my-3 mx-5 border-t" style={{ borderColor: '#2D3748' }} />

                    {user && (
                        <button
                            onClick={handleLogout}
                            className="nav-item w-full text-left"
                        >
                            <LogOut size={17} strokeWidth={1.8} className="flex-shrink-0" />
                            <span>Logout</span>
                        </button>
                    )}
                </nav>
            </aside>
        </>
    );
}
