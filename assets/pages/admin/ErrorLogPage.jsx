import React, { useState, useEffect } from 'react';
import { getErrorLogs, resolveErrorLog } from '../../api/adminApi';
import Pagination from '../../components/common/Pagination';
import { useToast } from '../../context/ToastContext';

export default function ErrorLogPage() {
    const { success, error: showError } = useToast();
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [filters, setFilters] = useState({ dateFrom: '', dateTo: '', route: '', unresolvedOnly: false });
    const [selected, setSelected] = useState(null);
    const [adminNote, setAdminNote] = useState('');
    const [resolving, setResolving] = useState(false);

    const fetchLogs = () => {
        setLoading(true);
        getErrorLogs({ page, ...filters })
            .then(data => {
                setLogs(data.items ?? data ?? []);
                setTotalPages(data.totalPages ?? 1);
            })
            .catch(() => setLogs([]))
            .finally(() => setLoading(false));
    };

    useEffect(fetchLogs, [page, filters]);

    const handleResolve = async (logId) => {
        setResolving(true);
        try {
            await resolveErrorLog(logId, adminNote);
            success('Error log marked as resolved.');
            setSelected(null);
            setAdminNote('');
            fetchLogs();
        } catch (err) {
            showError(err.response?.data?.message ?? 'Could not resolve.');
        } finally {
            setResolving(false);
        }
    };

    return (
        <div>
            <h1 className="page-title">Error Logs</h1>

            {/* Filter bar */}
            <div className="card mb-4">
                <div className="card-body" style={{ padding: '12px 20px' }}>
                    <div className="flex flex-wrap items-center gap-3">
                        <div>
                            <label className="form-label" style={{ fontSize: '12px' }}>Date From</label>
                            <input type="date" className="form-input" style={{ padding: '6px 10px' }}
                                value={filters.dateFrom}
                                onChange={e => setFilters(f => ({ ...f, dateFrom: e.target.value }))} />
                        </div>
                        <div>
                            <label className="form-label" style={{ fontSize: '12px' }}>Date To</label>
                            <input type="date" className="form-input" style={{ padding: '6px 10px' }}
                                value={filters.dateTo}
                                onChange={e => setFilters(f => ({ ...f, dateTo: e.target.value }))} />
                        </div>
                        <div>
                            <label className="form-label" style={{ fontSize: '12px' }}>Route</label>
                            <input className="form-input" placeholder="/api/…" style={{ padding: '6px 10px', width: '160px' }}
                                value={filters.route}
                                onChange={e => setFilters(f => ({ ...f, route: e.target.value }))} />
                        </div>
                        <label className="flex items-center gap-2 cursor-pointer mt-4">
                            <input type="checkbox" className="w-4 h-4 accent-primary"
                                checked={filters.unresolvedOnly}
                                onChange={e => setFilters(f => ({ ...f, unresolvedOnly: e.target.checked }))} />
                            <span className="text-sm font-medium text-gray-700">Unresolved only</span>
                        </label>
                    </div>
                </div>
            </div>

            <div className="grid gap-6" style={{ gridTemplateColumns: selected ? '1fr 380px' : '1fr', alignItems: 'start' }}>
                {/* Logs table */}
                <div className="card">
                    {loading ? (
                        <div className="card-body text-sm text-gray-400">Loading…</div>
                    ) : logs.length === 0 ? (
                        <div className="empty-state">
                            <div className="empty-state-icon">✅</div>
                            <div className="empty-state-title">No error logs found</div>
                        </div>
                    ) : (
                        <div className="table-wrapper" style={{ border: 'none', borderRadius: '0' }}>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Route</th>
                                        <th>Exception</th>
                                        <th>User</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.map(log => (
                                        <tr key={log.id}
                                            style={{ cursor: 'pointer', background: selected?.id === log.id ? '#EBF8FF' : undefined }}
                                            onClick={() => { setSelected(log); setAdminNote(''); }}>
                                            <td className="text-xs text-gray-500 whitespace-nowrap">
                                                {log.createdAt
                                                    ? new Date(log.createdAt).toLocaleString('en-IN', {
                                                        day: 'numeric', month: 'short',
                                                        hour: '2-digit', minute: '2-digit'
                                                    })
                                                    : '—'}
                                            </td>
                                            <td>
                                                <span className="badge badge-cancelled">{log.statusCode ?? 500}</span>
                                            </td>
                                            <td className="text-xs font-mono text-gray-600 max-w-xs truncate">
                                                {log.route ?? '—'}
                                            </td>
                                            <td className="text-xs text-gray-700 max-w-xs truncate">
                                                {log.exceptionClass ?? log.message ?? '—'}
                                            </td>
                                            <td className="text-xs text-gray-500">
                                                {log.userId ? `#${log.userId}` : '—'}
                                            </td>
                                            <td onClick={e => e.stopPropagation()}>
                                                <div className="flex gap-2">
                                                    <button
                                                        className="btn btn-secondary btn-sm"
                                                        onClick={() => { setSelected(log); setAdminNote(''); }}
                                                    >View</button>
                                                    {!log.resolved && (
                                                        <button
                                                            className="btn btn-primary btn-sm"
                                                            onClick={() => handleResolve(log.id)}
                                                        >✓ Resolve</button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Detail drawer */}
                {selected && (
                    <div className="card" style={{ position: 'sticky', top: '80px' }}>
                        <div className="card-header">
                            Error Detail
                            <button onClick={() => setSelected(null)} className="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
                        </div>
                        <div className="card-body space-y-4" style={{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }}>
                            <div>
                                <div className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Route</div>
                                <code className="text-sm font-mono text-gray-800">{selected.route}</code>
                            </div>
                            <div>
                                <div className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Exception</div>
                                <div className="text-sm text-red-600 font-mono">{selected.exceptionClass ?? '—'}</div>
                            </div>
                            <div>
                                <div className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Message</div>
                                <div className="text-sm text-gray-700 break-words">{selected.message ?? '—'}</div>
                            </div>
                            {selected.stackTrace && (
                                <div>
                                    <div className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Stack Trace</div>
                                    <details>
                                        <summary className="text-xs text-primary cursor-pointer">Show trace</summary>
                                        <pre className="mt-2 text-xs text-gray-600 overflow-x-auto whitespace-pre-wrap p-3 rounded-btn"
                                            style={{ background: '#F7F8FA', border: '1px solid #E2E8F0', maxHeight: '200px' }}>
                                            {selected.stackTrace}
                                        </pre>
                                    </details>
                                </div>
                            )}
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <div className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">User ID</div>
                                    <div className="text-sm">{selected.userId ? `#${selected.userId}` : '—'}</div>
                                </div>
                                <div>
                                    <div className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">IP</div>
                                    <div className="text-sm font-mono">{selected.ip ?? '—'}</div>
                                </div>
                            </div>

                            {!selected.resolved && (
                                <div>
                                    <label className="form-label">Admin Note</label>
                                    <textarea className="form-input" rows={2}
                                        placeholder="Optional note…"
                                        value={adminNote}
                                        onChange={e => setAdminNote(e.target.value)} />
                                    <button
                                        className="btn btn-primary btn-full mt-2"
                                        onClick={() => handleResolve(selected.id)}
                                        disabled={resolving}
                                    >
                                        {resolving ? 'Resolving…' : '✓ Mark Resolved'}
                                    </button>
                                </div>
                            )}
                            {selected.resolved && (
                                <div className="p-2 rounded-btn text-sm"
                                    style={{ background: '#F0FFF4', color: '#38A169', border: '1px solid #C6F6D5' }}>
                                    ✅ This error has been resolved.
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
        </div>
    );
}
