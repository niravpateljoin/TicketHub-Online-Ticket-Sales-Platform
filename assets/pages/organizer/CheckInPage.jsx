import React, { useState, useEffect, useRef, useCallback } from 'react';
import { CheckCircle, XCircle, ScanLine, Camera, CameraOff, Loader2 } from 'lucide-react';
import { Html5Qrcode } from 'html5-qrcode';
import { checkInTicket, getCheckInHistory } from '../../api/organizerApi';
import { useToast } from '../../context/ToastContext';

const SCANNER_ID = 'qr-scanner-region';

export default function CheckInPage() {
    const { success: toastSuccess, error: toastError } = useToast();
    const [qrToken, setQrToken]       = useState('');
    const [loading, setLoading]       = useState(false);
    const [result, setResult]         = useState(null);
    const [history, setHistory]       = useState([]);
    const [scannerState, setScannerState] = useState('idle'); // idle | starting | active | error
    const [scannerError, setScannerError] = useState(null);

    const scannerRef    = useRef(null);
    const processingRef = useRef(false);

    // Load persisted check-in history from DB on mount
    useEffect(() => {
        getCheckInHistory()
            .then(data => {
                const rows = Array.isArray(data) ? data : (data?.items ?? []);
                setHistory(rows.map(r => ({
                    ...r,
                    time: new Date(r.checkedInAt).toLocaleTimeString(),
                })));
            })
            .catch(() => {});
    }, []);

    const doCheckIn = useCallback(async (token) => {
        const t = token.trim();
        if (!t || processingRef.current) return;
        processingRef.current = true;
        setLoading(true);
        setResult(null);

        try {
            const res  = await checkInTicket(t);
            const data = res.data ?? res;
            const msg  = res.message ?? 'Check-in successful.';
            setResult({ success: true, data, message: msg });
            setHistory(prev => [{ ...data, time: new Date().toLocaleTimeString() }, ...prev.slice(0, 99)]);
            setQrToken('');
            toastSuccess(`✓ ${data.attendeeEmail ?? 'Attendee'} checked in`);
        } catch (err) {
            const msg = err.response?.data?.message ?? 'Check-in failed. Please try again.';
            setResult({ success: false, message: msg });
            toastError(msg);
        } finally {
            setLoading(false);
            setTimeout(() => { processingRef.current = false; }, 2000);
        }
    }, []);

    const startScanner = useCallback(async () => {
        setScannerError(null);
        setScannerState('starting');

        try {
            const cameras = await Html5Qrcode.getCameras();
            if (!cameras || cameras.length === 0) {
                setScannerError('No camera found on this device.');
                setScannerState('error');
                return;
            }

            // Prefer rear/back camera on mobile
            const cam = cameras.find(c => /back|rear|environment/i.test(c.label)) ?? cameras[cameras.length - 1];

            // Switch to active so the div is rendered + visible before html5-qrcode starts
            setScannerState('active');

            // Wait two animation frames for React to paint the visible div
            await new Promise(r => requestAnimationFrame(r));
            await new Promise(r => requestAnimationFrame(r));

            const qr = new Html5Qrcode(SCANNER_ID);
            scannerRef.current = qr;

            await qr.start(
                cam.id,
                { fps: 10, qrbox: { width: 220, height: 220 }, aspectRatio: 1.0 },
                (decodedText) => { doCheckIn(decodedText); },
                () => {}
            );
        } catch {
            setScannerError('Camera access denied or unavailable.');
            setScannerState('error');
        }
    }, [doCheckIn]);

    const stopScanner = useCallback(async () => {
        if (scannerRef.current) {
            try { await scannerRef.current.stop(); } catch {}
            scannerRef.current = null;
        }
        setScannerState('idle');
    }, []);

    useEffect(() => () => { stopScanner(); }, [stopScanner]);

    const handleManualSubmit = async (e) => {
        e.preventDefault();
        await doCheckIn(qrToken);
    };

    const isActive   = scannerState === 'active';
    const isStarting = scannerState === 'starting';

    return (
        <div style={{ padding: '24px 16px', maxWidth: '1000px', margin: '0 auto' }}>
            <h1 className="font-bold text-gray-900 mb-2" style={{ fontSize: '22px' }}>Attendee Check-In</h1>
            <p className="text-sm text-gray-500 mb-6">Scan a QR code with your camera or paste the token manually.</p>

            <div className="checkin-grid">

                {/* ── Scanner panel ── */}
                <div>
                    <div className="card" style={{ padding: '24px' }}>
                        <div className="flex items-center gap-2 mb-4 text-gray-600">
                            <ScanLine size={20} />
                            <span className="font-medium">QR Scanner</span>
                        </div>

                        {/* Camera toggle button */}
                        <button
                            type="button"
                            className={`btn btn-full mb-4 ${isActive ? 'btn-secondary' : 'btn-primary'}`}
                            onClick={isActive ? stopScanner : startScanner}
                            disabled={isStarting}
                            style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px' }}
                        >
                            {isStarting
                                ? <><Loader2 size={16} className="animate-spin" /> Starting camera…</>
                                : isActive
                                    ? <><CameraOff size={16} /> Stop Camera</>
                                    : <><Camera size={16} /> Scan with Camera</>
                            }
                        </button>

                        {scannerError && (
                            <p className="text-sm text-red-600 mb-3">{scannerError}</p>
                        )}

                        {/* Viewfinder area */}
                        <div style={{ marginBottom: '20px', position: 'relative' }}>
                            {/* Placeholder shown when camera is idle/error */}
                            {!isActive && (
                                <div style={{
                                    height: '280px',
                                    borderRadius: '10px',
                                    border: '2px dashed #CBD5E1',
                                    background: '#F8FAFC',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: '12px',
                                    color: '#94A3B8',
                                }}>
                                    {isStarting ? (
                                        <Loader2 size={40} strokeWidth={1.4} className="animate-spin" style={{ color: '#2EC4A1' }} />
                                    ) : (
                                        <>
                                            <Camera size={48} strokeWidth={1.2} />
                                            <p style={{ fontSize: '13px', textAlign: 'center', maxWidth: '200px', lineHeight: 1.5 }}>
                                                Click <strong style={{ color: '#374151' }}>Scan with Camera</strong> to open the camera viewfinder
                                            </p>
                                        </>
                                    )}
                                </div>
                            )}

                            {/* html5-qrcode mounts here — always in DOM so it can calculate dimensions */}
                            <div
                                id={SCANNER_ID}
                                style={{
                                    display: isActive ? 'block' : 'none',
                                    borderRadius: '10px',
                                    overflow: 'hidden',
                                    border: '2px solid #2EC4A1',
                                    background: '#000',
                                    minHeight: '280px',
                                }}
                            />

                            {/* Scan-frame overlay (corner brackets) shown when active */}
                            {isActive && (
                                <div style={{
                                    position: 'absolute',
                                    inset: 0,
                                    pointerEvents: 'none',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}>
                                    <div style={{
                                        width: 220,
                                        height: 220,
                                        position: 'relative',
                                    }}>
                                        {/* Four corner brackets */}
                                        {[
                                            { top: 0, left: 0, borderTop: '3px solid #2EC4A1', borderLeft: '3px solid #2EC4A1', borderRadius: '4px 0 0 0' },
                                            { top: 0, right: 0, borderTop: '3px solid #2EC4A1', borderRight: '3px solid #2EC4A1', borderRadius: '0 4px 0 0' },
                                            { bottom: 0, left: 0, borderBottom: '3px solid #2EC4A1', borderLeft: '3px solid #2EC4A1', borderRadius: '0 0 0 4px' },
                                            { bottom: 0, right: 0, borderBottom: '3px solid #2EC4A1', borderRight: '3px solid #2EC4A1', borderRadius: '0 0 4px 0' },
                                        ].map((s, i) => (
                                            <div key={i} style={{ position: 'absolute', width: 24, height: 24, ...s }} />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Manual input */}
                        <form onSubmit={handleManualSubmit}>
                            <div className="mb-3">
                                <label className="form-label">Or enter QR Token manually</label>
                                <input
                                    type="text"
                                    className="form-input"
                                    placeholder="Paste QR token here…"
                                    value={qrToken}
                                    onChange={e => setQrToken(e.target.value)}
                                />
                            </div>
                            <button
                                type="submit"
                                className="btn btn-primary btn-full"
                                disabled={loading || !qrToken.trim()}
                            >
                                {loading ? 'Checking in…' : 'Check In'}
                            </button>
                        </form>

                        {/* Result banner */}
                        {result && (
                            <div className="mt-5 p-4 rounded-btn" style={{
                                background: result.success ? '#F0FDF4' : '#FFF5F5',
                                border: `1px solid ${result.success ? '#86EFAC' : '#FEB2B2'}`,
                            }}>
                                <div className="flex items-start gap-3">
                                    {result.success
                                        ? <CheckCircle size={20} color="#16A34A" style={{ flexShrink: 0, marginTop: 2 }} />
                                        : <XCircle    size={20} color="#DC2626" style={{ flexShrink: 0, marginTop: 2 }} />
                                    }
                                    <div>
                                        <p className="font-semibold text-sm" style={{ color: result.success ? '#166534' : '#991B1B' }}>
                                            {result.message}
                                        </p>
                                        {result.success && result.data && (
                                            <div className="mt-2 text-sm" style={{ color: '#166534' }}>
                                                <p><strong>Attendee:</strong> {result.data.attendeeEmail}</p>
                                                {result.data.attendeeName && <p><strong>Name:</strong> {result.data.attendeeName}</p>}
                                                <p><strong>Event:</strong> {result.data.eventName}</p>
                                                <p><strong>Tier:</strong> {result.data.tierName} × {result.data.quantity}</p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* ── Recent check-ins ── */}
                <div>
                    <div className="card" style={{ padding: '24px' }}>
                        <h2 className="font-semibold text-gray-700 mb-4" style={{ fontSize: '15px' }}>
                            Recent Check-ins ({history.length})
                        </h2>
                        {history.length === 0 ? (
                            <p className="text-sm text-gray-400 text-center py-8">No check-ins yet this session.</p>
                        ) : (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', maxHeight: '500px', overflowY: 'auto' }}>
                                {history.map((h, i) => (
                                    <div key={i} className="p-3 rounded-btn"
                                        style={{ background: '#F0FDF4', border: '1px solid #BBF7D0', fontSize: '13px' }}>
                                        <div className="flex justify-between">
                                            <span className="font-medium text-gray-800">{h.attendeeEmail}</span>
                                            <span className="text-gray-400">{h.time}</span>
                                        </div>
                                        <div className="text-gray-500 mt-1">{h.tierName} — {h.eventName}</div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

            </div>
        </div>
    );
}
