@extends('layouts.app')

@section('title', $supplier->name)

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $supplier->name }}</h1>
        <p class="page-subtitle">
            <a href="{{ route('purchase.suppliers.index') }}" style="color:#2563eb;text-decoration:none;">Suppliers</a>
            &nbsp;/&nbsp; View Details
        </p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('purchase.suppliers.edit', $supplier) }}" class="btn btn-secondary">Edit Supplier</a>
        <a href="{{ route('purchase.suppliers.index') }}" class="btn btn-secondary">Back to List</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px;">

    {{-- Identity --}}
    <div style="background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:22px;grid-column:1/-1;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
            <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;">Company</h3>
            @if($supplier->is_active)
                <span class="badge-green">Active</span>
            @else
                <span class="badge-gray">Inactive</span>
            @endif
        </div>
        <div style="font-size:22px;font-weight:700;color:#0f172a;margin-bottom:6px;">{{ $supplier->name }}</div>
        @if($supplier->supplier_code)
            <div style="font-family:monospace;font-size:12px;color:#94a3b8;margin-bottom:4px;">{{ $supplier->supplier_code }}</div>
        @endif
        @if($supplier->category)
            <span style="display:inline-block;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600;background:#f1f5f9;color:#475569;margin-top:4px;">
                {{ $supplier->category }}
            </span>
        @endif
        @if($supplier->website)
            <div style="margin-top:10px;">
                <a href="{{ $supplier->website }}" target="_blank"
                   style="font-size:13px;color:#2563eb;text-decoration:none;">
                    {{ $supplier->website }}
                </a>
            </div>
        @endif
    </div>

    {{-- Contact --}}
    <div style="background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:22px;">
        <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:16px;">Contact</h3>
        @if($supplier->contact_person)
            <div style="margin-bottom:12px;">
                <div style="font-size:11px;color:#94a3b8;margin-bottom:2px;">Contact Person</div>
                <div style="font-size:14px;font-weight:600;color:#1e293b;">{{ $supplier->contact_person }}</div>
            </div>
        @endif
        @if($supplier->email)
            <div style="margin-bottom:10px;">
                <div style="font-size:11px;color:#94a3b8;margin-bottom:2px;">Primary Email</div>
                <a href="mailto:{{ $supplier->email }}" style="font-size:13px;color:#2563eb;text-decoration:none;">{{ $supplier->email }}</a>
            </div>
        @endif
        @if($supplier->secondary_email)
            <div style="margin-bottom:10px;">
                <div style="font-size:11px;color:#94a3b8;margin-bottom:2px;">Secondary Email</div>
                <a href="mailto:{{ $supplier->secondary_email }}" style="font-size:13px;color:#64748b;text-decoration:none;">{{ $supplier->secondary_email }}</a>
            </div>
        @endif
        @if($supplier->phone)
            <div style="margin-bottom:10px;">
                <div style="font-size:11px;color:#94a3b8;margin-bottom:2px;">Phone 1</div>
                <a href="tel:{{ $supplier->phone }}" style="font-size:13px;color:#334155;text-decoration:none;">{{ $supplier->phone }}</a>
            </div>
        @endif
        @if($supplier->phone2)
            <div style="margin-bottom:10px;">
                <div style="font-size:11px;color:#94a3b8;margin-bottom:2px;">Phone 2</div>
                <a href="tel:{{ $supplier->phone2 }}" style="font-size:13px;color:#334155;text-decoration:none;">{{ $supplier->phone2 }}</a>
            </div>
        @endif
        @if($supplier->whatsapp)
            <div>
                <div style="font-size:11px;color:#94a3b8;margin-bottom:2px;">WhatsApp</div>
                <a href="https://wa.me/{{ ltrim($supplier->whatsapp, '+') }}" target="_blank"
                   style="display:inline-flex;align-items:center;gap:5px;font-size:13px;color:#16a34a;text-decoration:none;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="#16a34a">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                    {{ $supplier->whatsapp }}
                </a>
            </div>
        @endif
    </div>

    {{-- Financial --}}
    <div style="background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:22px;">
        <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:16px;">Financial &amp; Address</h3>
        @if($supplier->address)
            <div style="margin-bottom:12px;">
                <div style="font-size:11px;color:#94a3b8;margin-bottom:2px;">Address</div>
                <div style="font-size:13px;color:#334155;line-height:1.5;">{{ $supplier->address }}</div>
            </div>
        @endif
        @if($supplier->tax_number)
            <div style="margin-bottom:12px;">
                <div style="font-size:11px;color:#94a3b8;margin-bottom:2px;">Tax / VAT Number</div>
                <div style="font-family:monospace;font-size:13px;color:#334155;">{{ $supplier->tax_number }}</div>
            </div>
        @endif
        @if($supplier->credit_terms)
            <div style="margin-bottom:12px;">
                <div style="font-size:11px;color:#94a3b8;margin-bottom:2px;">Credit Terms</div>
                <div style="font-size:13px;color:#16a34a;font-weight:600;">
                    {{ strtolower($supplier->credit_terms) === 'y' ? 'Yes' : $supplier->credit_terms }}
                    @if($supplier->credit_days)
                        &nbsp;·&nbsp; {{ $supplier->credit_days }} days
                    @endif
                </div>
            </div>
        @endif
        @if($supplier->remarks)
            <div>
                <div style="font-size:11px;color:#94a3b8;margin-bottom:2px;">Remarks</div>
                <div style="font-size:13px;color:#475569;line-height:1.5;">{{ $supplier->remarks }}</div>
            </div>
        @endif
    </div>

</div>

@endsection
