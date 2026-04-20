import React from 'react';
import { Link } from 'react-router-dom';

export default function OrganizerPendingPage() {
    return (
        <div>
            <h1 className="page-title">Account Status</h1>
            <div className="card" style={{ maxWidth: '520px' }}>
                <div className="card-body text-center" style={{ padding: '48px 40px' }}>
                    <div style={{ fontSize: '56px', marginBottom: '16px' }}>⏳</div>
                    <h2 className="font-bold text-gray-900 mb-3" style={{ fontSize: '20px' }}>
                        Your account is pending approval
                    </h2>
                    <p className="text-sm text-gray-600 mb-4" style={{ lineHeight: 1.7 }}>
                        Our admin team is reviewing your organizer application.
                        You'll receive an email once your account is approved or rejected.
                    </p>
                    <div className="mb-6 p-3 rounded-btn text-sm"
                        style={{ background: '#FFFBEB', border: '1px solid #FAF089', color: '#D69E2E' }}>
                        ⚠ This typically takes 1–2 business days.
                    </div>
                    <Link to="/" className="btn btn-secondary">Back to Home</Link>
                </div>
            </div>
        </div>
    );
}
