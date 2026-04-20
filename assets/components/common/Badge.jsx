import React from 'react';

const statusMap = {
    active:      'badge-active',
    pending:     'badge-pending',
    'sold out':  'badge-sold-out',
    sold_out:    'badge-sold-out',
    cancelled:   'badge-cancelled',
    canceled:    'badge-cancelled',
    confirmed:   'badge-confirmed',
    refunded:    'badge-refunded',
    postponed:   'badge-postponed',
    rejected:    'badge-rejected',
    approved:    'badge-approved',
    deactivated: 'badge-deactivated',
    inactive:    'badge-deactivated',
    draft:       'badge-pending',
    published:   'badge-active',
};

export default function Badge({ status, label, className = '' }) {
    const key = (status || '').toLowerCase().replace(/ /g, '_');
    const cls = statusMap[key] || 'badge-pending';
    const text = label ?? (status ? status.charAt(0).toUpperCase() + status.slice(1) : '');

    return (
        <span className={`badge ${cls} ${className}`}>
            {text}
        </span>
    );
}
