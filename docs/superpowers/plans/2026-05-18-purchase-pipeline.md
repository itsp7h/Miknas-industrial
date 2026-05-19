# Purchase Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the disconnected purchase screens with an 8-stage pipeline tracker that shows every purchase like a package delivery — stage, actor, timestamp, and the action needed next.

**Architecture:** Add a `stage` enum column to `purchase_requests`, add 3 new tables (`purchase_signatures`, `rfq_invitations`, `supplier_quotes` + items), and wire up 5 new controllers plus 2 services. A `PurchaseStageService` is the single chokepoint for advancing stages. A public `/rfq/{token}` route (outside auth middleware) serves the supplier quote portal.

**Tech Stack:** Laravel 12 / PHP 8.2 / SQLite / Tailwind CSS v3 JIT / Alpine.js v3 / HTML5 Canvas (GM signature) / Laravel Mail (RFQ emails) / `wa.me` deep links (WhatsApp)

---

## File Map

### New migrations
- `database/migrations/2026_05_18_200001_add_stage_to_purchase_requests_table.php`
- `database/migrations/2026_05_18_200002_add_type_to_grn_items_table.php`
- `database/migrations/2026_05_18_200003_create_purchase_signatures_table.php`
- `database/migrations/2026_05_18_200004_create_rfq_invitations_table.php`
- `database/migrations/2026_05_18_200005_create_supplier_quotes_table.php`
- `database/migrations/2026_05_18_200006_create_supplier_quote_items_table.php`

### New / modified models
- `app/Models/PurchaseSignature.php` (new)
- `app/Models/RfqInvitation.php` (new)
- `app/Models/SupplierQuote.php` (new)
- `app/Models/SupplierQuoteItem.php` (new)
- `app/Models/PurchaseRequest.php` (add `stage`, relationships)
- `app/Models/GrnItem.php` (add `type`)

### New services
- `app/Services/PurchaseStageService.php`
- `app/Services/RfqInvitationService.php`

### New controllers
- `app/Http/Controllers/Purchase/PurchasePipelineController.php`
- `app/Http/Controllers/Purchase/PurchaseSignatureController.php`
- `app/Http/Controllers/Purchase/RfqController.php`
- `app/Http/Controllers/Purchase/SupplierQuoteController.php`
- `app/Http/Controllers/Purchase/RfqPortalController.php`

### New mail
- `app/Mail/RfqInvitationMail.php`
- `resources/views/mail/rfq-invitation.blade.php`

### New views
- `resources/views/purchase/pipeline/index.blade.php`
- `resources/views/purchase/pipeline/_timeline.blade.php` (partial)
- `resources/views/purchase/signature/show.blade.php`
- `resources/views/purchase/rfq/show.blade.php`
- `resources/views/purchase/quotes/index.blade.php`
- `resources/views/purchase/quotes/compare.blade.php`
- `resources/views/rfq/show.blade.php` (public portal)
- `resources/views/rfq/submitted.blade.php` (thank-you page)
- `resources/views/rfq/expired.blade.php`

### Modified files
- `routes/web.php` (new routes)
- `resources/views/layouts/app.blade.php` (sidebar link)

---

## Task 1: Schema — add `stage` column to `purchase_requests`

**Files:**
- Create: `database/migrations/2026_05_18_200001_add_stage_to_purchase_requests_table.php`
- Modify: `app/Models/PurchaseRequest.php`

- [ ] **Step 1: Write the migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->enum('stage', [
                'draft', 'gm_approval', 'rfq', 'quoting',
                'comparison', 'lpo', 'receiving', 'payment', 'complete',
            ])->default('draft')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('stage');
        });
    }
};
```

- [ ] **Step 2: Update the PurchaseRequest model**

Add `stage` to `$fillable` and the relationship stubs that later tasks will fill:

```php
protected $fillable = [
    'request_number', 'date', 'project_name', 'department',
    'requested_by_name', 'required_date_text', 'location',
    'remarks', 'status', 'verified_by_name',
    'requested_by', 'approved_by', 'approved_at', 'stage',
];

public function signature()
{
    return $this->hasOne(\App\Models\PurchaseSignature::class);
}

public function rfqInvitations()
{
    return $this->hasMany(\App\Models\RfqInvitation::class);
}

public function supplierQuotes()
{
    return $this->hasMany(\App\Models\SupplierQuote::class);
}

public function awardedQuote()
{
    return $this->hasOne(\App\Models\SupplierQuote::class)->where('is_awarded', true);
}
```

- [ ] **Step 3: Run the migration**

```
php artisan migrate
```

Expected: `Migrated: 2026_05_18_200001_add_stage_to_purchase_requests_table`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_18_200001_add_stage_to_purchase_requests_table.php app/Models/PurchaseRequest.php
git commit -m "feat: add stage column to purchase_requests"
```

---

## Task 2: Schema — add `type` column to `grn_items`

**Files:**
- Create: `database/migrations/2026_05_18_200002_add_type_to_grn_items_table.php`
- Modify: `app/Models/GrnItem.php`

- [ ] **Step 1: Write the migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('grn_items', function (Blueprint $table) {
            $table->enum('type', ['inventory', 'consumable'])->default('inventory')->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('grn_items', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
```

- [ ] **Step 2: Update GrnItem model**

Add `type` to `$fillable`. Read the current file first, then edit:

```php
// In GrnItem.php, add 'type' to the $fillable array:
protected $fillable = [
    'goods_receipt_note_id', 'purchase_order_item_id',
    'item_id', 'quantity_received', 'unit_cost', 'type',
];
```

- [ ] **Step 3: Run the migration**

```
php artisan migrate
```

Expected: `Migrated: 2026_05_18_200002_add_type_to_grn_items_table`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_18_200002_add_type_to_grn_items_table.php app/Models/GrnItem.php
git commit -m "feat: add type (inventory/consumable) to grn_items"
```

---

## Task 3: Schema — new tables (`purchase_signatures`, `rfq_invitations`, `supplier_quotes`, `supplier_quote_items`)

**Files:**
- Create: `database/migrations/2026_05_18_200003_create_purchase_signatures_table.php`
- Create: `database/migrations/2026_05_18_200004_create_rfq_invitations_table.php`
- Create: `database/migrations/2026_05_18_200005_create_supplier_quotes_table.php`
- Create: `database/migrations/2026_05_18_200006_create_supplier_quote_items_table.php`

- [ ] **Step 1: `purchase_signatures` migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('signed_by')->constrained('users')->onDelete('restrict');
            $table->text('signature_image'); // base64 PNG
            $table->timestamp('signed_at');
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_signatures');
    }
};
```

- [ ] **Step 2: `rfq_invitations` migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rfq_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('restrict');
            $table->string('token', 64)->unique();
            $table->enum('channel', ['email', 'whatsapp', 'both'])->default('both');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'opened', 'submitted', 'declined'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_invitations');
    }
};
```

- [ ] **Step 3: `supplier_quotes` migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_invitation_id')->constrained()->onDelete('cascade');
            $table->foreignId('purchase_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('restrict');
            $table->timestamp('submitted_at');
            $table->integer('lead_time_days')->nullable();
            $table->string('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_amount', 12, 3)->default(0);
            $table->boolean('is_awarded')->default(false);
            $table->text('award_reason')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_quotes');
    }
};
```

- [ ] **Step 4: `supplier_quote_items` migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_quote_id')->constrained()->onDelete('cascade');
            $table->text('description');
            $table->string('unit', 50)->nullable();
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 12, 3);
            $table->decimal('total_price', 12, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_quote_items');
    }
};
```

- [ ] **Step 5: Run migrations**

```
php artisan migrate
```

