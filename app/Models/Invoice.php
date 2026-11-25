<?php

namespace App\Models;

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Exceptions\IssuedInvoiceImmutableException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Invoice extends Model
{
    protected $fillable = [
        'document_number',
        'official_number',
        'document_type',
        'status',
        'branch_id',
        'referenced_invoice_id',
        'source_type',
        'source_id',
        'icv',
        'invoice_hash',
        'previous_invoice_hash',
        'signed_xml',
        'qr_tlv_base64',
        'zheart_seller',
        'payload',
        'issued_at',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => InvoiceDocumentType::class,
            'status' => InvoiceStatus::class,
            'payload' => 'array',
            'zheart_seller' => 'array',
            'issued_at' => 'datetime',
            'due_date' => 'date',
        ];
    }

    public static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            if ($invoice->getOriginal('issued_at') !== null) {
                throw new IssuedInvoiceImmutableException(
                    'Issued invoices are immutable; issue a credit or debit note instead.'
                );
            }
        });

        static::deleting(function (Invoice $invoice): void {
            if ($invoice->status === InvoiceStatus::Issued) {
                throw new IssuedInvoiceImmutableException('Issued invoices cannot be deleted.');
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function referencedInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referenced_invoice_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(InvoiceAuditLog::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function isIssued(): bool
    {
        return $this->status === InvoiceStatus::Issued;
    }

    /**
     * Persist a UUID on the draft before the first Z-Heart call and reuse it on every resend.
     */
    public function ensureInvoiceUuid(): string
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        $existing = trim((string) ($payload['invoice_uuid'] ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        $uuid = (string) Str::uuid();
        $payload['invoice_uuid'] = $uuid;
        $this->forceFill(['payload' => $payload])->save();

        return $uuid;
    }

    public function invoiceUuid(): ?string
    {
        $uuid = trim((string) data_get($this->payload, 'invoice_uuid', ''));

        return $uuid !== '' ? $uuid : null;
    }

    public function displayNumber(): string
    {
        $official = $this->officialOrStoredNumber();
        if ($official !== null) {
            return $official;
        }

        if ($this->status !== InvoiceStatus::Issued) {
            return __('invoices.document_number_pending');
        }

        if ($this->isSubmissionRetry()) {
            return __('invoices.pending_number');
        }

        return (string) $this->document_number;
    }

    public function officialOrDocumentNumber(): string
    {
        return $this->officialOrStoredNumber() ?? (string) $this->document_number;
    }

    public function officialOrStoredNumber(): ?string
    {
        $official = trim((string) ($this->official_number ?? ''));
        if ($official !== '') {
            return $official;
        }

        $fromPayload = trim((string) data_get($this->payload, 'zheart_invoice_number', ''));

        return $fromPayload !== '' ? $fromPayload : null;
    }

    public function isSubmissionRetry(): bool
    {
        return data_get($this->payload, 'zheart_outcome') === \App\Support\Invoicing\ZHeartSubmissionOutcome::RETRY;
    }

    public function isSubmissionRejected(): bool
    {
        return data_get($this->payload, 'zheart_outcome') === \App\Support\Invoicing\ZHeartSubmissionOutcome::REJECTED;
    }

    public function isSubmissionAccepted(): bool
    {
        return data_get($this->payload, 'zheart_outcome') === \App\Support\Invoicing\ZHeartSubmissionOutcome::ACCEPTED;
    }
}
