import React from 'react';
import { Link } from 'react-router-dom';

export default function PendingApprovalPage() {
    return (
        <div className="min-h-screen flex items-center justify-center" style={{ background: '#F7F8FA' }}>
            <div className="card text-center" style={{ maxWidth: '480px', width: '100%', margin: '16px', padding: '48px 40px', borderRadius: '12px', boxShadow: '0 4px 24px rgba(0,0,0,0.08)' }}>
                <div style={{ fontSize: '56px', marginBottom: '16px' }}>⏳</div>
                <h1 className="font-bold text-gray-900 mb-3" style={{ fontSize: '22px' }}>
                    Application Under Review
                </h1>
                <p className="text-sm text-gray-600 mb-4" style={{ lineHeight: 1.7 }}>
                    Your organizer application has been submitted successfully.
                    Our team will review your details and get back to you via email.
                </p>
                <div className="mb-6 p-3 rounded-btn text-sm"
                    style={{ background: '#FFFBEB', border: '1px solid #FAF089', color: '#D69E2E' }}>
                    ⚠ This usually takes 1–2 business days.
                </div>
                <Link to="/login" className="btn btn-secondary btn-full">
                    Back to Login
                </Link>
            </div>
        </div>
    );
}
