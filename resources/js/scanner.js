import jsQR from 'jsqr';

/**
 * Attendance QR scanner.
 *
 * Reads QR codes from a <video> stream (native BarcodeDetector when the
 * browser has it, jsQR otherwise) and submits the decoded payload together
 * with the device location to POST /api/v1/qr/validate.
 */

const canvas = document.createElement('canvas');
const ctx = canvas.getContext('2d', { willReadFrequently: true });
const detector = 'BarcodeDetector' in window
    ? new window.BarcodeDetector({ formats: ['qr_code'] })
    : null;

async function decodeFrame(video) {
    if (detector) {
        const codes = await detector.detect(video);
        return codes.length ? codes[0].rawValue : null;
    }

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const result = jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });
    return result ? result.data : null;
}

/**
 * Resolve with the first QR payload seen on the video, or reject on cancel().
 * Returns { promise, cancel }.
 */
export function watchForQr(video) {
    let active = true;
    let rejectFn;

    const promise = new Promise((resolve, reject) => {
        rejectFn = reject;

        const tick = async () => {
            if (!active) return;

            if (video.readyState >= video.HAVE_ENOUGH_DATA && video.videoWidth > 0) {
                try {
                    const value = await decodeFrame(video);
                    if (value) {
                        active = false;
                        resolve(value);
                        return;
                    }
                } catch (e) {
                    // Transient decode errors: keep scanning.
                }
            }

            setTimeout(() => requestAnimationFrame(tick), 100);
        };

        tick();
    });

    return {
        promise,
        cancel() {
            if (active) {
                active = false;
                rejectFn(new Error('cancelled'));
            }
        },
    };
}

/** Submit a scanned payload as attendance. Resolves with the API body, rejects with a readable Error. */
export async function submitAttendance(payload, coords) {
    const token = window.App?.api?.token || document.querySelector('meta[name="api-token"]')?.content;

    const response = await fetch('/api/v1/qr/validate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: JSON.stringify({
            token: payload,
            latitude: coords.latitude,
            longitude: coords.longitude,
            timestamp: Math.floor(Date.now() / 1000),
        }),
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message = body?.error?.message || body?.message || 'Could not record attendance.';
        throw new Error(message);
    }

    return body;
}

window.AttendanceScanner = { watchForQr, submitAttendance };
