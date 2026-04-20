import React, { useEffect, useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import { getBooking } from '../api/bookingsApi';
import { useAuth } from '../hooks/useAuth';
import api from '../hooks/useApi';
import { downloadBlobResponse } from '../utils/download';

export default function CheckoutSuccessPage() {
    const { bookingId } = useParams();
    const { state } = useLocation();
    const { user } = useAuth();
    const [booking, setBooking] = useState(null);
    const [downloading, setDownloading] = useState(false);

    useEffect(() => {
        if (bookingId) {
            getBooking(bookingId)
                .then(setBooking)
                .catch(() => {});
        }
    }, [bookingId]);

    const handleDownload = async () => {
        if (!bookingId) {
            return;
        }

        setDownloading(true);
        try {
            const response = await api.get(`/bookings/${bookingId}/ticket`, { responseType: 'blob' });
            downloadBlobResponse(response, `booking-${bookingId}-ticket.pdf`);
        } catch {
            alert('Could not download ticket. Please try again.');
        } finally {
            setDownloading(false);
        }
    };

    return (
        <div>
            <div className="card" style={{ maxWidth: '540px', margin: '0 auto' }}>
                <div className="card-body text-center" style={{ padding: '48px 40px' }}>
                    <div style={{ fontSize: '64px', marginBottom: '16px' }}>🎉</div>
                    <h1 className="font-bold text-gray-900 mb-2" style={{ fontSize: '24px' }}>
                        Booking Confirmed!
                    </h1>
                    <p className="text-sm text-gray-600 mb-6">
                        Your tickets have been booked successfully.
                        Check your email for the e-ticket with QR code.
                    </p>

                    {booking && (
                        <div className="mb-6 p-4 rounded-card text-left" style={{ background: '#F0FFF4', border: '1px solid #C6F6D5' }}>
                            <div className="text-sm font-semibold text-green-800 mb-2">Booking #{booking.id}</div>
                            {booking.items?.map((item, i) => (
                                <div key={i} className="text-sm text-green-700 flex justify-between">
                                    <span>{item.quantity}× {item.tierName} — {booking.event?.name}</span>
                                    <span>{item.totalCredits} cr</span>
                                </div>
                            ))}
                            <div className="border-t mt-2 pt-2 text-sm font-bold text-green-800 flex justify-between">
                                <span>Total</span>
                                <span>{booking.totalCredits} credits</span>
                            </div>
                            <div className="border-t mt-2 pt-2 text-sm text-green-800 flex justify-between">
                                <span>Current balance</span>
                                <span>{(state?.newCreditBalance ?? user?.creditBalance ?? 0).toLocaleString()} credits</span>
                            </div>
                        </div>
                    )}

                    <div className="flex flex-col gap-3">
                        {bookingId && (
                            <button
                                type="button"
                                onClick={handleDownload}
                                disabled={downloading}
                                className="btn btn-primary btn-full"
                            >
                                {downloading ? 'Downloading…' : '↓ Download E-Ticket'}
                            </button>
                        )}
                        <Link to="/user/bookings" className="btn btn-secondary btn-full">
                            View All Bookings
                        </Link>
                        <Link to="/events" className="btn btn-ghost btn-full">
                            Browse More Events
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
