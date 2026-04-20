import React from 'react';
import PublicNavbar from './PublicNavbar';
import { Link } from 'react-router-dom';
import { Ticket } from 'lucide-react';

export default function PublicLayout({ children }) {
    return (
        <div className="pub-layout">
            <PublicNavbar />
            <main className="pub-main">
                {children}
            </main>
            <footer className="pub-footer">
                <div className="pub-footer-inner">
                    <div className="pub-footer-brand">
                       
                        <span className="pub-footer-brand-name">TicketHub</span>
                    </div>
                    <div className="pub-footer-links">
                        <Link to="/events">Browse Events</Link>
                        <Link to="/register">Sign Up</Link>
                        <Link to="/register/organizer">Become an Organizer</Link>
                        <Link to="/login">Login</Link>
                    </div>
                    <p className="pub-footer-copy">© {new Date().getFullYear()} TicketHub. All rights reserved.</p>
                </div>
            </footer>
        </div>
    );
}
