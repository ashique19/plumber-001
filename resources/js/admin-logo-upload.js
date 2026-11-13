const MAX_DIM = 256;
const MIN_EDGE = 64;
const MAX_BYTES = 100 * 1024;
const START_QUALITY = 0.85;
const MIN_QUALITY = 0.5;

function formatBytes(bytes) {
    if (! Number.isFinite(bytes) || bytes < 0) {
        return '';
    }
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1_048_576) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1_048_576).toFixed(2)} MB`;
}

function isRasterMime(mime) {
    return ['image/png', 'image/jpeg', 'image/webp', 'image/jpg'].includes(mime);
}

function rejectSvgUnsafe(svgText, labels) {
    const lower = svgText.toLowerCase();
    if (/<script/i.test(svgText)) {
        throw new Error(labels.svg_unsafe);
    }
    if (/\son\w+\s*=/i.test(svgText)) {
        throw new Error(labels.svg_unsafe);
    }
    if (/<foreignobject/i.test(lower)) {
        throw new Error(labels.svg_unsafe);
    }
    if (/javascript:/i.test(svgText)) {
        throw new Error(labels.svg_unsafe);
    }
}

async function loadImageElementFromFile(file) {
    const url = URL.createObjectURL(file);

    try {
        return await new Promise((resolve, reject) => {
            const el = new Image();
            el.onload = () => resolve(el);
            el.onerror = () => reject(new Error('decode'));
            el.src = url;
        });
    } finally {
        URL.revokeObjectURL(url);
    }
}

async function decodeBitmap(file) {
    if (typeof createImageBitmap === 'function') {
        try {
            return await createImageBitmap(file, {
                premultiplyAlpha: 'premultiply',
                colorSpaceConversion: 'default',
            });
        } catch {
            return loadImageElementFromFile(file);
        }
    }

    return loadImageElementFromFile(file);
}

function canvasHasTransparency(ctx, width, height) {
    const data = ctx.getImageData(0, 0, width, height).data;
    for (let i = 3; i < data.length; i += 4) {
        if (data[i] < 255) {
            return true;
        }
    }

    return false;
}

function canvasToBlob(canvas, mime, quality) {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (b) => (b ? resolve(b) : reject(new Error('encode'))),
            mime,
            quality,
        );
    });
}

async function encodeCanvasForUpload(sourceCanvas, sourceCtx, labels) {
    let width = sourceCanvas.width;
    let height = sourceCanvas.height;
    let quality = START_QUALITY;
    let workingCanvas = sourceCanvas;
    let workingCtx = sourceCtx;

    for (let attempt = 0; attempt < 8; attempt += 1) {
        const hasAlpha = canvasHasTransparency(workingCtx, width, height);
        let blob = await canvasToBlob(workingCanvas, 'image/webp', quality);
        let mime = 'image/webp';
        let ext = 'webp';

        if (hasAlpha) {
            const pngBlob = await canvasToBlob(workingCanvas, 'image/png');
            if (pngBlob.size <= blob.size) {
                blob = pngBlob;
                mime = 'image/png';
                ext = 'png';
            }
        }

        if (blob.size <= MAX_BYTES) {
            return { blob, mime, ext, width, height };
        }

        quality = Math.max(MIN_QUALITY, quality - 0.1);

        if (attempt >= 2) {
            width = Math.max(MIN_EDGE, Math.round(width * 0.8));
            height = Math.max(MIN_EDGE, Math.round(height * 0.8));
            const shrunk = document.createElement('canvas');
            shrunk.width = width;
            shrunk.height = height;
            const shrunkCtx = shrunk.getContext('2d', { alpha: true });
            if (! shrunkCtx) {
                break;
            }
            shrunkCtx.drawImage(workingCanvas, 0, 0, width, height);
            workingCanvas = shrunk;
            workingCtx = shrunkCtx;
        }
    }

    throw new Error(labels.still_too_large ?? labels.processing_failed);
}

/**
 * Always redraw through canvas (strips EXIF); never upload raw phone photos.
 */
async function prepareFileForUpload(file, labels) {
    const mime = (file.type || '').toLowerCase();
    const name = file.name || 'logo';

    if (mime === 'image/svg+xml' || name.toLowerCase().endsWith('.svg')) {
        const text = await file.text();
        rejectSvgUnsafe(text, labels);
        const blob = new Blob([text], { type: 'image/svg+xml' });
        const outFile = new File([blob], name.replace(/\.[^.]+$/, '') + '.svg', {
            type: 'image/svg+xml',
            lastModified: Date.now(),
        });

        return {
            file: outFile,
            previewBlob: blob,
            name: outFile.name,
            width: null,
            height: null,
            bytes: outFile.size,
        };
    }

    if (! isRasterMime(mime)) {
        throw new Error(labels.invalid_type);
    }

    const bitmap = await decodeBitmap(file);
    const sourceWidth = bitmap.width || bitmap.naturalWidth || 0;
    const sourceHeight = bitmap.height || bitmap.naturalHeight || 0;

    if (sourceWidth < MIN_EDGE || sourceHeight < MIN_EDGE) {
        if (typeof bitmap.close === 'function') {
            bitmap.close();
        }
        throw new Error(labels.too_small);
    }

    const scale = Math.min(1, MAX_DIM / Math.max(sourceWidth, sourceHeight));
    const width = Math.max(1, Math.round(sourceWidth * scale));
    const height = Math.max(1, Math.round(sourceHeight * scale));

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d', { alpha: true });

    if (! ctx) {
        if (typeof bitmap.close === 'function') {
            bitmap.close();
        }
        throw new Error(labels.processing_failed);
    }

    ctx.clearRect(0, 0, width, height);
    ctx.drawImage(bitmap, 0, 0, width, height);

    if (typeof bitmap.close === 'function') {
        bitmap.close();
    }

    const encoded = await encodeCanvasForUpload(canvas, ctx, labels);
    const baseName = name.replace(/\.[^.]+$/, '');
    const outFile = new File([encoded.blob], `${baseName}.${encoded.ext}`, {
        type: encoded.mime,
        lastModified: Date.now(),
    });

    return {
        file: outFile,
        previewBlob: encoded.blob,
        name: outFile.name,
        width: encoded.width,
        height: encoded.height,
        bytes: encoded.blob.size,
    };
}

export function registerAdminLogoUpload(Alpine) {
    Alpine.data('adminLogoUpload', (config) => ({
        wireProp: config.wireProp ?? 'logoUpload',
        previewUrl: '',
        storedPreview: config.initialPreview ?? '',
        localPreviewUrl: '',
        fileName: '',
        fileSizeLabel: '',
        dimensionsLabel: '',
        metaLabel: '',
        progress: 0,
        preparing: false,
        uploadInFlight: false,
        error: '',
        labels: config.labels ?? {},

        get hasPreview() {
            return typeof this.previewUrl === 'string' && this.previewUrl.length > 0;
        },

        get showMeta() {
            return this.fileName !== '' || this.metaLabel !== '';
        },

        filenameText() {
            if (this.fileName) {
                return this.fileName;
            }
            if (this.hasPreview && this.storedPreview) {
                return this.labels.current_logo ?? '';
            }

            return '';
        },

        init() {
            if (this.storedPreview) {
                this.previewUrl = this.storedPreview;
            }

            window.addEventListener('business-logo-saved', (event) => {
                const url = event.detail?.publicUrl;
                if (typeof url === 'string' && url !== '') {
                    this.revokeLocalPreview();
                    this.previewUrl = url;
                    this.storedPreview = url;
                    this.clearPendingMeta();
                    this.uploadInFlight = false;
                    this.preparing = false;
                }
            });

            window.addEventListener('business-logo-removed', () => {
                this.revokeLocalPreview();
                this.previewUrl = '';
                this.storedPreview = '';
                this.clearPendingMeta();
                this.uploadInFlight = false;
                this.preparing = false;
            });

            const prop = this.wireProp;
            const onError = (e) => {
                if (e?.detail?.property && e.detail.property !== prop) {
                    return;
                }
                this.uploadInFlight = false;
                this.preparing = false;
                if (! this.error) {
                    this.error = this.labels.failed_causes ?? this.labels.failed_generic;
                }
            };
            const onStart = (e) => {
                if (e.detail?.property !== prop) {
                    return;
                }
                this.uploadInFlight = true;
                this.progress = 0;
                this.error = '';
            };
            const onProgress = (e) => {
                if (e.detail?.property !== prop) {
                    return;
                }
                this.progress = Math.round(e.detail?.progress ?? 0);
            };
            const onFinish = (e) => {
                if (e.detail?.property !== prop) {
                    return;
                }
                this.uploadInFlight = false;
                this.preparing = false;
                this.progress = 100;
            };

            window.addEventListener('livewire-upload-error', onError);
            window.addEventListener('livewire-upload-start', onStart);
            window.addEventListener('livewire-upload-progress', onProgress);
            window.addEventListener('livewire-upload-finish', onFinish);
        },

        revokeLocalPreview() {
            if (this.localPreviewUrl && this.localPreviewUrl.startsWith('blob:')) {
                URL.revokeObjectURL(this.localPreviewUrl);
            }
            this.localPreviewUrl = '';
        },

        setLocalPreviewFromBlob(blob) {
            this.revokeLocalPreview();
            this.localPreviewUrl = URL.createObjectURL(blob);
            this.previewUrl = this.localPreviewUrl;
        },

        clearPendingMeta() {
            this.fileName = '';
            this.fileSizeLabel = '';
            this.dimensionsLabel = '';
            this.metaLabel = '';
            this.progress = 0;
        },

        syncMetaFromProcessed(processed) {
            this.fileName = processed.name;
            this.fileSizeLabel = formatBytes(processed.bytes);
            if (processed.width && processed.height) {
                this.dimensionsLabel = `${processed.width}×${processed.height}`;
                this.metaLabel = `${this.dimensionsLabel} · ${this.fileSizeLabel}`;
            } else {
                this.dimensionsLabel = '';
                this.metaLabel = this.fileSizeLabel;
            }
        },

        async pickFile(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (! file) {
                return;
            }

            this.error = '';
            this.preparing = true;
            this.uploadInFlight = false;
            this.progress = 0;

            try {
                const processed = await prepareFileForUpload(file, this.labels);
                this.setLocalPreviewFromBlob(processed.previewBlob);
                this.syncMetaFromProcessed(processed);
                this.preparing = false;

                this.$wire.upload(
                    this.wireProp,
                    processed.file,
                    () => {
                        this.uploadInFlight = false;
                        this.progress = 100;
                    },
                    () => {
                        this.uploadInFlight = false;
                        this.error = this.labels.failed_causes ?? this.labels.failed_generic;
                    },
                    (ev) => {
                        this.uploadInFlight = true;
                        this.progress = Math.round(ev.detail?.progress ?? 0);
                    },
                );
            } catch (err) {
                this.preparing = false;
                this.uploadInFlight = false;
                this.error = err?.message || this.labels.failed_generic;
            }
        },

        clearPending() {
            this.$wire.set(this.wireProp, null);
            this.revokeLocalPreview();
            this.previewUrl = this.storedPreview;
            this.clearPendingMeta();
            this.error = '';
            this.uploadInFlight = false;
            this.preparing = false;
        },

        openPicker() {
            this.$refs.fileInput?.click();
        },
    }));
}