Expected: 4 new lines, each `Migrated: 2026_05_18_2000{03,04,05,06}_...`

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_05_18_200003_create_purchase_signatures_table.php
git add database/migrations/2026_05_18_200004_create_rfq_invitations_table.php
git add database/migrations/2026_05_18_200005_create_supplier_quotes_table.php
git add database/migrations/2026_05_18_200006_create_supplier_quote_items_table.php
git commit -m "feat: add pipeline tables (signatures, rfq_invitations, supplier_quotes)"
```

---

## Task 4: New models

**Files:**
- Create: `app/Models/PurchaseSignature.php`
- Create: `app/Models/RfqInvitation.php`
- Create: `app/Models/SupplierQuote.php`
- Create: `app/Models/SupplierQuoteItem.php`

- [ ] **Step 1: PurchaseSignature model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseSignature extends Model
{
    protected $fillable = [
        'purchase_request_id', 'signed_by', 'signature_image', 'signed_at', 'ip_address',
    ];

    protected $casts = ['signed_at' => 'datetime'];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function signedBy()
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
```

- [ ] **Step 2: RfqInvitation model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RfqInvitation extends Model
{
    protected $fillable = [
        'purchase_request_id', 'supplier_id', 'token', 'channel',
        'sent_at', 'opened_at', 'expires_at', 'status',
    ];

    protected $casts = [
        'sent_at'    => 'datetime',
        'opened_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function quote()
    {
        return $this->hasOne(SupplierQuote::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }
}
```

- [ ] **Step 3: SupplierQuote model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierQuote extends Model
{
    protected $fillable = [
        'rfq_invitation_id', 'purchase_request_id', 'supplier_id',
        'submitted_at', 'lead_time_days', 'payment_terms', 'notes',
        'total_amount', 'is_awarded', 'award_reason', 'awarded_at', 'awarded_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'awarded_at'   => 'datetime',
        'is_awarded'   => 'boolean',
    ];

    public function rfqInvitation()
    {
        return $this->belongsTo(RfqInvitation::class);
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(SupplierQuoteItem::class);
    }

    public function awardedBy()
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}
```

- [ ] **Step 4: SupplierQuoteItem model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierQuoteItem extends Model
{
    protected $fillable = [
        'supplier_quote_id', 'description', 'unit', 'quantity', 'unit_price', 'total_price',
    ];

    public function quote()
    {
        return $this->belongsTo(SupplierQuote::class, 'supplier_quote_id');
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Models/PurchaseSignature.php app/Models/RfqInvitation.php app/Models/SupplierQuote.php app/Models/SupplierQuoteItem.php
git commit -m "feat: add pipeline models (PurchaseSignature, RfqInvitation, SupplierQuote, SupplierQuoteItem)"
```

---

## Task 5: PurchaseStageService — single place that advances stage

**Files:**
- Create: `app/Services/PurchaseStageService.php`

This service is the only place that changes `purchase_requests.stage`. All controllers call it.

- [ ] **Step 1: Write the service**

```php
<?php
namespace App\Services;

use App\Models\PurchaseRequest;

class PurchaseStageService
{
    const STAGES = [
        'draft', 'gm_approval', 'rfq', 'quoting',
        'comparison', 'lpo', 'receiving', 'payment', 'complete',
    ];

    public function advance(PurchaseRequest $request): void
    {
        $current = array_search($request->stage, self::STAGES);
        if ($current === false || $current === count(self::STAGES) - 1) {
            return;
        }
        $request->update(['stage' => self::STAGES[$current + 1]]);
    }

    public function setStage(PurchaseRequest $request, string $stage): void
    {
        abort_unless(in_array($stage, self::STAGES), 422, 'Invalid stage');
        $request->update(['stage' => $stage]);
    }

    public function stageIndex(string $stage): int
    {
        return array_search($stage, self::STAGES) ?: 0;
    }

    public function stageLabel(string $stage): string
    {
        return match ($stage) {
            'draft'      => 'Purchase Request',
            'gm_approval'=> 'GM Signature',
            'rfq'        => 'Select Suppliers',
            'quoting'    => 'Awaiting Quotes',
            'comparison' => 'Quote Comparison',
            'lpo'        => 'LPO Issued',
            'receiving'  => 'Receiving Materials',
            'payment'    => 'Payment',
            'complete'   => 'Complete',
            default      => ucfirst($stage),
        };
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/PurchaseStageService.php
git commit -m "feat: add PurchaseStageService for stage advancement"
```

---

## Task 6: GM Signature controller + canvas UI

**Files:**
- Create: `app/Http/Controllers/Purchase/PurchaseSignatureController.php`
- Create: `resources/views/purchase/signature/show.blade.php`

- [ ] **Step 1: Write the controller**

```php
<?php
namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\PurchaseSignature;
use App\Services\PurchaseStageService;
use Illuminate\Http\Request;

class PurchaseSignatureController extends Controller
{
    public function show(PurchaseRequest $request)
    {
        return view('purchase.signature.show', compact('request'));
    }

    public function store(Request $request, PurchaseRequest $purchaseRequest, PurchaseStageService $stages)
    {
        $validated = $request->validate([
            'signature_image' => ['required', 'string', 'starts_with:data:image/png;base64,'],
        ]);

        // Prevent double-signing
        if ($purchaseRequest->signature) {
            return back()->with('error', 'This request has already been signed.');
        }

        PurchaseSignature::create([
            'purchase_request_id' => $purchaseRequest->id,
            'signed_by'           => auth()->id(),
            'signature_image'     => $validated['signature_image'],
            'signed_at'           => now(),
            'ip_address'          => $request->ip(),
        ]);

        $stages->advance($purchaseRequest);

        return redirect()->route('purchase.pipeline.index')
            ->with('success', 'Signature saved. Request approved and moved to RFQ stage.');
    }
}
```

- [ ] **Step 2: Write the signature canvas view**

```blade
@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4">
  <div style="background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden;">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#7c3aed,#4f46e5);padding:24px 28px;">
      <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;">GM Digital Signature</div>
      <div style="font-size:20px;font-weight:700;color:#fff;margin-top:4px;">{{ $request->request_number }}</div>
      <div style="font-size:13px;color:rgba(255,255,255,.8);margin-top:2px;">{{ $request->project_name }}</div>
    </div>

    <div style="padding:28px;">

      @if($request->signature)
        <!-- Already signed — show the signature -->
        <div style="text-align:center;margin-bottom:20px;">
          <img src="{{ $request->signature->signature_image }}" style="max-width:100%;border:1px solid #e2e8f0;border-radius:8px;">
          <div style="font-size:12px;color:#64748b;margin-top:8px;">
            Signed by {{ $request->signature->signedBy->name }}
            on {{ $request->signature->signed_at->format('d M Y, H:i') }}
          </div>
        </div>
      @else
        <!-- Signature pad -->
        <p style="font-size:13px;color:#475569;margin-bottom:16px;">Please draw your signature below using your mouse or touch screen.</p>

        <canvas id="sig-canvas" width="600" height="180"
          style="width:100%;border:2px solid #e2e8f0;border-radius:10px;cursor:crosshair;touch-action:none;"></canvas>

        <div style="display:flex;gap:10px;margin-top:14px;">
          <button onclick="clearCanvas()" type="button"
            style="flex:1;padding:10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;color:#64748b;background:#f8fafc;cursor:pointer;">
            Clear
          </button>
          <button onclick="submitSignature()" type="button"
            style="flex:2;padding:10px;border:none;border-radius:8px;font-size:13px;font-weight:700;color:#fff;background:linear-gradient(135deg,#7c3aed,#4f46e5);cursor:pointer;">
            Confirm Signature
          </button>
        </div>

        <form id="sig-form" method="POST" action="{{ route('purchase.requests.sign.store', $request) }}">
          @csrf
          <input type="hidden" name="signature_image" id="sig-data">
        </form>
      @endif

    </div>
  </div>
</div>

<script>
const canvas = document.getElementById('sig-canvas');
if (canvas) {
  const ctx = canvas.getContext('2d');
  let drawing = false;

  function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    const src = e.touches ? e.touches[0] : e;
    return { x: (src.clientX - rect.left) * scaleX, y: (src.clientY - rect.top) * scaleY };
  }

  canvas.addEventListener('mousedown', e => { drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
  canvas.addEventListener('mousemove', e => { if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.strokeStyle = '#1e293b'; ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.stroke(); });
  canvas.addEventListener('mouseup', () => drawing = false);
  canvas.addEventListener('mouseleave', () => drawing = false);
  canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }, { passive: false });
  canvas.addEventListener('touchmove', e => { e.preventDefault(); if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.strokeStyle = '#1e293b'; ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.stroke(); }, { passive: false });
  canvas.addEventListener('touchend', () => drawing = false);
}

function clearCanvas() {
  const ctx = document.getElementById('sig-canvas').getContext('2d');
  ctx.clearRect(0, 0, 600, 180);
}

function submitSignature() {
  const canvas = document.getElementById('sig-canvas');
  const data = canvas.toDataURL('image/png');
  // Check if canvas is blank
  const blank = document.createElement('canvas');
  blank.width = canvas.width; blank.height = canvas.height;
  if (data === blank.toDataURL('image/png')) {
    showToast('Please draw your signature first.', 'warn');
    return;
  }
  document.getElementById('sig-data').value = data;
  document.getElementById('sig-form').submit();
}
</script>
@endsection
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Purchase/PurchaseSignatureController.php resources/views/purchase/signature/show.blade.php
git commit -m "feat: GM signature canvas — controller + view"
```

---

## Task 7: RfqInvitationService + RfqController

**Files:**
- Create: `app/Services/RfqInvitationService.php`
- Create: `app/Mail/RfqInvitationMail.php`
- Create: `resources/views/mail/rfq-invitation.blade.php`
- Create: `app/Http/Controllers/Purchase/RfqController.php`
- Create: `resources/views/purchase/rfq/show.blade.php`

- [ ] **Step 1: Write RfqInvitationService**

```php
<?php
namespace App\Services;

use App\Mail\RfqInvitationMail;
use App\Models\PurchaseRequest;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use Illuminate\Support\Facades\Mail;

class RfqInvitationService
{
    public function invite(PurchaseRequest $purchaseRequest, Supplier $supplier, string $channel): RfqInvitation
    {
        $token = bin2hex(random_bytes(32)); // 64 hex chars

        $invitation = RfqInvitation::create([
            'purchase_request_id' => $purchaseRequest->id,
            'supplier_id'         => $supplier->id,
            'token'               => $token,
            'channel'             => $channel,
            'sent_at'             => now(),
            'expires_at'          => now()->addDays(7),
            'status'              => 'sent',
        ]);

        if (in_array($channel, ['email', 'both']) && $supplier->email) {
            Mail::to($supplier->email)->send(new RfqInvitationMail($invitation));
        }

        return $invitation;
    }

    public function whatsappLink(RfqInvitation $invitation): string
    {
        $url  = route('rfq.show', $invitation->token);
        $text = "Hello {$invitation->supplier->name},\n\nYou are invited to submit a quote for purchase request {$invitation->purchaseRequest->request_number}.\n\nPlease click the link below to submit your quote:\n{$url}\n\nThis link expires in 7 days and can only be used once.";
        $phone = preg_replace('/\D/', '', $invitation->supplier->phone ?? '');
        return "https://wa.me/{$phone}?text=" . rawurlencode($text);
    }
}
```

- [ ] **Step 2: Write RfqInvitationMail**

```php
<?php
namespace App\Mail;

use App\Models\RfqInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RfqInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RfqInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Quote Request — ' . $this->invitation->purchaseRequest->request_number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.rfq-invitation');
    }
}
```

- [ ] **Step 3: Write the email blade template**

```blade
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;background:#f8fafc;margin:0;padding:20px;">
  <div style="max-width:500px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;box-shadow:0 2px 8px rgba(0,0,0,.08);">
    <div style="font-size:20px;font-weight:700;color:#1e293b;margin-bottom:4px;">Quote Request</div>
    <div style="font-size:13px;color:#64748b;margin-bottom:24px;">{{ $invitation->purchaseRequest->request_number }}</div>

    <p style="font-size:14px;color:#334155;margin-bottom:16px;">Dear {{ $invitation->supplier->name }},</p>
    <p style="font-size:14px;color:#334155;margin-bottom:24px;">
      You have been invited to submit a price quotation for a purchase request. Please click the button below to view the required items and submit your quote.
    </p>

    <a href="{{ route('rfq.show', $invitation->token) }}"
       style="display:inline-block;background:#2563eb;color:#fff;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:700;text-decoration:none;margin-bottom:24px;">
      Submit Quote →
    </a>

    <p style="font-size:12px;color:#94a3b8;">This link expires on {{ $invitation->expires_at->format('d M Y') }} and can only be submitted once.</p>
  </div>
</body>
</html>
```

- [ ] **Step 4: Write RfqController**

```php
<?php
namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Services\PurchaseStageService;
use App\Services\RfqInvitationService;
use Illuminate\Http\Request;

class RfqController extends Controller
{
    public function show(PurchaseRequest $request)
    {
        $suppliers   = Supplier::where('is_active', true)->orderBy('name')->get();
        $invitations = $request->rfqInvitations()->with('supplier', 'quote')->get();
        return view('purchase.rfq.show', compact('request', 'suppliers', 'invitations'));
    }

    public function store(Request $request, PurchaseRequest $purchaseRequest, RfqInvitationService $service, PurchaseStageService $stages)
    {
        $validated = $request->validate([
            'suppliers'            => ['required', 'array', 'min:1'],
            'suppliers.*.id'       => ['required', 'exists:suppliers,id'],
            'suppliers.*.channel'  => ['required', 'in:email,whatsapp,both'],
        ]);

        $alreadyInvited = $purchaseRequest->rfqInvitations()->pluck('supplier_id')->toArray();

        foreach ($validated['suppliers'] as $entry) {
            if (in_array($entry['id'], $alreadyInvited)) {
                continue; // skip already-invited suppliers
            }
            $supplier = Supplier::findOrFail($entry['id']);
            $service->invite($purchaseRequest, $supplier, $entry['channel']);
        }

        $stages->setStage($purchaseRequest, 'quoting');

        return redirect()->route('purchase.requests.rfq', $purchaseRequest)
            ->with('success', 'Invitations sent. Waiting for supplier quotes.');
    }
}
```

- [ ] **Step 5: Write the RFQ selection view**

```blade
@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto py-8 px-4">
  <div style="background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden;">

    <div style="background:linear-gradient(135deg,#0ea5e9,#0284c7);padding:24px 28px;">
      <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;">RFQ — Select Suppliers</div>
      <div style="font-size:20px;font-weight:700;color:#fff;margin-top:4px;">{{ $request->request_number }}</div>
    </div>

    <div style="padding:28px;">

      @if($invitations->count())
      <div style="margin-bottom:24px;">
        <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:10px;">Already Invited</div>
        <div style="display:flex;flex-direction:column;gap:8px;">
          @foreach($invitations as $inv)
          <div style="display:flex;align-items:center;justify-content:space-between;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 14px;">
            <div>
              <div style="font-size:13px;font-weight:600;color:#0f172a;">{{ $inv->supplier->name }}</div>
              <div style="font-size:11px;color:#64748b;">{{ ucfirst($inv->channel) }} · {{ $inv->sent_at?->format('d M Y') }}</div>
            </div>
            <div style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;
              background:{{ $inv->status === 'submitted' ? '#dcfce7' : '#fef9c3' }};
              color:{{ $inv->status === 'submitted' ? '#15803d' : '#92400e' }};">
              {{ ucfirst($inv->status) }}
            </div>
          </div>
          @endforeach
        </div>
      </div>
      @endif

      <form method="POST" action="{{ route('purchase.requests.rfq.store', $request) }}" id="rfq-form">
        @csrf
        <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:10px;">Add Suppliers</div>

        <input type="text" placeholder="Search suppliers..." oninput="filterSuppliers(this.value)"
          style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;margin-bottom:12px;box-sizing:border-box;">

        <div id="supplier-list" style="display:flex;flex-direction:column;gap:6px;max-height:360px;overflow-y:auto;">
          @foreach($suppliers as $supplier)
          @php $alreadyInvited = $invitations->where('supplier_id', $supplier->id)->first(); @endphp
          <div class="sup-row" data-name="{{ strtolower($supplier->name) }}"
            style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;{{ $alreadyInvited ? 'opacity:.5;pointer-events:none;' : '' }}">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;flex:1;">
              <input type="checkbox" name="suppliers[]" value="{{ $supplier->id }}" data-idx="{{ $supplier->id }}"
                onchange="toggleSupplier(this)"
                style="width:16px;height:16px;cursor:pointer;" {{ $alreadyInvited ? 'disabled' : '' }}>
              <div>
                <div style="font-size:13px;font-weight:600;color:#0f172a;">{{ $supplier->name }}</div>
                <div style="font-size:11px;color:#64748b;">{{ $supplier->email ?? '—' }} · {{ $supplier->phone ?? '—' }}</div>
              </div>
            </label>
            <div id="channel-{{ $supplier->id }}" style="display:none;gap:6px;">
              <input type="hidden" name="suppliers[{{ $supplier->id }}][id]" value="{{ $supplier->id }}">
              <label style="font-size:11px;font-weight:600;cursor:pointer;">
                <input type="radio" name="suppliers[{{ $supplier->id }}][channel]" value="whatsapp"> WhatsApp
              </label>
              <label style="font-size:11px;font-weight:600;cursor:pointer;">
                <input type="radio" name="suppliers[{{ $supplier->id }}][channel]" value="email"> Email
              </label>
              <label style="font-size:11px;font-weight:600;cursor:pointer;">
                <input type="radio" name="suppliers[{{ $supplier->id }}][channel]" value="both" checked> Both
              </label>
            </div>
          </div>
          @endforeach
        </div>

        <button type="submit" style="width:100%;margin-top:20px;padding:12px;background:#0ea5e9;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
          Send Invitations
        </button>
      </form>
    </div>
  </div>
</div>

<script>
function toggleSupplier(cb) {
  const channelDiv = document.getElementById('channel-' + cb.dataset.idx);
  channelDiv.style.display = cb.checked ? 'flex' : 'none';
}
function filterSuppliers(q) {
  document.querySelectorAll('.sup-row').forEach(row => {
    row.style.display = row.dataset.name.includes(q.toLowerCase()) ? '' : 'none';
  });
}
</script>
@endsection
```

- [ ] **Step 6: Commit**

```bash
git add app/Services/RfqInvitationService.php app/Mail/RfqInvitationMail.php resources/views/mail/rfq-invitation.blade.php app/Http/Controllers/Purchase/RfqController.php resources/views/purchase/rfq/show.blade.php
git commit -m "feat: RFQ invitation service, mail, controller and view"
```

---

## Task 8: Public supplier quote portal (RfqPortalController + views)

**Files:**
- Create: `app/Http/Controllers/Purchase/RfqPortalController.php`
- Create: `resources/views/rfq/show.blade.php`
- Create: `resources/views/rfq/submitted.blade.php`
- Create: `resources/views/rfq/expired.blade.php`

- [ ] **Step 1: Write RfqPortalController**

```php
<?php
namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\RfqInvitation;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Services\PurchaseStageService;
use Illuminate\Http\Request;

class RfqPortalController extends Controller
{
    private function resolveInvitation(string $token): RfqInvitation
    {
        $invitation = RfqInvitation::where('token', $token)
            ->with(['purchaseRequest.items', 'supplier'])
            ->firstOrFail();
        return $invitation;
    }

    public function show(string $token)
    {
        $invitation = $this->resolveInvitation($token);

        if ($invitation->isSubmitted()) {
            return view('rfq.submitted', compact('invitation'));
        }

        if ($invitation->isExpired()) {
            return view('rfq.expired', compact('invitation'));
        }

        // Mark as opened on first visit
        if ($invitation->status === 'sent') {
            $invitation->update(['status' => 'opened', 'opened_at' => now()]);
        }

        $purchaseRequest = $invitation->purchaseRequest;
        $items           = $purchaseRequest->items;

        return view('rfq.show', compact('invitation', 'purchaseRequest', 'items'));
    }

    public function submit(Request $request, string $token, PurchaseStageService $stages)
    {
        $invitation = $this->resolveInvitation($token);

        if ($invitation->isSubmitted() || $invitation->isExpired()) {
            abort(403);
        }

        $validated = $request->validate([
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'payment_terms'  => ['nullable', 'string', 'max:200'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'items'          => ['required', 'array'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $purchaseItems = $invitation->purchaseRequest->items;

        $quote = SupplierQuote::create([
            'rfq_invitation_id'  => $invitation->id,
            'purchase_request_id'=> $invitation->purchase_request_id,
            'supplier_id'        => $invitation->supplier_id,
            'submitted_at'       => now(),
            'lead_time_days'     => $validated['lead_time_days'],
            'payment_terms'      => $validated['payment_terms'],
            'notes'              => $validated['notes'],
            'total_amount'       => 0,
        ]);

        $total = 0;
        foreach ($purchaseItems as $i => $item) {
            $unitPrice  = (float)($validated['items'][$i]['unit_price'] ?? 0);
            $totalPrice = $unitPrice * $item->quantity;
            $total     += $totalPrice;

            SupplierQuoteItem::create([
                'supplier_quote_id' => $quote->id,
                'description'       => $item->description,
                'unit'              => $item->unit ?? '',
                'quantity'          => $item->quantity,
                'unit_price'        => $unitPrice,
                'total_price'       => $totalPrice,
            ]);
        }

        $quote->update(['total_amount' => $total]);
        $invitation->update(['status' => 'submitted']);

        return view('rfq.submitted', compact('invitation'));
    }
}
```

- [ ] **Step 2: Public quote form view (`resources/views/rfq/show.blade.php`)**

Note: This view uses no auth layout — it is a standalone public page.

```blade
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quote Request — {{ $purchaseRequest->request_number }}</title>
  <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family: system-ui, -apple-system, sans-serif; background:#f1f5f9; min-height:100vh; padding:20px; }
    .card { background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.08); max-width:700px; margin:0 auto; overflow:hidden; }
    .header { background:linear-gradient(135deg,#2563eb,#1d4ed8); padding:28px 32px; }
    .body { padding:32px; }
    label { display:block; font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin-bottom:5px; }
    input[type=text], input[type=number], textarea { width:100%; padding:9px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; }
    input:focus, textarea:focus { outline:none; border-color:#2563eb; }
    .btn { width:100%; padding:14px; background:#2563eb; color:#fff; border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; margin-top:24px; }
    table { width:100%; border-collapse:collapse; font-size:13px; }
    th { background:#f8fafc; padding:10px 12px; text-align:left; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; }
    td { padding:10px 12px; border-bottom:1px solid #f1f5f9; }
  </style>
</head>
<body>
<div class="card">
  <div class="header">
    <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;">Quote Request</div>
    <div style="font-size:22px;font-weight:700;color:#fff;margin-top:4px;">{{ $purchaseRequest->request_number }}</div>
    <div style="font-size:13px;color:rgba(255,255,255,.8);margin-top:2px;">{{ $purchaseRequest->project_name }}</div>
  </div>

  <div class="body">
    <p style="font-size:14px;color:#334155;margin-bottom:24px;">
      Hello <strong>{{ $invitation->supplier->name }}</strong>, please fill in your prices for the items below and submit your quote. This link can only be submitted once.
    </p>

    <form method="POST" action="{{ route('rfq.submit', $invitation->token) }}">
      @csrf

      <!-- Items table -->
      <div style="margin-bottom:24px;overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Description</th>
              <th>Qty</th>
              <th>Unit</th>
              <th>Unit Price (BD)</th>
              <th>Total (BD)</th>
            </tr>
          </thead>
          <tbody>
            @foreach($items as $i => $item)
            <tr>
              <td>{{ $i + 1 }}</td>
              <td>{{ $item->description }}</td>
              <td>{{ $item->quantity }}</td>
              <td>{{ $item->unit ?? '—' }}</td>
              <td>
                <input type="number" name="items[{{ $i }}][unit_price]" min="0" step="0.001" required
                  oninput="calcRow({{ $i }}, {{ $item->quantity }})"
                  id="up-{{ $i }}" style="width:110px;">
              </td>
              <td id="tot-{{ $i }}" style="font-weight:600;">—</td>
            </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <td colspan="5" style="text-align:right;font-weight:700;font-size:13px;padding:12px;">Grand Total:</td>
              <td style="font-weight:700;font-size:14px;color:#2563eb;" id="grand-total">BD 0.000</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Terms -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
          <label>Lead Time (days)</label>
          <input type="number" name="lead_time_days" min="0">
        </div>
        <div>
          <label>Payment Terms</label>
          <input type="text" name="payment_terms" placeholder="e.g. 30 days net">
        </div>
      </div>
      <div style="margin-bottom:8px;">
        <label>Notes</label>
        <textarea name="notes" rows="3" placeholder="Any additional notes..."></textarea>
      </div>

      <button class="btn">Submit My Quote →</button>
    </form>
  </div>
</div>

<script>
const totals = {};
function calcRow(i, qty) {
  const up  = parseFloat(document.getElementById('up-' + i).value) || 0;
  const tot = up * qty;
  totals[i] = tot;
  document.getElementById('tot-' + i).textContent = 'BD ' + tot.toFixed(3);
  const grand = Object.values(totals).reduce((a,b) => a+b, 0);
  document.getElementById('grand-total').textContent = 'BD ' + grand.toFixed(3);
}
</script>
</body>
</html>
```

- [ ] **Step 3: Thank-you view (`resources/views/rfq/submitted.blade.php`)**

```blade
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Quote Submitted</title>
  <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family: system-ui, sans-serif; background:#f0fdf4; min-height:100vh; display:flex; align-items:center; justify-content:center; }
    .card { background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.08); padding:48px 40px; max-width:480px; text-align:center; }
  </style>
</head>
<body>
  <div class="card">
    <div style="font-size:56px;margin-bottom:16px;">✅</div>
    <div style="font-size:22px;font-weight:700;color:#15803d;margin-bottom:8px;">Quote Submitted</div>
    <div style="font-size:14px;color:#475569;">Thank you, {{ $invitation->supplier->name }}. Your quote for <strong>{{ $invitation->purchaseRequest->request_number }}</strong> has been received. You will be contacted if you are selected.</div>
  </div>
</body>
</html>
```

- [ ] **Step 4: Expired view (`resources/views/rfq/expired.blade.php`)**

```blade
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Link Expired</title>
  <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family: system-ui, sans-serif; background:#fef2f2; min-height:100vh; display:flex; align-items:center; justify-content:center; }
    .card { background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.08); padding:48px 40px; max-width:480px; text-align:center; }
  </style>
</head>
<body>
  <div class="card">
    <div style="font-size:56px;margin-bottom:16px;">⏰</div>
    <div style="font-size:22px;font-weight:700;color:#dc2626;margin-bottom:8px;">Link Expired</div>
    <div style="font-size:14px;color:#475569;">This quote invitation link has expired. Please contact the purchasing team if you still wish to submit a quote.</div>
  </div>
</body>
</html>
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Purchase/RfqPortalController.php resources/views/rfq/
git commit -m "feat: public supplier quote portal — form, submitted, expired views"
```

---

## Task 9: SupplierQuoteController — view quotes + compare + award

**Files:**
- Create: `app/Http/Controllers/Purchase/SupplierQuoteController.php`
- Create: `resources/views/purchase/quotes/index.blade.php`
- Create: `resources/views/purchase/quotes/compare.blade.php`

- [ ] **Step 1: Write SupplierQuoteController**

```php
<?php
namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\SupplierQuote;
use App\Services\PurchaseStageService;
use Illuminate\Http\Request;

class SupplierQuoteController extends Controller
{
    public function index(PurchaseRequest $request)
    {
        $quotes = $request->supplierQuotes()->with('supplier', 'items')->get();
        return view('purchase.quotes.index', compact('request', 'quotes'));
    }

    public function compare(PurchaseRequest $request)
    {
        $quotes = $request->supplierQuotes()->with('supplier', 'items')->get();
        $items  = $request->items;
        return view('purchase.quotes.compare', compact('request', 'quotes', 'items'));
    }

    public function award(Request $request, PurchaseRequest $purchaseRequest, SupplierQuote $quote, PurchaseStageService $stages)
    {
        $validated = $request->validate([
            'award_reason' => ['required', 'string', 'min:5'],
        ]);

        if ($purchaseRequest->awardedQuote) {
            return back()->with('error', 'A quote has already been awarded for this request.');
        }

        // Mark all other quotes as not awarded (they remain in the table)
        $purchaseRequest->supplierQuotes()->where('id', '!=', $quote->id)->update(['is_awarded' => false]);

        $quote->update([
            'is_awarded'   => true,
            'award_reason' => $validated['award_reason'],
            'awarded_at'   => now(),
            'awarded_by'   => auth()->id(),
        ]);

        $stages->setStage($purchaseRequest, 'lpo');

        return redirect()->route('purchase.pipeline.index')
            ->with('success', "Quote from {$quote->supplier->name} awarded. Ready to issue LPO.");
    }
}
```

- [ ] **Step 2: Write quotes index view**

```blade
@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4">
  <div style="background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden;">

    <div style="background:linear-gradient(135deg,#f59e0b,#d97706);padding:24px 28px;display:flex;align-items:center;justify-content:space-between;">
      <div>
        <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;">Supplier Quotes</div>
        <div style="font-size:20px;font-weight:700;color:#fff;margin-top:4px;">{{ $request->request_number }}</div>
      </div>
      @if($quotes->count() >= 1)
      <a href="{{ route('purchase.requests.compare', $request) }}"
        style="padding:10px 20px;background:#fff;color:#d97706;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;">
        Compare →
      </a>
      @endif
    </div>

    <div style="padding:24px;">
      @if($quotes->isEmpty())
        <p style="text-align:center;color:#94a3b8;padding:40px 0;">No quotes received yet.</p>
      @else
        <div style="display:flex;flex-direction:column;gap:12px;">
          @foreach($quotes as $quote)
          <div style="border:1px solid {{ $quote->is_awarded ? '#bbf7d0' : '#e2e8f0' }};border-radius:12px;padding:16px 20px;background:{{ $quote->is_awarded ? '#f0fdf4' : '#fff' }};">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
              <div style="font-size:14px;font-weight:700;color:#0f172a;">{{ $quote->supplier->name }}</div>
              <div style="font-size:16px;font-weight:700;color:#2563eb;">BD {{ number_format($quote->total_amount, 3) }}</div>
            </div>
            <div style="font-size:12px;color:#64748b;">
              Lead time: {{ $quote->lead_time_days ? $quote->lead_time_days.' days' : '—' }} ·
              Terms: {{ $quote->payment_terms ?: '—' }} ·
              Submitted: {{ $quote->submitted_at->format('d M Y') }}
            </div>
            @if($quote->is_awarded)
              <div style="margin-top:8px;font-size:11px;font-weight:700;color:#15803d;">✓ Awarded</div>
            @endif
          </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
```

- [ ] **Step 3: Write quote comparison view**

```blade
@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto py-8 px-4">
  <div style="background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden;">

    <div style="background:linear-gradient(135deg,#f59e0b,#d97706);padding:24px 28px;">
      <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;">Quote Comparison</div>
      <div style="font-size:20px;font-weight:700;color:#fff;margin-top:4px;">{{ $request->request_number }}</div>
    </div>

    <div style="overflow-x:auto;padding:24px;">
      @if($quotes->isEmpty())
        <p style="text-align:center;color:#94a3b8;padding:40px 0;">No quotes to compare.</p>
      @else
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
          <tr>
            <th style="padding:10px 12px;text-align:left;background:#f8fafc;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;min-width:180px;">Item</th>
            @foreach($quotes as $quote)
            <th style="padding:10px 12px;text-align:center;background:#f8fafc;font-size:12px;font-weight:700;color:#0f172a;min-width:140px;">
              {{ $quote->supplier->name }}<br>
              <span style="font-size:10px;font-weight:400;color:#64748b;">{{ $quote->submitted_at->format('d M') }}</span>
            </th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          {{-- Items rows --}}
          @foreach($items as $i => $reqItem)
          <tr>
            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;font-weight:500;">
              {{ $reqItem->description }}<br>
              <span style="font-size:11px;color:#94a3b8;">Qty: {{ $reqItem->quantity }}</span>
            </td>
            @php
              $rowPrices = $quotes->map(fn($q) => optional($q->items->get($i))->unit_price ?? null)->filter();
              $minPrice  = $rowPrices->min();
            @endphp
            @foreach($quotes as $q)
            @php $qItem = $q->items->get($i); @endphp
            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;text-align:center;
              background:{{ $qItem && $qItem->unit_price == $minPrice ? '#f0fdf4' : '' }};">
              @if($qItem)
                <div style="font-weight:600;color:{{ $qItem->unit_price == $minPrice ? '#15803d' : '#0f172a' }};">
                  BD {{ number_format($qItem->unit_price, 3) }}
                </div>
                <div style="font-size:11px;color:#64748b;">Total: BD {{ number_format($qItem->total_price, 3) }}</div>
              @else
                <span style="color:#cbd5e1;">—</span>
              @endif
            </td>
            @endforeach
          </tr>
          @endforeach

          {{-- Totals row --}}
          <tr style="background:#f8fafc;">
            <td style="padding:12px;font-weight:700;">Grand Total</td>
            @php $minTotal = $quotes->min('total_amount'); @endphp
            @foreach($quotes as $quote)
            <td style="padding:12px;text-align:center;font-weight:700;font-size:14px;
              color:{{ $quote->total_amount == $minTotal ? '#15803d' : '#0f172a' }};">
              BD {{ number_format($quote->total_amount, 3) }}
            </td>
            @endforeach
          </tr>

          {{-- Lead time --}}
          <tr>
            <td style="padding:10px 12px;color:#64748b;font-size:12px;">Lead Time</td>
            @foreach($quotes as $quote)
            <td style="padding:10px 12px;text-align:center;font-size:12px;color:#64748b;">
              {{ $quote->lead_time_days ? $quote->lead_time_days.' days' : '—' }}
            </td>
            @endforeach
          </tr>

          {{-- Payment terms --}}
          <tr>
            <td style="padding:10px 12px;color:#64748b;font-size:12px;">Payment Terms</td>
            @foreach($quotes as $quote)
            <td style="padding:10px 12px;text-align:center;font-size:12px;color:#64748b;">
              {{ $quote->payment_terms ?: '—' }}
            </td>
            @endforeach
          </tr>

          {{-- Award buttons --}}
          @if(!$request->awardedQuote)
          <tr style="background:#fffbeb;">
            <td style="padding:12px;font-weight:700;font-size:12px;color:#92400e;">Award to:</td>
            @foreach($quotes as $quote)
            <td style="padding:12px;text-align:center;">
              <button onclick="openAwardModal({{ $quote->id }}, '{{ addslashes($quote->supplier->name) }}')"
                style="padding:8px 14px;background:#f59e0b;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;">
                Award →
              </button>
            </td>
            @endforeach
          </tr>
          @else
          <tr style="background:#f0fdf4;">
            <td colspan="{{ $quotes->count() + 1 }}" style="padding:12px;text-align:center;font-size:13px;font-weight:700;color:#15803d;">
              ✓ Quote awarded to {{ $request->awardedQuote->supplier->name }}
            </td>
          </tr>
          @endif
        </tbody>
      </table>
      @endif
    </div>
  </div>
</div>

<!-- Award modal -->
<div id="award-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;padding:32px;max-width:440px;width:90%;">
    <div style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:4px;">Award Quote</div>
    <div style="font-size:13px;color:#64748b;margin-bottom:20px;" id="award-supplier-name"></div>
    <form id="award-form" method="POST">
      @csrf
      <label style="display:block;font-size:12px;font-weight:700;color:#64748b;margin-bottom:6px;">Reason for selection</label>
      <textarea name="award_reason" rows="3" required style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;margin-bottom:16px;"></textarea>
      <div style="display:flex;gap:10px;">
        <button type="button" onclick="closeAwardModal()" style="flex:1;padding:10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;background:#f8fafc;cursor:pointer;">Cancel</button>
        <button type="submit" style="flex:2;padding:10px;background:#f59e0b;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">Confirm Award</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAwardModal(quoteId, supplierName) {
  document.getElementById('award-form').action = '/purchase/requests/{{ $request->id }}/quotes/' + quoteId + '/award';
  document.getElementById('award-supplier-name').textContent = 'Awarding to: ' + supplierName;
  document.getElementById('award-modal').style.display = 'flex';
}
function closeAwardModal() {
  document.getElementById('award-modal').style.display = 'none';
}
</script>
@endsection
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Purchase/SupplierQuoteController.php resources/views/purchase/quotes/
git commit -m "feat: supplier quote controller — index, compare, award views"
```

---

## Task 10: Pipeline index view with vertical timeline

**Files:**
- Create: `app/Http/Controllers/Purchase/PurchasePipelineController.php`
- Create: `resources/views/purchase/pipeline/index.blade.php`

- [ ] **Step 1: Write PurchasePipelineController**

```php
<?php
namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Services\PurchaseStageService;

class PurchasePipelineController extends Controller
{
    public function index(PurchaseStageService $stages)
    {
        $requests = PurchaseRequest::with([
            'requestedBy', 'signature', 'rfqInvitations', 'supplierQuotes', 'awardedQuote.supplier',
        ])->orderByRaw("CASE stage
            WHEN 'draft' THEN 0 WHEN 'gm_approval' THEN 1 WHEN 'rfq' THEN 2
            WHEN 'quoting' THEN 3 WHEN 'comparison' THEN 4 WHEN 'lpo' THEN 5
            WHEN 'receiving' THEN 6 WHEN 'payment' THEN 7 WHEN 'complete' THEN 8
            ELSE 9 END")
            ->latest()->get();

        return view('purchase.pipeline.index', compact('requests', 'stages'));
    }
}
```

- [ ] **Step 2: Write the pipeline index view**

```blade
@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto py-8 px-4">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
    <div>
      <h1 style="font-size:22px;font-weight:700;color:#0f172a;">Purchase Pipeline</h1>
      <p style="font-size:13px;color:#64748b;margin-top:2px;">Track every purchase from request to payment</p>
    </div>
    <a href="{{ route('purchase.requests.create') }}"
      style="padding:10px 20px;background:#2563eb;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;">
      + New Request
    </a>
  </div>

  @if($requests->isEmpty())
    <div style="text-align:center;padding:80px 0;color:#94a3b8;">
      <div style="font-size:48px;margin-bottom:12px;">📋</div>
      <div style="font-size:16px;font-weight:600;">No purchases yet</div>
      <div style="font-size:13px;margin-top:4px;">Create a purchase request to get started.</div>
    </div>
  @endif

  <div style="display:flex;flex-direction:column;gap:16px;">
    @foreach($requests as $pr)
    @php
      $stageIdx = $stages->stageIndex($pr->stage);
      $allStages = \App\Services\PurchaseStageService::STAGES;
    @endphp
    <div style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.06);overflow:hidden;">

      <!-- Card header -->
      <div style="padding:20px 24px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
        <div>
          <div style="font-size:15px;font-weight:700;color:#0f172a;">{{ $pr->request_number }}</div>
          <div style="font-size:12px;color:#64748b;margin-top:2px;">{{ $pr->project_name }} · {{ $pr->date->format('d M Y') }}</div>
        </div>
        <div style="font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;
          background:{{ $pr->stage === 'complete' ? '#dcfce7' : '#fffbeb' }};
          color:{{ $pr->stage === 'complete' ? '#15803d' : '#92400e' }};">
          {{ $stages->stageLabel($pr->stage) }}
        </div>
      </div>

      <!-- Mini progress bar -->
      <div style="height:4px;background:#f1f5f9;">
        <div style="height:4px;background:{{ $pr->stage === 'complete' ? '#22c55e' : '#f59e0b' }};
          width:{{ round(($stageIdx / (count($allStages) - 1)) * 100) }}%;
          transition:width .4s ease;"></div>
      </div>

      <!-- Vertical timeline (compact) -->
      <div style="padding:20px 24px;">
        <div style="display:flex;flex-direction:column;gap:0;">

          @foreach($allStages as $i => $stage)
          @php
            $done    = $i < $stageIdx;
            $current = $i === $stageIdx;
            $future  = $i > $stageIdx;
          @endphp
          <div style="display:flex;align-items:stretch;gap:14px;">
            <!-- dot + line -->
            <div style="display:flex;flex-direction:column;align-items:center;width:20px;flex-shrink:0;">
              <div style="width:16px;height:16px;border-radius:50%;flex-shrink:0;margin-top:2px;
                background:{{ $done ? '#2563eb' : ($current ? '#f59e0b' : '#e2e8f0') }};
                {{ $current ? 'box-shadow:0 0 0 3px #fde68a;' : '' }}
                display:flex;align-items:center;justify-content:center;">
                @if($done)
                  <svg width="8" height="8" viewBox="0 0 8 8"><path d="M1 4l2 2 4-4" stroke="#fff" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
                @endif
              </div>
              @if($i < count($allStages) - 1)
              <div style="width:2px;flex:1;min-height:8px;background:{{ $done ? '#2563eb' : '#e2e8f0' }};margin:2px 0;"></div>
              @endif
            </div>

            <!-- content -->
            <div style="padding-bottom:{{ $i < count($allStages) - 1 ? '10' : '0' }}px;flex:1;min-width:0;">
              <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
                <span style="font-size:13px;font-weight:{{ $current ? '700' : '500' }};
                  color:{{ $done ? '#2563eb' : ($current ? '#d97706' : '#94a3b8') }};">
                  {{ $stages->stageLabel($stage) }}
                </span>

                {{-- Action button for current stage --}}
                @if($current)
                  @if($stage === 'gm_approval')
                    <a href="{{ route('purchase.requests.sign', $pr) }}"
                      style="font-size:11px;font-weight:700;padding:3px 10px;background:#7c3aed;color:#fff;border-radius:6px;text-decoration:none;">
                      Sign →
                    </a>
                  @elseif($stage === 'rfq')
                    <a href="{{ route('purchase.requests.rfq', $pr) }}"
                      style="font-size:11px;font-weight:700;padding:3px 10px;background:#0ea5e9;color:#fff;border-radius:6px;text-decoration:none;">
                      Select Suppliers →
                    </a>
                  @elseif($stage === 'quoting')
                    <a href="{{ route('purchase.requests.quotes', $pr) }}"
                      style="font-size:11px;font-weight:700;padding:3px 10px;background:#f59e0b;color:#fff;border-radius:6px;text-decoration:none;">
                      View Quotes ({{ $pr->supplierQuotes->count() }}) →
                    </a>
                  @elseif($stage === 'comparison')
                    <a href="{{ route('purchase.requests.compare', $pr) }}"
                      style="font-size:11px;font-weight:700;padding:3px 10px;background:#f59e0b;color:#fff;border-radius:6px;text-decoration:none;">
                      Compare & Award →
                    </a>
                  @elseif($stage === 'lpo')
                    <a href="{{ route('purchase.orders.create', ['request_id' => $pr->id]) }}"
                      style="font-size:11px;font-weight:700;padding:3px 10px;background:#16a34a;color:#fff;border-radius:6px;text-decoration:none;">
                      Issue LPO →
                    </a>
                  @elseif($stage === 'receiving')
                    <a href="{{ route('purchase.grns.create', ['request_id' => $pr->id]) }}"
                      style="font-size:11px;font-weight:700;padding:3px 10px;background:#16a34a;color:#fff;border-radius:6px;text-decoration:none;">
                      Record GRN →
                    </a>
                  @elseif($stage === 'payment')
                    <a href="{{ route('purchase.payments.create', ['request_id' => $pr->id]) }}"
                      style="font-size:11px;font-weight:700;padding:3px 10px;background:#0f172a;color:#fff;border-radius:6px;text-decoration:none;">
                      Issue Payment →
                    </a>
                  @endif
                @endif
              </div>

              {{-- Stage detail text --}}
              @if($done || $current)
              <div style="font-size:11px;color:#94a3b8;margin-top:1px;">
                @if($stage === 'draft') Created by {{ $pr->requestedBy->name }} · {{ $pr->created_at->format('d M Y') }}
                @elseif($stage === 'gm_approval' && $pr->signature) Signed by {{ $pr->signature->signedBy->name }} · {{ $pr->signature->signed_at->format('d M Y') }}
                @elseif($stage === 'rfq' || $stage === 'quoting') {{ $pr->rfqInvitations->count() }} supplier(s) invited
                @elseif($stage === 'comparison') {{ $pr->supplierQuotes->count() }} quote(s) received
                @elseif($stage === 'lpo' && $pr->awardedQuote) Awarded to {{ $pr->awardedQuote->supplier->name }}
                @endif
              </div>
              @endif
            </div>
          </div>
          @endforeach

        </div>
      </div>
    </div>
    @endforeach
  </div>
</div>
@endsection
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Purchase/PurchasePipelineController.php resources/views/purchase/pipeline/
git commit -m "feat: purchase pipeline index — card list with vertical timeline"
```

---

## Task 11: Route wiring + sidebar navigation link

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Add all new routes to `routes/web.php`**

Add these imports at the top of `web.php` (after existing Purchase imports):

```php
use App\Http\Controllers\Purchase\PurchasePipelineController;
use App\Http\Controllers\Purchase\PurchaseSignatureController;
use App\Http\Controllers\Purchase\RfqController;
use App\Http\Controllers\Purchase\SupplierQuoteController;
use App\Http\Controllers\Purchase\RfqPortalController;
```

Add the public RFQ route **before** the auth middleware group:

```php
// Public — no auth required
Route::get('/rfq/{token}',  [RfqPortalController::class, 'show'])->name('rfq.show');
Route::post('/rfq/{token}', [RfqPortalController::class, 'submit'])->name('rfq.submit');
```

Inside the `purchase.` prefix group, add:

```php
Route::get('pipeline', [PurchasePipelineController::class, 'index'])->name('pipeline.index');
Route::get('requests/{request}/sign', [PurchaseSignatureController::class, 'show'])->name('requests.sign');
Route::post('requests/{request}/sign', [PurchaseSignatureController::class, 'store'])->name('requests.sign.store');
Route::get('requests/{request}/rfq',  [RfqController::class, 'show'])->name('requests.rfq');
Route::post('requests/{request}/rfq', [RfqController::class, 'store'])->name('requests.rfq.store');
Route::get('requests/{request}/quotes', [SupplierQuoteController::class, 'index'])->name('requests.quotes');
Route::get('requests/{request}/compare', [SupplierQuoteController::class, 'compare'])->name('requests.compare');
Route::post('requests/{request}/quotes/{quote}/award', [SupplierQuoteController::class, 'award'])->name('requests.quotes.award');
```

- [ ] **Step 2: Add sidebar link to the layout**

Open `resources/views/layouts/app.blade.php`. Find where the sidebar purchase links are. Add a "Pipeline" link as the first item under Purchase:

```blade
<a href="{{ route('purchase.pipeline.index') }}"
   class="{{ request()->routeIs('purchase.pipeline.*') ? 'active' : '' }}">
  Pipeline
</a>
```

(Match the exact element tag/class used by the rest of the sidebar navigation links in the file.)

- [ ] **Step 3: Verify routes load**

```
php artisan route:list --path=purchase/pipeline
php artisan route:list --path=rfq
```

Expected: `purchase.pipeline.index`, `rfq.show`, `rfq.submit` listed.

- [ ] **Step 4: Commit**

```bash
git add routes/web.php resources/views/layouts/app.blade.php
git commit -m "feat: wire pipeline routes and sidebar navigation"
```

---

## Task 12: Seed existing purchase requests with `draft` stage

Existing `purchase_requests` rows will have a NULL `stage` (migration sets default `draft` for new rows but existing NULL rows won't match the pipeline logic).

**Files:**
- Create: `database/seeders/PurchaseRequestStagePatchSeeder.php`

- [ ] **Step 1: Write the one-time patch seeder**

```php
<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseRequestStagePatchSeeder extends Seeder
{
    public function run(): void
    {
        // Existing approved requests get 'rfq' stage; pending get 'gm_approval'; rest stay 'draft'
        DB::table('purchase_requests')->where('status', 'approved')->whereNull('stage')->update(['stage' => 'rfq']);
        DB::table('purchase_requests')->where('status', 'pending')->whereNull('stage')->update(['stage' => 'gm_approval']);
        DB::table('purchase_requests')->whereNull('stage')->update(['stage' => 'draft']);
    }
}
```

- [ ] **Step 2: Run the seeder**

```
php artisan db:seed --class=PurchaseRequestStagePatchSeeder
```

Expected: No errors; existing rows now have non-null stage values.

- [ ] **Step 3: Commit**

```bash
git add database/seeders/PurchaseRequestStagePatchSeeder.php
git commit -m "feat: patch existing purchase_requests with initial stage values"
```

---

## Task 13: GRN receiving view — toggle Inventory / Consumable per item

**Files:**
- Modify: `resources/views/purchase/grns/create.blade.php`

This is a targeted edit to the existing GRN creation form. Find the item rows section and add a toggle after the quantity/cost fields for each item.

- [ ] **Step 1: Read the existing GRN create view** to understand how item rows are rendered before editing.

```
Read: resources/views/purchase/grns/create.blade.php
```

- [ ] **Step 2: For each item row in the form, add a type toggle**

After the existing quantity / unit cost inputs for each GRN item, add:

```blade
<div style="display:flex;gap:0;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;">
  <label style="flex:1;text-align:center;cursor:pointer;">
    <input type="radio" name="items[{{ $i }}][type]" value="inventory" checked style="display:none;">
    <span class="grn-type-btn" onclick="setGrnType(this,'inventory');"
      style="display:block;padding:6px 8px;font-size:11px;font-weight:700;background:#eff6ff;color:#2563eb;">
      Inventory
    </span>
  </label>
  <label style="flex:1;text-align:center;cursor:pointer;border-left:1px solid #e2e8f0;">
    <input type="radio" name="items[{{ $i }}][type]" value="consumable" style="display:none;">
    <span class="grn-type-btn" onclick="setGrnType(this,'consumable');"
      style="display:block;padding:6px 8px;font-size:11px;font-weight:700;background:#fff;color:#64748b;">
      Consumable
    </span>
  </label>
</div>
```

And a small JS helper (once in the view, not per row):

```javascript
function setGrnType(span, val) {
  const parent = span.closest('label').parentElement;
  parent.querySelectorAll('.grn-type-btn').forEach(b => {
    b.style.background = '#fff'; b.style.color = '#64748b';
  });
  span.style.background = val === 'inventory' ? '#eff6ff' : '#fef3c7';
  span.style.color = val === 'inventory' ? '#2563eb' : '#92400e';
}
```

- [ ] **Step 3: Update GoodsReceiptNoteController to accept and store the `type` field**

In `GoodsReceiptNoteController::store()`, when creating each `GrnItem`, pass `type` from the request:

```php
$item->type = $itemData['type'] ?? 'inventory';
$item->save();
```

(Read the exact controller code first to insert at the right location.)

- [ ] **Step 4: Commit**

```bash
git add resources/views/purchase/grns/create.blade.php app/Http/Controllers/Purchase/GoodsReceiptNoteController.php
git commit -m "feat: GRN item type toggle — inventory or consumable"
```

---

## Self-Review Checklist

**Spec coverage:**
- [x] Stage 1 (Purchase Request) — existing, no new code needed; stage seeded via Task 12
- [x] Stage 2 (GM Signature) — Task 6
- [x] Stage 3 (Select Suppliers + RFQ) — Task 7
- [x] Stage 4 (Supplier Quotes via private link) — Task 8
- [x] Stage 5 (Comparison + Award) — Task 9
- [x] Stage 6 (LPO) — action button links to existing `purchase.orders.create`; no new controller needed
- [x] Stage 7 (Receiving with inventory/consumable) — Task 13
- [x] Stage 8 (Payment) — action button links to existing `purchase.payments.create`; no new controller needed
- [x] Pipeline index view — Task 10
- [x] Stage advancement via `PurchaseStageService` — Task 5
- [x] Token expiry, one-submit-only — `RfqPortalController::show()` checks both
- [x] Awarding is irreversible / locks comparison — `SupplierQuoteController::award()` guard
- [x] WhatsApp deep link — `RfqInvitationService::whatsappLink()`
- [x] Email send — `RfqInvitationMail` + `rfq-invitation.blade.php`
- [x] Public route outside auth — Task 11 adds routes before middleware group
- [x] `grn_items.type` field — Task 2 + Task 13

**Known dependency:** Stage 6 (LPO) and Stage 8 (Payment) action buttons link to existing controllers. Those controllers don't advance the pipeline stage automatically. A follow-up task should add `$stages->setStage($pr, 'receiving')` after LPO creation, and `$stages->setStage($pr, 'complete')` after payment creation. This can be done as a polish pass without blocking the core pipeline.
