const MAX_IMAGE_EDGE = 1920;
const TARGET_IMAGE_BYTES = 850 * 1024;
const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const OUTPUT_IMAGE_TYPE = 'image/jpeg';
const OUTPUT_IMAGE_EXTENSION = 'jpg';

const formatBytes = (bytes) => {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const canvasToBlob = (canvas, type, quality) => new Promise((resolve, reject) => {
    canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('No se pudo comprimir la imagen.')), type, quality);
});

const loadImage = async (file) => {
    if ('createImageBitmap' in window) {
        return createImageBitmap(file, { imageOrientation: 'from-image' });
    }

    return new Promise((resolve, reject) => {
        const image = new Image();
        const url = URL.createObjectURL(file);
        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('No se pudo leer la imagen seleccionada.'));
        };
        image.src = url;
    });
};

const optimizeImage = async (file) => {
    const source = await loadImage(file);
    const sourceWidth = source.width;
    const sourceHeight = source.height;
    const scale = Math.min(1, MAX_IMAGE_EDGE / Math.max(sourceWidth, sourceHeight));
    let canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(sourceWidth * scale));
    canvas.height = Math.max(1, Math.round(sourceHeight * scale));

    const context = canvas.getContext('2d', { alpha: false });
    if (!context) {
        source.close?.();
        throw new Error('El navegador no pudo preparar la imagen.');
    }

    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(source, 0, 0, canvas.width, canvas.height);
    let blob;
    while (true) {
        for (const quality of [0.84, 0.76, 0.68, 0.60]) {
            blob = await canvasToBlob(canvas, OUTPUT_IMAGE_TYPE, quality);
            if (blob.size <= TARGET_IMAGE_BYTES) {
                break;
            }
        }

        if (blob.size <= TARGET_IMAGE_BYTES || Math.max(canvas.width, canvas.height) <= 960) {
            break;
        }

        const reduced = document.createElement('canvas');
        reduced.width = Math.max(1, Math.round(canvas.width * 0.82));
        reduced.height = Math.max(1, Math.round(canvas.height * 0.82));
        const reducedContext = reduced.getContext('2d', { alpha: false });
        reducedContext.fillStyle = '#ffffff';
        reducedContext.fillRect(0, 0, reduced.width, reduced.height);
        reducedContext.drawImage(canvas, 0, 0, reduced.width, reduced.height);
        canvas = reduced;
    }
    source.close?.();

    const baseName = file.name.replace(/\.[^.]+$/, '').replace(/[^a-zA-Z0-9_-]+/g, '-') || 'tip';
    return new File([blob], `${baseName}.${OUTPUT_IMAGE_EXTENSION}`, { type: OUTPUT_IMAGE_TYPE, lastModified: Date.now() });
};

const initializeTipForm = (form) => {
    const input = form.querySelector('[data-tip-image-input]');
    const preview = form.querySelector('[data-tip-image-preview]');
    const placeholder = form.querySelector('[data-tip-image-placeholder]');
    const status = form.querySelector('[data-tip-image-status]');
    const removeImage = form.querySelector('[data-tip-remove-image]');
    const body = form.querySelector('[data-tip-body]');
    const bodyCount = form.querySelector('[data-tip-body-count]');
    const submitButtons = form.querySelectorAll('[data-tip-submit]');
    let previewUrl = null;
    let processing = false;

    const setProcessing = (value) => {
        processing = value;
        submitButtons.forEach((button) => {
            button.disabled = value;
            button.classList.toggle('opacity-60', value);
            button.classList.toggle('cursor-wait', value);
        });
    };

    const showPreview = (file) => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');
    };

    const updateBodyCount = () => {
        if (bodyCount && body) {
            bodyCount.textContent = body.value.length.toLocaleString('es-MX');
        }
    };

    body?.addEventListener('input', updateBodyCount);
    updateBodyCount();

    input?.addEventListener('change', async () => {
        const original = input.files?.[0];
        if (!original) {
            return;
        }
        if (!ACCEPTED_IMAGE_TYPES.includes(original.type)) {
            input.value = '';
            status.textContent = 'Selecciona una imagen JPG, PNG o WebP.';
            status.className = 'mt-1 truncate text-xs text-red-600';
            return;
        }

        setProcessing(true);
        status.textContent = 'Optimizando imagen…';
        status.className = 'mt-1 truncate text-xs text-indigo-600';

        try {
            const optimized = await optimizeImage(original);
            const transfer = new DataTransfer();
            transfer.items.add(optimized);
            input.files = transfer.files;
            showPreview(optimized);
            removeImage && (removeImage.checked = false);
            preview.classList.remove('opacity-30');
            status.textContent = `${optimized.name} · ${formatBytes(original.size)} → ${formatBytes(optimized.size)}`;
            status.className = 'mt-1 truncate text-xs text-emerald-700';
        } catch (error) {
            input.value = '';
            status.textContent = error instanceof Error ? error.message : 'No se pudo preparar la imagen.';
            status.className = 'mt-1 truncate text-xs text-red-600';
        } finally {
            setProcessing(false);
        }
    });

    removeImage?.addEventListener('change', () => {
        preview.classList.toggle('opacity-30', removeImage.checked);
    });
    preview.classList.toggle('opacity-30', removeImage?.checked ?? false);

    form.addEventListener('submit', (event) => {
        if (processing) {
            event.preventDefault();
        }
    });

    window.addEventListener('beforeunload', () => previewUrl && URL.revokeObjectURL(previewUrl), { once: true });
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tip-form]').forEach(initializeTipForm);
});
