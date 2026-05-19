# Purchase Pipeline — Design Spec
**Date:** 2026-05-18  
**Status:** Approved for implementation

---

## Overview

Replace the disconnected purchase screens with a single end-to-end pipeline that tracks every purchase like a package delivery — each record shows exactly which stage it is at, who acted last, and what is needed next.

The pipeline has 8 stages. 3 new database tables are added. All existing models (PurchaseRequest, PurchaseOrder, GRN, SupplierPayment) are reused with minor additions.

---

## The 8 Stages

| # | Stage | Actor | Model |
|---|-------|-------|-------|
| 1 | Purchase Request created | Operations team | `purchase_requests` (existing) |
| 2 | GM digital signature | GM | `purchase_signatures` (NEW) |
| 3 | Suppliers selected + RFQ links sent | Purchasing team | `rfq_invitations` (NEW) |
| 4 | Suppliers submit quotes via private link | Supplier (public portal) | `supplier_quotes` + `supplier_quote_items` (NEW) |
| 5 | Quote comparison + winner selected | Purchasing team | `supplier_quotes` (awarded flag) |
| 6 | LPO issued to winning supplier | Purchasing team | `purchase_orders` (existing, relabelled LPO) |
| 7 | Materials received at site | Warehouse / Store Manager | `goods_receipt_notes` + `grn_items` (existing, + type field) |
| 8 | Payment / cheque issued | Accounts | `supplier_payments` (existing) |

The active stage is stored as a `stage` enum column on `purchase_requests`. It advances automatically when each step is completed.

---

## New Database Tables

### `purchase_signatures`
```
id
purchase_request_id  FK → purchase_requests
signed_by            FK → users
signature_image      TEXT (base64 PNG from canvas)
signed_at            TIMESTAMP
ip_address           STRING nullable
```

### `rfq_invitations`
```
id
purchase_request_id  FK → purchase_requests
supplier_id          FK → suppliers
token                STRING(64) UNIQUE — cryptographically random hex
channel              ENUM: email | whatsapp | both
sent_at              TIMESTAMP nullable
opened_at            TIMESTAMP nullable
expires_at           TIMESTAMP (sent_at + 7 days)
status               ENUM: pending | sent | opened | submitted | declined
timestamps
```

### `supplier_quotes`
```
id
rfq_invitation_id    FK → rfq_invitations
purchase_request_id  FK → purchase_requests (denormalised for easy querying)
supplier_id          FK → suppliers
submitted_at         TIMESTAMP
lead_time_days       INTEGER nullable
payment_terms        STRING nullable
notes                TEXT nullable
total_amount         DECIMAL(12,3)
is_awarded           BOOLEAN default false
award_reason         TEXT nullable
awarded_at           TIMESTAMP nullable
awarded_by           FK → users nullable
timestamps
```

### `supplier_quote_items`
```
id
supplier_quote_id    FK → supplier_quotes
description          TEXT
unit                 STRING(50)
quantity             DECIMAL(10,3)
unit_price           DECIMAL(12,3)
total_price          DECIMAL(12,3)
timestamps
```

---

## Existing Table Changes

### `purchase_requests` — add column
```
stage  ENUM: draft | gm_approval | rfq | quoting | comparison | lpo | receiving | payment | complete
       DEFAULT: draft
```

### `grn_items` — add column
```
type  ENUM: inventory | consumable   DEFAULT: inventory
```

No other existing tables are changed.

---

## Architecture

### Internal routes (auth-protected)
All existing purchase routes remain. New routes added:

```
GET    purchase/pipeline                         purchase.pipeline.index
GET    purchase/requests/{id}/sign               purchase.requests.sign        (GM signature page)
POST   purchase/requests/{id}/sign               purchase.requests.sign.store
GET    purchase/requests/{id}/rfq                purchase.requests.rfq         (select suppliers)
POST   purchase/requests/{id}/rfq                purchase.requests.rfq.store   (send invitations)
GET    purchase/requests/{id}/quotes             purchase.requests.quotes      (view all quotes)
POST   purchase/requests/{id}/quotes/{quote}/award  purchase.requests.quotes.award
GET    purchase/requests/{id}/compare            purchase.requests.compare     (comparison table)
```

