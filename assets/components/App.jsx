import React from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { AuthProvider } from '../context/AuthContext';
import { CartProvider } from '../context/CartContext';
import { ToastProvider } from '../context/ToastContext';
import ProtectedRoute from './common/ProtectedRoute';
import Layout from './layout/Layout';
import PublicLayout from './layout/PublicLayout';
import UserLayout from './layout/UserLayout';

// Auth pages (no layout — centered card)
import LoginPage from '../pages/auth/LoginPage';
import RegisterUserPage from '../pages/auth/RegisterUserPage';
import RegisterOrganizerPage from '../pages/auth/RegisterOrganizerPage';
import PendingApprovalPage from '../pages/auth/PendingApprovalPage';
import VerificationPendingPage from '../pages/auth/VerificationPendingPage';
import VerifyEmailPage from '../pages/auth/VerifyEmailPage';
import ForgotPasswordPage from '../pages/auth/ForgotPasswordPage';
import ResetPasswordPage from '../pages/auth/ResetPasswordPage';
import BookingManagementPage from '../pages/admin/BookingManagementPage';
import CheckInPage from '../pages/organizer/CheckInPage';
import WaitlistPage from '../pages/user/WaitlistPage';

// Public pages
import HomePage from '../pages/HomePage';
import EventsPage from '../pages/EventsPage';
import EventDetailPage from '../pages/EventDetailPage';
import NotFoundPage from '../pages/NotFoundPage';
import ForbiddenPage from '../pages/ForbiddenPage';

// User pages
import CartPage from '../pages/CartPage';
import CheckoutPage from '../pages/CheckoutPage';
import CheckoutSuccessPage from '../pages/CheckoutSuccessPage';
import UserDashboardPage from '../pages/user/UserDashboardPage';
import BookingHistoryPage from '../pages/user/BookingHistoryPage';
import UserProfilePage from '../pages/user/UserProfilePage';

// Organizer pages
import OrganizerDashboardPage from '../pages/organizer/OrganizerDashboardPage';
import OrganizerPendingPage from '../pages/organizer/OrganizerPendingPage';
import EventListPage from '../pages/organizer/EventListPage';
import EventCreatePage from '../pages/organizer/EventCreatePage';
import EventEditPage from '../pages/organizer/EventEditPage';
import EventBookingsPage from '../pages/organizer/EventBookingsPage';
import EventRevenuePage from '../pages/organizer/EventRevenuePage';

// Admin pages
import AdminDashboardPage from '../pages/admin/AdminDashboardPage';
import AdministratorManagementPage from '../pages/admin/AdministratorManagementPage';
import OrganizerManagementPage from '../pages/admin/OrganizerManagementPage';
import AllEventsPage from '../pages/admin/AllEventsPage';
import ErrorLogPage from '../pages/admin/ErrorLogPage';
import UserManagementPage from '../pages/admin/UserManagementPage';
import CategoryManagementPage from '../pages/admin/CategoryManagementPage';

import { ROLES } from '../utils/constants';

function WithLayout({ children }) {
    return <Layout>{children}</Layout>;
}

function WithUserLayout({ children }) {
    return <UserLayout>{children}</UserLayout>;
}

function WithPublicLayout({ children, contained = false }) {
    return (
        <PublicLayout>
            {contained
                ? <div style={{ maxWidth: '1280px', margin: '0 auto', padding: '32px 24px' }}>{children}</div>
                : children}
        </PublicLayout>
    );
}

