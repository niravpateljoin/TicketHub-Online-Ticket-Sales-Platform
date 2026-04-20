export function getFilenameFromContentDisposition(contentDisposition) {
    if (!contentDisposition || typeof contentDisposition !== 'string') {
        return null;
    }

    const utfMatch = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i);
    if (utfMatch?.[1]) {
        try {
            return decodeURIComponent(utfMatch[1]).replace(/["']/g, '');
        } catch {
            return utfMatch[1].replace(/["']/g, '');
        }
    }

    const asciiMatch = contentDisposition.match(/filename="?([^";]+)"?/i);
    return asciiMatch?.[1] ? asciiMatch[1].replace(/["']/g, '') : null;
}

export function downloadBlobResponse(response, fallbackName = 'ticket.pdf') {
    const contentType = response?.headers?.['content-type'] ?? 'application/octet-stream';
    const contentDisposition = response?.headers?.['content-disposition'] ?? '';
    const fileName = getFilenameFromContentDisposition(contentDisposition) ?? fallbackName;

    const url = window.URL.createObjectURL(new Blob([response.data], { type: contentType }));
    const a = document.createElement('a');
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
}
