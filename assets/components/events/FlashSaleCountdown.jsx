import React, { useEffect, useState } from 'react';
import { Zap } from 'lucide-react';

export default function FlashSaleCountdown({ saleStartsAt }) {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const id = setInterval(() => setNow(Date.now()), 1000);
        return () => clearInterval(id);
    }, []);

    const diff = Math.max(0, Math.floor((new Date(saleStartsAt).getTime() - now) / 1000));

    if (diff === 0) {
        return (
            <span style={{ color: '#2EC4A1', fontWeight: 600 }}>
                <Zap size={12} style={{ display: 'inline', marginRight: 4 }} />
                Sale just opened!
            </span>
        );
    }

    const h = Math.floor(diff / 3600);
    const m = Math.floor((diff % 3600) / 60);
    const s = diff % 60;
    const pad = (n) => String(n).padStart(2, '0');

    return (
        <span style={{ color: '#D69E2E', fontWeight: 600 }}>
            <Zap size={12} style={{ display: 'inline', marginRight: 4 }} />
            Sale opens in:{' '}
            {h > 0 && `${h}h `}{pad(m)}m {pad(s)}s
        </span>
    );
}