export default function App() {
    return (
        <AuthProvider>
            <CartProvider>
                <ToastProvider>
                    <BrowserRouter>
                        <Routes>
                            {/* ── Auth pages (no sidebar layout) ── */}
                            <Route path="/login" element={<LoginPage />} />
                            <Route path="/register" element={<RegisterUserPage />} />
                            <Route path="/register/organizer" element={<RegisterOrganizerPage />} />
                            <Route path="/pending-approval" element={<PendingApprovalPage />} />
                            <Route path="/verification-pending" element={<VerificationPendingPage />} />
                            <Route path="/verify-email" element={<VerifyEmailPage />} />
                            <Route path="/forgot-password" element={<ForgotPasswordPage />} />
                            <Route path="/reset-password" element={<ResetPasswordPage />} />
                            <Route path="/forbidden" element={<ForbiddenPage />} />

                            {/* ── Public pages (website layout — no sidebar) ── */}
                            <Route path="/" element={<WithPublicLayout><HomePage /></WithPublicLayout>} />
                            <Route path="/events" element={<WithPublicLayout contained><EventsPage /></WithPublicLayout>} />
                            <Route path="/events/:slug" element={<WithPublicLayout contained><EventDetailPage /></WithPublicLayout>} />

                            {/* ── User pages ── */}
                            <Route element={<ProtectedRoute role={ROLES.USER} />}>
                                <Route path="/cart"
                                    element={<WithPublicLayout contained><CartPage /></WithPublicLayout>} />
                                <Route path="/checkout"
                                    element={<WithPublicLayout contained><CheckoutPage /></WithPublicLayout>} />
                                <Route path="/checkout/success/:bookingId"
                                    element={<WithPublicLayout contained><CheckoutSuccessPage /></WithPublicLayout>} />
                                <Route path="/user/dashboard"
                                    element={<WithUserLayout><UserDashboardPage /></WithUserLayout>} />
                                <Route path="/user/bookings"
                                    element={<WithUserLayout><BookingHistoryPage /></WithUserLayout>} />
                                <Route path="/user/profile"
                                    element={<WithUserLayout><UserProfilePage /></WithUserLayout>} />
                                <Route path="/user/waitlist"
                                    element={<WithUserLayout><WaitlistPage /></WithUserLayout>} />
                            </Route>

                            {/* ── Organizer pages ── */}
                            <Route element={<ProtectedRoute role={ROLES.ORGANIZER} />}>
                                <Route path="/organizer/dashboard"
                                    element={<WithLayout><OrganizerDashboardPage /></WithLayout>} />
                                <Route path="/organizer/pending"
                                    element={<WithLayout><OrganizerPendingPage /></WithLayout>} />
                                <Route path="/organizer/events"
                                    element={<WithLayout><EventListPage /></WithLayout>} />
                                <Route path="/organizer/events/new"
                                    element={<WithLayout><EventCreatePage /></WithLayout>} />
                                <Route path="/organizer/events/:id/edit"
                                    element={<WithLayout><EventEditPage /></WithLayout>} />
                                <Route path="/organizer/events/:id/bookings"
                                    element={<WithLayout><EventBookingsPage /></WithLayout>} />
                                <Route path="/organizer/events/:id/revenue"
                                    element={<WithLayout><EventRevenuePage /></WithLayout>} />
                                <Route path="/organizer/checkin"
                                    element={<WithLayout><CheckInPage /></WithLayout>} />
                            </Route>

                            {/* ── Admin pages ── */}
                            <Route element={<ProtectedRoute role={ROLES.ADMIN} />}>
                                <Route path="/admin/dashboard"
                                    element={<WithLayout><AdminDashboardPage /></WithLayout>} />
                                <Route path="/admin/administrators"
                                    element={<WithLayout><AdministratorManagementPage /></WithLayout>} />
                                <Route path="/admin/organizers"
                                    element={<WithLayout><OrganizerManagementPage /></WithLayout>} />
                                <Route path="/admin/events"
                                    element={<WithLayout><AllEventsPage /></WithLayout>} />
                                <Route path="/admin/events/:id/edit"
                                    element={<WithLayout><EventEditPage /></WithLayout>} />
                                <Route path="/admin/users"
                                    element={<WithLayout><UserManagementPage /></WithLayout>} />
                                <Route path="/admin/categories"
                                    element={<WithLayout><CategoryManagementPage /></WithLayout>} />
                                <Route path="/admin/error-logs"
                                    element={<WithLayout><ErrorLogPage /></WithLayout>} />
                                <Route path="/admin/bookings"
                                    element={<WithLayout><BookingManagementPage /></WithLayout>} />
                            </Route>

                            <Route path="*" element={<NotFoundPage />} />
                        </Routes>
                    </BrowserRouter>
                </ToastProvider>
            </CartProvider>
        </AuthProvider>
    );
}
