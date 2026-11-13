<?php

namespace App\Models;

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Exceptions\IssuedInvoiceImmutableException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{

    protected $fillable = [
        'document_number',
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
        'payload',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => InvoiceDocumentType::class,
            'status' => InvoiceStatus::class,
            'payload' => 'array',
            'issued_at' => 'datetime',
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

    public function isIssued(): bool
    {
        return $this->status === InvoiceStatus::Issued;
    }
}
