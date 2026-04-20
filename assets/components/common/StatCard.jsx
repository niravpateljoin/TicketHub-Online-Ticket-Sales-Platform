import React from 'react';

export default function StatCard({ icon: Icon, label, value, sub, iconBg, iconColor }) {
    return (
        <div className="stat-card">
            <div
                className="stat-card-icon"
                style={iconBg ? { background: iconBg, color: iconColor ?? '#fff' } : undefined}
            >
                {Icon && <Icon size={22} strokeWidth={1.8} />}
            </div>
            <div>
                <div className="stat-card-label">{label}</div>
                <div className="stat-card-value">{value}</div>
                {sub && <div className="text-xs text-gray-500 mt-0.5">{sub}</div>}
            </div>
        </div>
    );
}
