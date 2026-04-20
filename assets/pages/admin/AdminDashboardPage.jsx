import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Users, CalendarDays, Ticket, IndianRupee } from 'lucide-react';
import { getAdminStats, getPendingOrganizers, getRecentErrors } from '../../api/adminApi';
import StatCard from '../../components/common/StatCard';
import Badge from '../../components/common/Badge';

export default function AdminDashboardPage() {
    const [stats, setStats] = useState(null);
    const [pendingOrganizers, setPendingOrganizers] = useState([]);
    const [recentErrors, setRecentErrors] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        Promise.all([
            getAdminStats().catch(() => null),
            getPendingOrganizers({ limit: 5 }).catch(() => ({ items: [] })),
            getRecentErrors({ limit: 5 }).catch(() => ({ items: [] })),
        ]).then(([s, orgs, errs]) => {
            setStats(s);
            setPendingOrganizers(orgs?.items ?? orgs ?? []);
            setRecentErrors(errs?.items ?? errs ?? []);
        }).finally(() => setLoading(false));
    }, []);

    return (
        <div>
            <h1 className="page-title">Dashboard</h1>

            {/* Stat cards */}
            <div className="grid gap-4 mb-6" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))' }}>
                <StatCard icon={Users}        label="Registered Users" value={stats?.totalUsers ?? '—'}        iconBg="#EEF2FF" iconColor="#6366F1" />
                <StatCard icon={CalendarDays} label="Total Events"     value={stats?.totalEvents ?? '—'}       iconBg="#FFF7ED" iconColor="#F97316" />
                <StatCard icon={Ticket}       label="Tickets Sold"     value={stats?.totalTicketsSold ?? '—'}  iconBg="#FFF1F2" iconColor="#F43F5E" />
                <StatCard icon={IndianRupee}  label="System Revenue"   value={stats?.totalSystemRevenue != null ? `${stats.totalSystemRevenue.toLocaleString()} cr` : '—'} iconBg="#F0FDF4" iconColor="#22C55E" />
            </div>

            {/* Sales overview chart placeholder */}
            <div className="card mb-6">
                <div className="card-header">
                    Sales Overview
                    <div className="flex gap-2">
                        <button className="btn btn-secondary btn-sm">2 Weeks</button>
                        <button className="btn btn-primary btn-sm">Month</button>
                        <button className="btn btn-secondary btn-sm">Year</button>
                    </div>
                </div>
                <div className="card-body">
                    <div className="chart-placeholder">
                        📊 Sales chart will appear here
                    </div>
                </div>
            </div>

            {/* Two-column: pending organizers + recent errors */}
            <div className="grid gap-6" style={{ gridTemplateColumns: '1fr 1fr' }}>
                {/* Pending organizers */}
                <div className="card">
                    <div className="card-header">
                        Pending Organizers
                        <Link to="/admin/organizers?tab=pending" className="btn btn-ghost btn-sm">View all →</Link>
                    </div>
                    {loading ? (
                        <div className="card-body text-sm text-gray-400">Loading…</div>
                    ) : pendingOrganizers.length === 0 ? (
                        <div className="card-body">
                            <div className="empty-state" style={{ padding: '20px' }}>
                                <div style={{ fontSize: '28px' }}>✅</div>
                                <div className="text-sm text-gray-500 mt-2">No pending approvals</div>
                            </div>
                        </div>
                    ) : (
                        <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Registered</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {pendingOrganizers.map(org => (
                                        <tr key={org.id}>
                                            <td>
                                                <div className="font-medium text-sm">{org.organizationName ?? org.email}</div>
                                                <div className="text-xs text-gray-400">{org.email}</div>
                                            </td>
                                            <td className="text-sm text-gray-500">
                                                {org.createdAt ? new Date(org.createdAt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' }) : '—'}
                                            </td>
                                            <td>
                                                <Link to={`/admin/organizers`} className="btn btn-ghost btn-sm">Review</Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Recent errors */}
                <div className="card">
                    <div className="card-header">
                        Recent Errors
                        <Link to="/admin/error-logs" className="btn btn-ghost btn-sm">View all →</Link>
                    </div>
                    {loading ? (
                        <div className="card-body text-sm text-gray-400">Loading…</div>
                    ) : recentErrors.length === 0 ? (
                        <div className="card-body">
                            <div className="empty-state" style={{ padding: '20px' }}>
                                <div style={{ fontSize: '28px' }}>✅</div>
                                <div className="text-sm text-gray-500 mt-2">No recent errors</div>
                            </div>
                        </div>
                    ) : (
                        <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Route</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentErrors.map(err => (
                                        <tr key={err.id}>
                                            <td className="text-xs text-gray-500">
                                                {err.createdAt ? new Date(err.createdAt).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—'}
                                            </td>
                                            <td>
                                                <span className="badge badge-cancelled">{err.statusCode ?? 500}</span>
                                            </td>
                                            <td className="text-xs font-mono text-gray-600 truncate" style={{ maxWidth: '120px' }}>
                                                {err.route ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
