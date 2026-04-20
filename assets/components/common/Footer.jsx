import React from 'react';

export default function Footer() {
    return (
        <footer className="bg-gray-50 border-t border-gray-200 mt-auto py-6 px-6 text-center text-sm text-gray-500">
            &copy; {new Date().getFullYear()} TicketHub. All rights reserved.
        </footer>
    );
}
