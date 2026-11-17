@include('print.partials.toolbar-screen', [
    'invoice' => $invoice ?? null,
    'receiptUrl' => $receiptUrl ?? null,
    'cashUrl' => $cashUrl ?? null,
    'a4Url' => $a4Url ?? null,
    'zatcaNotSent' => $zatcaNotSent ?? false,
    'fallbackBackUrl' => $fallbackBackUrl ?? route('admin.invoices'),
    'pdfUrl' => $pdfUrl ?? null,
    'canDownloadPdf' => $canDownloadPdf ?? true,
    'canResubmitZatca' => $canResubmitZatca ?? false,
    'zatcaResubmitUrl' => $zatcaResubmitUrl ?? null,
    'zatcaResubmitLabel' => $zatcaResubmitLabel ?? null,
    'isPdf' => $isPdf ?? false,
])