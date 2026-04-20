import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { useCart } from '../../hooks/useCart';
import { ROLES } from '../../utils/constants';

export default function Navbar() {
    const { user, logout } = useAuth();
    const { itemCount } = useCart();
    const navigate = useNavigate();

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    const dashboardLink = () => {
        if (!user) return null;
        if (user.roles.includes(ROLES.ADMIN)) return '/admin/dashboard';
        if (user.roles.includes(ROLES.ORGANIZER)) return '/organizer/dashboard';
        return '/user/dashboard';
    };

    return (
        <nav className="bg-white shadow-sm border-b border-gray-200 px-6 py-3 flex items-center justify-between">
            <Link to="/" className="text-xl font-bold text-indigo-600">TicketHub</Link>

            <div className="flex items-center gap-6 text-sm font-medium text-gray-600">
                <Link to="/events" className="hover:text-indigo-600">Events</Link>

                {user ? (
                    <>
                        <Link to="/cart" className="relative hover:text-indigo-600">
                            Cart
                            {itemCount > 0 && (
                                <span className="ml-1 bg-indigo-600 text-white text-xs rounded-full px-1.5 py-0.5">
                                    {itemCount}
                                </span>
                            )}
                        </Link>
                        <Link to={dashboardLink()} className="hover:text-indigo-600">Dashboard</Link>
                        <button onClick={handleLogout} className="hover:text-red-500">Logout</button>
                    </>
                ) : (
                    <>
                        <Link to="/login" className="hover:text-indigo-600">Login</Link>
                        <Link to="/register" className="bg-indigo-600 text-white px-3 py-1.5 rounded hover:bg-indigo-700">
                            Sign Up
                        </Link>
                    </>
                )}
            </div>
        </nav>
    );
}
