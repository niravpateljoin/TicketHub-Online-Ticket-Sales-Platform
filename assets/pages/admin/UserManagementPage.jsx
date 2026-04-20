import React, { useEffect, useState } from 'react';
import { Users, Search, Wallet, CheckCircle2, Clock } from 'lucide-react';
import { getUsers } from '../../api/adminApi';
import Pagination from '../../components/common/Pagination';

export default function UserManagementPage() {
    const [users, setUsers]           = useState([]);
    const [loading, setLoading]       = useState(true);
    const [page, setPage]             = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [total, setTotal]           = useState(0);
    const [search, setSearch]         = useState('');
    const [inputValue, setInputValue] = useState('');

    const fetchUsers = () => {
        setLoading(true);
        getUsers({ page, search, perPage: 15 })
            .then((data) => {
                setUsers(data.items ?? []);
                setTotalPages(data.totalPages ?? 1);
                setTotal(data.total ?? 0);
            })
            .catch(() => {
                setUsers([]);
                setTotalPages(1);
                setTotal(0);
            })
            .finally(() => setLoading(false));
    };

    useEffect(() => { fetchUsers(); }, [page, search]);
    useEffect(() => { setPage(1); }, [search]);

    // Debounce search input
    useEffect(() => {
        const t = setTimeout(() => setSearch(inputValue.trim()), 300);
        return () => clearTimeout(t);
    }, [inputValue]);

    const fmt = (iso) => iso
        ? new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
        : '—';

    return (
        <div>
            {/* Header */}
            <div className="flex flex-wrap items-center justify-between gap-3 mb-6">
                <div>
                    <h1 className="page-title mb-1">Users</h1>
                    <p className="text-sm text-gray-500">View and manage registered user accounts.</p>
                </div>
                <div className="user-mgmt-stat">
                    <Users size={15} strokeWidth={1.8} className="text-primary" />
                    <span className="text-sm font-semibold text-gray-700">{total}</span>
                    <span className="text-sm text-gray-400">registered</span>
                </div>
            </div>

            {/* Search */}
            <div className="card mb-4">
                <div className="card-body" style={{ padding: '12px 20px' }}>
                    <div className="user-mgmt-search-row">
                        <div className="user-mgmt-search-wrap">
                            <Search size={14} className="user-mgmt-search-icon" />
                            <input
                                className="form-input"
                                style={{ paddingLeft: '34px' }}
                                placeholder="Search by name or email…"
                                value={inputValue}
                                onChange={(e) => setInputValue(e.target.value)}
                            />
                        </div>
                        <div className="text-sm text-gray-500">
                            Total users: <span className="font-semibold text-gray-700">{total}</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Table */}
            <div className="card">
                {loading ? (
                    <div className="card-body text-sm text-gray-400">Loading…</div>
                ) : users.length === 0 ? (
                    <div className="empty-state">
                        <div className="empty-state-icon-wrap">
                            <Users size={32} strokeWidth={1.4} className="empty-state-svg-icon" />
                        </div>
                        <div className="empty-state-title">No users found</div>
                        <div className="empty-state-text">
                            {search ? `No users matching "${search}".` : 'No registered users yet.'}
                        </div>
                    </div>
                ) : (
                    <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Verification</th>
                                    <th>Credits</th>
                                    <th>Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.map((u) => (
                                    <tr key={u.id}>
                                        <td>
                                            <div className="user-mgmt-cell">
                                                <div className="user-mgmt-avatar">
                                                    {(u.name || u.email || 'U').slice(0, 2).toUpperCase()}
                                                </div>
                                                <div>
                                                    <div className="font-medium text-sm text-gray-800">
                                                        {u.name || <span className="text-gray-400 italic">No name</span>}
                                                    </div>
                                                    <div className="text-xs text-gray-500 mt-0.5">{u.email}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            {u.isVerified ? (
                                                <span className="badge badge-approved">
                                                    <CheckCircle2 size={11} strokeWidth={2.2} style={{ display: 'inline', marginRight: 4 }} />
                                                    Verified
                                                </span>
                                            ) : (
                                                <span className="badge badge-pending">
                                                    <Clock size={11} strokeWidth={2.2} style={{ display: 'inline', marginRight: 4 }} />
                                                    Pending
                                                </span>
                                            )}
                                        </td>
                                        <td>
                                            <div className="user-mgmt-credits">
                                                <Wallet size={13} strokeWidth={1.8} className="text-gray-400" />
                                                <span className="text-sm font-medium text-gray-700">
                                                    {(u.creditBalance ?? 0).toLocaleString()} cr
                                                </span>
                                            </div>
                                        </td>
                                        <td className="text-sm text-gray-500">{fmt(u.createdAt)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
        </div>
    );
}
