import React from 'react';

export default function Spinner({ className = '', size = 24 }) {
    return (
        <div className={`flex items-center justify-center ${className}`}>
            <div
                className="rounded-full animate-spin"
                style={{
                    width: size,
                    height: size,
                    border: `2.5px solid #E6F8F4`,
                    borderTopColor: '#2EC4A1',
                }}
            />
        </div>
    );
}
