import React from 'react';

const S = ({ w = '100%', h = 16, radius = 6, mb = 0, style = {} }) => (
    <div style={{
        width: w, height: h, borderRadius: radius,
        background: '#E2E8F0', marginBottom: mb, flexShrink: 0, ...style,
    }} />
);

/* Wrapper that applies animate-pulse to all children */
export function SkeletonGroup({ children, style = {} }) {
    return (
        <div className="animate-pulse" style={style}>
            {children}
        </div>
    );
}

/* Generic block */
export function SkeletonBlock({ w, h, radius, mb, style }) {
    return <S w={w} h={h} radius={radius} mb={mb} style={style} />;
}

/* One text line */
export function SkeletonLine({ w = '100%', h = 14, mb = 10 }) {
    return <S w={w} h={h} mb={mb} />;
}

/* A card-shaped stat box (used on Dashboard) */
export function SkeletonStatCard() {
    return (
        <div className="card" style={{ padding: '20px 24px' }}>
            <S w={32} h={32} radius={8} mb={12} />
            <S w="55%" h={12} mb={8} />
            <S w="35%" h={22} mb={6} />
            <S w="70%" h={11} />
        </div>
    );
}

/* A table row placeholder */
export function SkeletonTableRows({ cols = 5, rows = 4 }) {
    return Array.from({ length: rows }).map((_, r) => (
        <tr key={r} style={{ borderBottom: '1px solid #F1F5F9' }}>
            {Array.from({ length: cols }).map((_, c) => (
                <td key={c} style={{ padding: '14px 16px' }}>
                    <S h={13} w={c === 0 ? '60%' : c === cols - 1 ? '40%' : '75%'} mb={0} />
                </td>
            ))}
        </tr>
    ));
}

/* Event detail page skeleton */
export function SkeletonEventDetail() {
    return (
        <SkeletonGroup>
            {/* Banner */}
            <S h={320} radius={8} mb={24} />

            {/* Badges + title */}
            <div style={{ display: 'flex', gap: 8, marginBottom: 10 }}>
                <S w={70} h={22} radius={20} />
                <S w={90} h={22} radius={20} />
            </div>
            <S w="60%" h={32} radius={6} mb={24} />

            {/* Two-column grid */}
            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) 360px', gap: 24, alignItems: 'start' }}>
                {/* Left — info card */}
                <div>
                    <div className="card" style={{ padding: '24px', marginBottom: 16 }}>
                        <S w="40%" h={16} mb={20} />
                        {[1, 2, 3].map(i => (
                            <div key={i} style={{ display: 'flex', gap: 12, marginBottom: 20 }}>
                                <S w={18} h={18} radius={4} style={{ flexShrink: 0, marginTop: 2 }} />
                                <div style={{ flex: 1 }}>
                                    <S w="30%" h={13} mb={6} />
                                    <S w="60%" h={12} mb={0} />
                                </div>
                            </div>
                        ))}
                    </div>
                    <div className="card" style={{ padding: '24px' }}>
                        <S w="35%" h={16} mb={16} />
                        <S h={12} mb={8} />
                        <S h={12} mb={8} w="90%" />
                        <S h={12} w="75%" />
                    </div>
                </div>

                {/* Right — ticket tiers card */}
                <div className="card" style={{ padding: '24px' }}>
                    <S w="50%" h={16} mb={20} />
                    {[1, 2].map(i => (
                        <div key={i} style={{ padding: '16px', border: '1px solid #E2E8F0', borderRadius: 8, marginBottom: 12 }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 10 }}>
                                <S w="40%" h={14} mb={0} />
                                <S w="25%" h={14} mb={0} />
                            </div>
                            <S w="55%" h={12} mb={14} />
                            <S h={36} radius={8} />
                        </div>
                    ))}
                </div>
            </div>
        </SkeletonGroup>
    );
}

/* Checkout page skeleton */
export function SkeletonCheckout() {
    return (
        <SkeletonGroup>
            <S w="40%" h={28} radius={6} mb={24} />
            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) 340px', gap: 24, alignItems: 'start' }}>
                {/* Order summary card */}
                <div className="card" style={{ padding: '24px' }}>
                    <S w="45%" h={16} mb={20} />
                    {[1, 2].map(i => (
                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: '14px 0', borderBottom: '1px solid #F1F5F9' }}>
                            <div style={{ flex: 1 }}>
                                <S w="65%" h={14} mb={8} />
                                <S w="40%" h={12} mb={0} />
                            </div>
                            <S w={60} h={14} mb={0} />
                        </div>
                    ))}
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 16 }}>
                        <S w="30%" h={16} mb={0} />
                        <S w="20%" h={16} mb={0} />
                    </div>
                </div>

                {/* Payment card */}
                <div className="card" style={{ padding: '24px' }}>
                    <S w="50%" h={16} mb={20} />
                    <S h={48} radius={8} mb={12} />
                    <S h={48} radius={8} mb={16} />
                    <S h={44} radius={8} />
                </div>
            </div>
        </SkeletonGroup>
    );
}

/* Waitlist page skeleton */
export function SkeletonWaitlist({ rows = 3 }) {
    return (
        <SkeletonGroup>
            <S w="35%" h={22} mb={8} />
            <S w="55%" h={13} mb={32} />
            {Array.from({ length: rows }).map((_, i) => (
                <div key={i} className="card" style={{ padding: '20px 24px', marginBottom: 12 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                        <div style={{ flex: 1 }}>
                            <S w="50%" h={15} mb={8} />
                            <S w="35%" h={12} mb={6} />
                            <S w="25%" h={12} mb={0} />
                        </div>
                        <S w={70} h={28} radius={6} mb={0} />
                    </div>
                </div>
            ))}
        </SkeletonGroup>
    );
}
