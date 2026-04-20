import React from 'react';
import { Link } from 'react-router-dom';

export default function ForbiddenPage() {
    return (
        <div className="min-h-screen flex items-center justify-center" style={{ background: '#F7F8FA' }}>
            <div className="text-center" style={{ maxWidth: '400px', padding: '40px 20px' }}>
                <div style={{ fontSize: '72px', marginBottom: '16px' }}>🚫</div>
                <h1 className="font-bold text-gray-900 mb-2" style={{ fontSize: '36px' }}>403</h1>
                <h2 className="font-semibold text-gray-700 mb-3" style={{ fontSize: '20px' }}>Access Forbidden</h2>
                <p className="text-sm text-gray-500 mb-6">
                    You don't have permission to view this page.
                </p>
                <div className="flex items-center justify-center gap-3">
                    <Link to="/" className="btn btn-primary">Go Home</Link>
                    <Link to="/login" className="btn btn-secondary">Login</Link>
                </div>
            </div>
        </div>
    );
}
