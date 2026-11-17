# Z-Heart `POST /api/v1/invoices/clear` — plumber mapping

Contract reference: **z-heart** `docs/API-CLIENT-CONTRACT.md` (private repo `ashique19/z-heart`).

## Simplified (B2C) `buyer.address`

Plumber **omits** `buyer.address` on simplified invoices unless the buyer snapshot has a **complete** Saudi national address (building, street, district, city, 5-digit postal, country SA). Partial objects (e.g. only `country: SA`) caused Z-Heart **422** because its validator treats every present `buyer.address` as requiring full sub-fields. Standard clearance invoices still send `buyer.address` with at least `country: SA` per #154. If Z-Heart should accept minimal `buyer.address` for simplified only, that belongs in the z-heart API contract — not in plumber partial payloads.

## Response shape (success)

Top-level:

- `status`: `ok`
- `request_id`: correlation id (also may appear as `X-Request-Id`)
- `data`: invoice result object

Important `data` fields:

| Field | Meaning |
|-------|---------|
| `signed_xml` | Base64 or raw signed UBL |
| `cleared_xml` | Present when ZATCA cleared the invoice |
| `zatca.ok` | Whether the ZATCA call succeeded |
| `zatca.http_status` | HTTP status from ZATCA |
| `zatca.body.clearanceStatus` | Primary clearance status (often `CLEARED`, `NOT_CLEARED`, …) |
| `zatca.body.validationResults` | Errors / warnings |

Plumber does **not** rely on legacy `data.zatca.status` or top-level `data.reporting_status` for **clear** (those are used on **report**). Normalization lives in `App\Support\Invoicing\ZHeartZatcaPayloadNormalizer`.

## Persisted plumber fields

After resubmit or issue-with-clear:

- `zheart_reporting_status` — lowercase enum (`cleared`, `pending`, `rejected`, …)
- `zheart_zatca` / `zheart_zatca_display` — sanitized `remote_submission` (includes `clearance_status`, `delivery`, validation lines)
- `zheart_zatca_delivery` — `sync` \| `pending` \| `rejected` \| `stub`
- `zheart_zatca_http_status` — ZATCA HTTP status from Z-Heart
- `zheart_request_id` — last Z-Heart request id

The invoice **ZATCA** tab shows clearance status, delivery hint, Z-Heart HTTP status, and ZATCA HTTP status when available.

## Fast 200 responses (~20 ms)

Z-Heart may return **200 in tens of milliseconds** when it **replays a cached successful submission** for the same client + invoice UUID + `clear` operation (idempotent cache). That is **not** proof that ZATCA was called on that request.

**Plumber does not poll** — z-heart exposes no clearance status poll API in `routes/api.php` today.

## Resubmit vs true re-clear

Plumber always **POSTs** `/api/v1/invoices/clear` on resubmit. If Z-Heart keeps serving a cached non-terminal result (e.g. old `NOT_CLEARED`), the UI now shows that status honestly.

**For a true forced re-clear**, Z-Heart needs a change (e.g. bypass idempotent replay on client resubmit, or invalidate `InvoiceSubmission` for that UUID). That cannot be faked in plumber.