### Public routes (no auth)
```
GET    /rfq/{token}    RfqPortalController@show    (supplier quote form)
POST   /rfq/{token}    RfqPortalController@submit  (supplier submits quote)
```

Token is 64 hex chars generated via `bin2hex(random_bytes(32))`. Validated on every request — expired, already-submitted, or unknown tokens show a clear error page.

---

## UI Components

### 1. Pipeline index page (`purchase/pipeline`)
Replaces the current fragmented purchase list. Shows all active purchases as cards, each with:
- Vertical timeline showing completed stages (checked, with date + actor)
- Current stage highlighted in amber with action button
- Upcoming stages grayed out

### 2. GM Signature modal
- HTML5 Canvas — GM draws with mouse or touch
- "Clear" and "Confirm Signature" buttons
- On confirm: canvas serialised to base64 PNG, POSTed to server, stored in `purchase_signatures`
- Signature image displayed on the request detail forever after

### 3. RFQ Invitation screen
- Checklist of suppliers from the suppliers table (search/filter)
- Per-supplier: toggle WhatsApp / Email / Both
- "Send Invitations" generates tokens, logs `rfq_invitations`, sends messages
- WhatsApp: deep link `https://wa.me/{number}?text=...` with the URL embedded
- Email: Laravel `Mail` with the unique URL

### 4. Public supplier portal (`/rfq/{token}`)
- No login, no navigation — clean standalone page
- Shows: your company name, request reference, list of items needed (description + quantity)
- Supplier fills: unit price per item, lead time (days), payment terms, notes
- One submit only — redirects to a thank-you screen
- After submission: `rfq_invitations.status` → submitted, `supplier_quotes` record created

### 5. Quote comparison table
- Side-by-side: one column per supplier who submitted
- Rows: each item, plus totals row, lead time, payment terms
- Lowest price per item highlighted in green
- "Award to this supplier" button per column — opens a modal to enter reason
- Awarding locks the comparison and advances stage to `lpo`

### 6. Vertical timeline (on every purchase detail)
Each completed stage shows:
- Stage name + icon
- Actor name (who did it)
- Timestamp
- Any key data (e.g. "Signed by Ahmed Al-Rashid", "3 quotes received", "LPO sent to Safety Chemical Trading")

Current stage pulses amber. Future stages are gray.

---

## New Controllers / Services

| File | Responsibility |
|------|---------------|
| `PurchasePipelineController` | Pipeline index — lists all purchases by stage |
| `PurchaseSignatureController` | Store GM signature, advance stage |
| `RfqController` | Select suppliers, generate tokens, send invites |
| `SupplierQuoteController` | View quotes, award winner |
| `RfqPortalController` | Public: show form, accept submission |
| `RfqInvitationService` | Generate token, send WhatsApp link + email |
| `PurchaseStageService` | Single place that advances `stage` and logs the event |

---

## New Models

`PurchaseSignature`, `RfqInvitation`, `SupplierQuote`, `SupplierQuoteItem`

---

## Notification Channels

**WhatsApp:** Generate a `wa.me` deep-link with the RFQ URL pre-filled in the message body. User clicks the link in the system → WhatsApp opens on their device → they send it to the supplier. (No WhatsApp API key needed.)

**Email:** Standard Laravel `Mail::to($supplier->email)->send(new RfqInvitationMail($invitation))`.

---

## Constraints & Rules

- A purchase cannot advance past stage 2 (GM sign) without a signature record
- A purchase cannot advance past stage 4 (quoting) with fewer than 1 submitted quote (minimum 1, recommended 3)
- A token expires 7 days after `sent_at`; expired tokens show an expiry message, not an error
- A quote token can only be submitted once; re-visiting after submit shows the thank-you screen
- Awarding a quote is irreversible — all other quotes are marked `declined`
- GRN items default to `inventory`; the receiving form lets the user toggle each item to `consumable`
