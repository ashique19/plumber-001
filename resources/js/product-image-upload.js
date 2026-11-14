import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const MAX_EDGE = 800;
const OUTPUT_QUALITY = 0.8;
const MIN_EDGE = 64;

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

function canvasToBlob(canvas, mime, quality) {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (b) => (b ? resolve(b) : reject(new Error('encode'))),
            mime,
            quality,
        );
    });
}

async function exportCroppedCanvas(sourceCanvas, labels) {
    const scale = Math.min(1, MAX_EDGE / Math.max(sourceCanvas.width, sourceCanvas.height));
    let width = Math.max(MIN_EDGE, Math.round(sourceCanvas.width * scale));
    let height = Math.max(MIN_EDGE, Math.round(sourceCanvas.height * scale));

    const out = document.createElement('canvas');
    out.width = width;
    out.height = height;
    const ctx = out.getContext('2d');
    if (! ctx) {
        throw new Error(labels.processing_failed);
    }
    ctx.drawImage(sourceCanvas, 0, 0, width, height);

    let blob = await canvasToBlob(out, 'image/webp', OUTPUT_QUALITY);
    let mime = 'image/webp';
    let ext = 'webp';

    if (blob.size === 0 || blob.type === '') {
        blob = await canvasToBlob(out, 'image/jpeg', OUTPUT_QUALITY);
        mime = 'image/jpeg';
        ext = 'jpg';
    }

    const name = `product-${Date.now()}.${ext}`;
    const file = new File([blob], name, { type: mime });

    return {
        file,
        previewBlob: blob,
        bytes: blob.size,
        width,
        height,
        name,
    };
}

export function registerCatalogProductImageUpload(Alpine) {
    Alpine.data('catalogProductImageUpload', (config) => ({
        wireProp: config.wireProp,
        labels: config.labels,
        storedPreview: config.initialPreview ?? '',
        previewUrl: '',
        localPreviewUrl: '',
        fileName: '',
        metaLabel: '',
        error: '',
        progress: 0,
        uploadInFlight: false,
        preparing: false,
        cropOpen: false,
        cropper: null,
        pendingObjectUrl: '',

        get hasPreview() {
            return Boolean(this.previewUrl);
        },

        get showMeta() {
            return Boolean(this.fileName || this.metaLabel);
        },

        init() {
            if (this.storedPreview) {
                this.previewUrl = this.storedPreview;
            }

            window.addEventListener('catalog-product-image-cleared', () => {
                this.revokeLocalPreview();
                this.previewUrl = '';
                this.storedPreview = '';
                this.clearPendingMeta();
                this.uploadInFlight = false;
                this.preparing = false;
                this.closeCropper();
            });

            const prop = this.wireProp;
            const onError = (e) => {
                if (e?.detail?.property && e.detail.property !== prop) {
                    return;
                }
                this.uploadInFlight = false;
                this.preparing = false;
                if (! this.error) {
                    this.error = this.labels.upload_failed ?? this.labels.network;
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

        revokePendingObjectUrl() {
            if (this.pendingObjectUrl) {
                URL.revokeObjectURL(this.pendingObjectUrl);
                this.pendingObjectUrl = '';
            }
        },

        setLocalPreviewFromBlob(blob) {
            this.revokeLocalPreview();
            this.localPreviewUrl = URL.createObjectURL(blob);
            this.previewUrl = this.localPreviewUrl;
        },

        clearPendingMeta() {
            this.fileName = '';
            this.metaLabel = '';
            this.progress = 0;
        },

        closeCropper() {
            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
            this.cropOpen = false;
            this.revokePendingObjectUrl();
        },

        openPicker() {
            this.$refs.fileInput?.click();
        },

        async pickFile(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (! file) {
                return;
            }

            this.error = '';
            if (! file.type.startsWith('image/')) {
                this.error = this.labels.wrong_type;

                return;
            }

            this.revokePendingObjectUrl();
            this.pendingObjectUrl = URL.createObjectURL(file);
            this.cropOpen = true;

            await this.$nextTick();

            const img = this.$refs.cropImage;
            if (! img) {
                this.error = this.labels.processing_failed;
                this.closeCropper();

                return;
            }

            img.onload = () => {
                if (this.cropper) {
                    this.cropper.destroy();
                }
                this.cropper = new Cropper(img, {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 1,
                    responsive: true,
                    guides: true,
                });
            };
            img.src = this.pendingObjectUrl;
        },

        cancelCrop() {
            this.closeCropper();
        },

        async confirmCrop() {
            if (! this.cropper) {
                return;
            }

            this.preparing = true;
            this.error = '';

            try {
                const canvas = this.cropper.getCroppedCanvas({
                    maxWidth: MAX_EDGE,
                    maxHeight: MAX_EDGE,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });

                if (! canvas || canvas.width < MIN_EDGE || canvas.height < MIN_EDGE) {
                    throw new Error(this.labels.too_small);
                }

                const processed = await exportCroppedCanvas(canvas, this.labels);
                this.closeCropper();
                this.setLocalPreviewFromBlob(processed.previewBlob);
                this.fileName = processed.name;
                this.metaLabel = `${processed.width}×${processed.height} · ${formatBytes(processed.bytes)}`;
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
                        this.error = this.labels.upload_failed ?? this.labels.network;
                    },
                    (ev) => {
                        this.uploadInFlight = true;
                        this.progress = Math.round(ev.detail?.progress ?? 0);
                    },
                );
            } catch (err) {
                this.preparing = false;
                this.error = err?.message || this.labels.processing_failed;
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

        removeStored() {
            this.$wire.call('removeProductImage');
        },
    }));
}