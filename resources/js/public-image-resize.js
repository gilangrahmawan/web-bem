/**
 * Form publik (oprec): perkecil foto di browser sebelum dikirim.
 *
 * <input type="file" data-resize-image="1600" data-status="#id-status">
 *   → foto yang sisi terpanjangnya > 1600px (atau > 1,5 MB) digambar ulang ke canvas dan diganti
 *     JPEG ±0,88 sebelum form dikirim. Orientasi EXIF diterapkan browser saat decode, jadi hasilnya
 *     sudah tegak (server tidak perlu memutar bitmap besar).
 * <input type="file" data-max-kb="2048"> → peringatan langsung bila berkas terlalu besar.
 * <form data-processing-form> → tombol submit dikunci + pesan "sedang mengunggah…" saat dikirim.
 *
 * Bila browser tidak mendukung (DataTransfer/canvas), berkas asli dikirim apa adanya dan server
 * tetap memvalidasi & mengompres.
 */

const RESIZE_THRESHOLD_BYTES = 1.5 * 1024 * 1024;

function setStatus(input, text, isError = false) {
    const target = input.dataset.status ? document.querySelector(input.dataset.status) : null;

    if (!target) {
        return;
    }

    target.textContent = text;
    target.classList.toggle('hidden', text === '');
    target.classList.toggle('text-red-700', isError);
    target.classList.toggle('text-slate-600', !isError);
}

async function decode(file) {
    if ('createImageBitmap' in window) {
        try {
            return await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch {
            // Lanjut ke fallback <img>.
        }
    }

    const url = URL.createObjectURL(file);

    try {
        const img = new Image();
        img.decoding = 'async';
        img.src = url;
        await img.decode();

        return img;
    } finally {
        URL.revokeObjectURL(url);
    }
}

async function shrink(file, maxSide) {
    const image = await decode(file);
    const width = image.width;
    const height = image.height;
    const scale = Math.min(1, maxSide / Math.max(width, height));

    if (scale === 1 && file.size <= RESIZE_THRESHOLD_BYTES) {
        image.close?.();

        return null;
    }

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(width * scale);
    canvas.height = Math.round(height * scale);

    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
    image.close?.();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.88));

    if (!blob) {
        return null;
    }

    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';

    return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
}

function formatMb(bytes) {
    return (bytes / 1024 / 1024).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' MB';
}

const pending = new WeakMap();

document.querySelectorAll('input[type=file][data-resize-image]').forEach((input) => {
    input.addEventListener('change', () => {
        const file = input.files?.[0];

        if (!file || !file.type.startsWith('image/') || typeof DataTransfer === 'undefined') {
            return;
        }

        setStatus(input, 'Sedang memperkecil foto…');

        const job = shrink(file, Number(input.dataset.resizeImage) || 1600)
            .then((resized) => {
                if (resized && resized.size < file.size) {
                    const transfer = new DataTransfer();
                    transfer.items.add(resized);
                    input.files = transfer.files;
                    setStatus(input, `Foto diperkecil: ${formatMb(file.size)} → ${formatMb(resized.size)}.`);
                } else {
                    setStatus(input, '');
                }
            })
            .catch(() => setStatus(input, ''))
            .finally(() => pending.delete(input));

        pending.set(input, job);
    });
});

document.querySelectorAll('input[type=file][data-max-kb]').forEach((input) => {
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        const max = Number(input.dataset.maxKb) * 1024;

        if (file && max && file.size > max) {
            setStatus(input, `Berkas ${formatMb(file.size)} melebihi batas ${formatMb(max)}.`, true);
        } else if (file) {
            setStatus(input, '');
        }
    });
});

document.querySelectorAll('form[data-processing-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        const jobs = [...form.querySelectorAll('input[type=file][data-resize-image]')]
            .map((input) => pending.get(input))
            .filter(Boolean);
        const button = form.querySelector('[type=submit]');
        const message = form.querySelector('[data-processing-message]');

        if (jobs.length > 0) {
            // Tunggu resize selesai, lalu kirim ulang.
            event.preventDefault();
            await Promise.allSettled(jobs);
            form.requestSubmit(button ?? undefined);

            return;
        }

        if (button) {
            button.disabled = true;
        }

        message?.classList.remove('hidden');
    });
});
