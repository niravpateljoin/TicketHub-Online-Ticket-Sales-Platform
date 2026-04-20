export function formatCredits(amount) {
    return `${amount} credits`;
}

export function formatDate(isoString, timezone = undefined) {
    const options = {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: timezone,
    };
    return new Date(isoString).toLocaleString(undefined, options);
}

export function formatCountdown(expiresAt) {
    const diff = Math.max(0, new Date(expiresAt) - Date.now());
    const totalSeconds = Math.floor(diff / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}
