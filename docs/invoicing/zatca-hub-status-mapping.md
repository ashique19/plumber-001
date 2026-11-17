# Invoice hub — ZATCA status mapping

This document defines how raw Z-Heart / payload fields map to the **ZATCA Status** badge in the admin invoice table (en / ar / bn use `invoices.zatca_badge.*`).

## Raw sources (precedence)

1. `payload.zheart_zatca.status`
2. `payload.zheart_reporting_status`
3. `payload.signing_submission_status`

## Badge keys → UI label

| Badge key | English | When |
|-----------|---------|------|
| `not_submitted` | Not Submitted | `signing_submission_status = not_submitted`, or no remote submission and not signed |
| `pending` | Pending | `reporting_status = pending`, `signed_locally`, unsigned issued doc, or remote submit without terminal status |
| `cleared` | Cleared | **Standard** (and credit/debit on standard base): `cleared`, `clear`, `accepted`, or `reported` after successful **clear** |
| `reported` | Reported | **Simplified**: `reported`, `warning`, `warnings`, or successful **report** with `zheart_remote_submitted = true` |
| `rejected` | Rejected | `rejected`, `failed`, `error`, `pending_error`, or `zheart_last_error` set |

## Hub tabs (unchanged)

- **ZATCA success** tab: `HubInvoiceZatcaTab::isSuccess()` — includes simplified **Reported** and standard **Cleared**.
- **ZATCA failed** tab: not success and `HubInvoiceZatcaTab::isFailed()`.
- **Resend** row action + bulk **Resend all failed**: `HubInvoiceZatcaTab::canResend()` when Z-Heart is configured — any failed-tab invoice (including **Pending** backlog and never-submitted), not only explicit **Rejected**.

## Print / QR policy

| Document | QR source | Print when not cleared |
|----------|-----------|-------------------------|
| **Standard** | `invoices.qr_tlv_base64` from Z-Heart **clear** response only (no Phase-1 fallback) | Watermark **Not cleared**; Print/Download blocked on hub |
| **Simplified** | Z-Heart `qr` from sign/report (stored in `qr_tlv_base64`); Phase-1 fallback only if unsigned POS receipt |

QR bytes are taken from the API `data.qr` field after clear/report, not parsed from `cleared_xml`.