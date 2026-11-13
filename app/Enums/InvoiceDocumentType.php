<?php

namespace App\Enums;

enum InvoiceDocumentType: string
{
    case Standard = 'standard';
    case Simplified = 'simplified';
    case CreditNote = 'credit_note';
    case DebitNote = 'debit_note';
}
