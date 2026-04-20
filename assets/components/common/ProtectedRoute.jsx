import React, { useContext } from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { AuthContext } from '../../context/AuthContext';
import { APPROVAL_STATUS } from '../../utils/constants';

export default function ProtectedRoute({ role }) {
    const { user, loading } = useContext(AuthContext);
    const location = useLocation();

    if (loading) {
        return (
            <div className="page-loader">
                <div className="page-loader-box">
                    <div className="page-loader-logo">
                        <span className="page-loader-dot" />
                        <span className="page-loader-brand">TicketHub</span>
                    </div>
                    <div className="page-loader-spinner" />
                    <p className="page-loader-text">Loading your workspace…</p>
                </div>
            </div>
        );
    }

    if (!user) {
        return <Navigate to="/login" state={{ from: location }} replace />;
    }

    // Pending organizers are locked out of ALL protected routes
    if (
        user.roles.includes('ROLE_ORGANIZER') &&
        user.approvalStatus !== APPROVAL_STATUS.APPROVED
    ) {
        return <Navigate to="/pending-approval" replace />;
    }

    if (role && !user.roles.includes(role)) {
        return <Navigate to="/" replace />;
    }

    if (
        role === 'ROLE_ADMIN' &&
        user.isVerified === false &&
        location.pathname !== '/verification-pending'
    ) {
        return <Navigate to="/verification-pending" replace />;
    }

    return <Outlet />;
}
