@extends('layouts.app')

@section('title', 'Suppliers')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────────────────── --}}
<div class="page-header">
    <div>
        <h1 class="page-title">Suppliers</h1>
        <p class="page-subtitle">Manage your supplier directory</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="{{ route('purchase.suppliers.export-pdf') }}" class="btn btn-secondary" title="Export as PDF">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6M9 17h4"/>
            </svg>
            Export PDF
        </a>
        <a href="{{ route('purchase.suppliers.template') }}" class="btn btn-secondary" title="Download import template">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Template
        </a>
        <button onclick="document.getElementById('import-modal').classList.remove('hidden')" class="btn btn-success">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            Import Excel
        </button>
        <button onclick="openNewSupplierModal()" class="btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
            </svg>
            Add Supplier
        </button>
    </div>
</div>

{{-- ── Stat Cards ───────────────────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">

    <div style="background:#fff;border-radius:12px;padding:18px 20px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,.04);display:flex;align-items:center;gap:14px;">
        <div style="width:44px;height:44px;border-radius:10px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="20" height="20" fill="none" stroke="#2563eb" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2h5M12 12a4 4 0 100-8 4 4 0 000 8z"/>
            </svg>
        </div>
        <div>
            <div style="font-size:26px;font-weight:700;color:#0f172a;line-height:1;" id="stat-total">{{ $stats['total'] }}</div>
            <div style="font-size:12px;color:#64748b;margin-top:2px;">Total Suppliers</div>
        </div>
    </div>

    <div style="background:#fff;border-radius:12px;padding:18px 20px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,.04);display:flex;align-items:center;gap:14px;">
        <div style="width:44px;height:44px;border-radius:10px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="20" height="20" fill="none" stroke="#16a34a" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <div>
            <div style="font-size:26px;font-weight:700;color:#16a34a;line-height:1;">{{ $stats['active'] }}</div>
            <div style="font-size:12px;color:#64748b;margin-top:2px;">Active</div>
        </div>
    </div>

    <div style="background:#fff;border-radius:12px;padding:18px 20px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,.04);display:flex;align-items:center;gap:14px;">
        <div style="width:44px;height:44px;border-radius:10px;background:#fef2f2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="20" height="20" fill="none" stroke="#dc2626" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </div>
        <div>
            <div style="font-size:26px;font-weight:700;color:#dc2626;line-height:1;">{{ $stats['inactive'] }}</div>
            <div style="font-size:12px;color:#64748b;margin-top:2px;">Inactive</div>
        </div>
    </div>

    <div style="background:#fff;border-radius:12px;padding:18px 20px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,.04);display:flex;align-items:center;gap:14px;">
        <div style="width:44px;height:44px;border-radius:10px;background:#fefce8;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="20" height="20" fill="none" stroke="#ca8a04" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>
        <div>
            <div style="font-size:26px;font-weight:700;color:#ca8a04;line-height:1;">{{ $stats['categories'] }}</div>
            <div style="font-size:12px;color:#64748b;margin-top:2px;">Categories</div>
        </div>
    </div>

</div>

{{-- ── Search + Count bar ───────────────────────────────────────────────────── --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;gap:12px;">
    <div style="position:relative;flex:0 0 auto;">
        <svg style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;"
             width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
        </svg>
        <input id="supplier-search"
               type="text"
               placeholder="Search name, contact, email, phone, address…"
               autocomplete="off"
               style="padding:8px 14px 8px 34px;border:1px solid #e2e8f0;border-radius:8px;font-size:13.5px;width:340px;outline:none;transition:border-color .15s,box-shadow .15s;">
    </div>
    <div id="search-count" style="font-size:12.5px;color:#94a3b8;white-space:nowrap;">
        {{ $stats['total'] }} suppliers
    </div>
</div>

{{-- ── Table ────────────────────────────────────────────────────────────────── --}}
<div style="width:100%;overflow-x:auto;border-radius:12px;border:1px solid #e2e8f0;">
    <table id="suppliers-table" style="width:100%;border-collapse:collapse;font-size:12.5px;white-space:nowrap;">
        <thead>
            <tr style="background:#1e293b;">
                <th style="padding:10px 12px;text-align:left;font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#94a3b8;white-space:nowrap;">Code</th>
                <th style="padding:10px 12px;text-align:left;font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#94a3b8;min-width:200px;white-space:nowrap;">Company</th>
                <th style="padding:10px 12px;text-align:left;font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#94a3b8;white-space:nowrap;">Category</th>
                <th style="padding:10px 12px;text-align:left;font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#94a3b8;min-width:160px;white-space:nowrap;">Contact &amp; Email</th>
                <th style="padding:10px 12px;text-align:left;font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#94a3b8;min-width:140px;white-space:nowrap;">Phone / WhatsApp</th>
                <th style="padding:10px 12px;text-align:left;font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#94a3b8;min-width:140px;white-space:nowrap;">Address</th>
                <th style="padding:10px 12px;text-align:left;font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#94a3b8;white-space:nowrap;">Tax / Credit</th>
                <th style="padding:10px 12px;text-align:left;font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#94a3b8;white-space:nowrap;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($suppliers as $i => $supplier)
            <tr class="supplier-row"
                data-supplier='@json($supplier)'
                data-update="{{ route('purchase.suppliers.update', $supplier) }}"
                data-delete="{{ route('purchase.suppliers.destroy', $supplier) }}"
                style="border-bottom:1px solid #f1f5f9;cursor:pointer;{{ $i % 2 === 0 ? '' : 'background:#fafafa;' }}"
                onmouseover="this.style.background='#f0f9ff'"
                onmouseout="this.style.background='{{ $i % 2 === 0 ? '' : '#fafafa' }}'">

                {{-- Code --}}
                <td style="padding:10px 12px;font-family:monospace;font-size:11px;color:#94a3b8;vertical-align:top;">
                    {{ $supplier->supplier_code ?: '—' }}
                </td>

                {{-- Company + website --}}
                <td style="padding:10px 12px;vertical-align:top;max-width:220px;white-space:normal;">
                    <div style="font-weight:600;color:#0f172a;font-size:13px;line-height:1.3;">{{ $supplier->name }}</div>
                    @if($supplier->website)
                        <a href="{{ $supplier->website }}" target="_blank"
                           style="font-size:11px;color:#2563eb;text-decoration:none;display:block;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px;"
                           title="{{ $supplier->website }}">
                            {{ parse_url($supplier->website, PHP_URL_HOST) ?: $supplier->website }}
                        </a>
                    @endif
                </td>

                {{-- Category --}}
                <td style="padding:10px 12px;vertical-align:top;">
                    @if($supplier->category)
                        <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#f1f5f9;color:#475569;white-space:nowrap;">
                            {{ $supplier->category }}
                        </span>
                    @else
                        <span style="color:#cbd5e1;">—</span>
                    @endif
                </td>

                {{-- Contact person + emails stacked --}}
                <td style="padding:10px 12px;vertical-align:top;max-width:200px;white-space:normal;">
                    @if($supplier->contact_person)
                        <div style="font-weight:500;color:#334155;font-size:12.5px;">{{ $supplier->contact_person }}</div>
                    @endif
                    @if($supplier->email)
                        <a href="mailto:{{ $supplier->email }}"
                           style="display:block;color:#2563eb;text-decoration:none;font-size:12px;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:190px;"
                           title="{{ $supplier->email }}">{{ $supplier->email }}</a>
                    @endif
                    @if($supplier->secondary_email)
                        <a href="mailto:{{ $supplier->secondary_email }}"
                           style="display:block;color:#94a3b8;text-decoration:none;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:190px;"
                           title="{{ $supplier->secondary_email }}">{{ $supplier->secondary_email }}</a>
                    @endif
                    @if(!$supplier->contact_person && !$supplier->email)
                        <span style="color:#cbd5e1;">—</span>
                    @endif
                </td>

                {{-- Phones stacked --}}
                <td style="padding:10px 12px;vertical-align:top;white-space:nowrap;">
                    @if($supplier->phone)
                        <a href="tel:{{ $supplier->phone }}" style="display:block;color:#334155;text-decoration:none;font-size:12.5px;">
                            {{ $supplier->phone }}
                        </a>
                    @endif
                    @if($supplier->phone2)
                        <a href="tel:{{ $supplier->phone2 }}" style="display:block;color:#64748b;text-decoration:none;font-size:12px;margin-top:2px;">
                            {{ $supplier->phone2 }}
                        </a>
                    @endif
                    @if($supplier->whatsapp)
                        <a href="https://wa.me/{{ ltrim($supplier->whatsapp, '+') }}" target="_blank"
                           style="display:inline-flex;align-items:center;gap:3px;color:#16a34a;text-decoration:none;font-size:12px;margin-top:2px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="#16a34a" style="flex-shrink:0;">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                            {{ $supplier->whatsapp }}
                        </a>
                    @endif
                    @if(!$supplier->phone && !$supplier->phone2 && !$supplier->whatsapp)
                        <span style="color:#cbd5e1;">—</span>
                    @endif
                </td>

                {{-- Address --}}
                <td style="padding:10px 12px;vertical-align:top;color:#475569;max-width:160px;white-space:normal;font-size:12px;line-height:1.4;">
                    {{ $supplier->address ?: '—' }}
                </td>

                {{-- Tax + Credit stacked --}}
                <td style="padding:10px 12px;vertical-align:top;white-space:nowrap;">
                    @if($supplier->tax_number)
                        <div style="font-family:monospace;font-size:11px;color:#64748b;">{{ $supplier->tax_number }}</div>
                    @endif
                    @if(in_array(strtolower($supplier->credit_terms ?? ''), ['y','yes']))
                        <div style="font-size:11px;color:#16a34a;font-weight:600;margin-top:2px;">
                            Credit{{ $supplier->credit_days ? ' · '.$supplier->credit_days.'d' : '' }}
                        </div>
                    @elseif(!$supplier->tax_number)
                        <span style="color:#cbd5e1;">—</span>
                    @endif
                </td>

                {{-- Status --}}
                <td style="padding:10px 12px;vertical-align:top;">
                    @if($supplier->is_active)
                        <span class="badge-green">Active</span>
                    @else
                        <span class="badge-gray">Inactive</span>
                    @endif
                </td>

            </tr>
            @empty
            <tr>
                <td colspan="8" style="padding:40px;text-align:center;color:#94a3b8;">No suppliers found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- No-results message (shown by JS) --}}
<div id="no-results" style="display:none;text-align:center;padding:40px 20px;color:#94a3b8;font-size:14px;">
    No suppliers match your search.
</div>

{{-- ═══════════ Row Context Dropdown ═══════════ --}}
<div id="row-menu" style="display:none;position:fixed;z-index:200;background:#fff;border-radius:12px;
     box-shadow:0 8px 30px rgba(0,0,0,.15),0 2px 8px rgba(0,0,0,.08);
     border:1px solid #e2e8f0;min-width:190px;padding:6px;overflow:hidden;">

    <div id="rm-supplier-label" style="padding:8px 12px 6px;font-size:11px;font-weight:700;
         text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;border-bottom:1px solid #f1f5f9;margin-bottom:4px;
         white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:178px;"></div>

    <a id="rm-email" href="#" target="_blank"
       style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;
              text-decoration:none;color:#334155;font-size:13px;font-weight:500;">
        <span style="width:28px;height:28px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="13" height="13" fill="none" stroke="#2563eb" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </span>Send Email
    </a>

    <a id="rm-whatsapp" href="#" target="_blank"
       style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;
              text-decoration:none;color:#334155;font-size:13px;font-weight:500;">
        <span style="width:28px;height:28px;border-radius:8px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="#16a34a">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
            </svg>
        </span>Send WhatsApp
    </a>

    <div style="height:1px;background:#f1f5f9;margin:4px 0;"></div>

    <button id="rm-view" type="button"
       style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;width:100%;
              background:none;border:none;cursor:pointer;color:#334155;font-size:13px;font-weight:500;text-align:left;">
        <span style="width:28px;height:28px;border-radius:8px;background:#f8fafc;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="13" height="13" fill="none" stroke="#475569" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
        </span>View / Edit
    </button>

    <div style="height:1px;background:#f1f5f9;margin:4px 0;"></div>

    <button id="rm-delete" type="button"
       style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;width:100%;
              background:none;border:none;cursor:pointer;color:#dc2626;font-size:13px;font-weight:500;text-align:left;">
        <span style="width:28px;height:28px;border-radius:8px;background:#fef2f2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="13" height="13" fill="none" stroke="#dc2626" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V4a1 1 0 011-1h6a1 1 0 011 1v3"/>
            </svg>
        </span>Delete Supplier
    </button>
</div>

{{-- ═══════════ Supplier View / Edit Modal ═══════════ --}}
<datalist id="sm-category-list">
    @foreach($categories as $cat)
        <option value="{{ $cat }}">
    @endforeach
</datalist>

<div id="supplier-modal" style="display:none;position:fixed;inset:0;z-index:250;
     background:rgba(15,23,42,.6);backdrop-filter:blur(6px);
     align-items:center;justify-content:center;padding:16px;">

    <div style="background:#fff;border-radius:20px;width:100%;max-width:640px;
                overflow:hidden;display:flex;flex-direction:column;
                box-shadow:0 32px 80px rgba(0,0,0,.28);
                animation:modalIn .24s cubic-bezier(.34,1.56,.64,1);">

        {{-- ── Gradient top stripe ── --}}
        <div id="sm-stripe" style="height:3px;background:linear-gradient(90deg,#2563eb 0%,#7c3aed 50%,#0ea5e9 100%);transition:background .3s;"></div>

        {{-- ── Header ── --}}
        <div style="padding:14px 18px 12px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #f1f5f9;">
            {{-- Avatar circle with supplier initial --}}
            <div id="sm-avatar" style="width:42px;height:42px;border-radius:12px;flex-shrink:0;
                 display:flex;align-items:center;justify-content:center;
                 font-size:18px;font-weight:800;color:#fff;background:linear-gradient(135deg,#2563eb,#7c3aed);
                 box-shadow:0 4px 10px rgba(37,99,235,.3);">A</div>
            <div style="flex:1;min-width:0;">
                <div id="sm-hd-name" style="font-size:15px;font-weight:700;color:#0f172a;
                     white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:380px;"></div>
                <div style="display:flex;align-items:center;gap:6px;margin-top:2px;">
                    <span id="sm-hd-code" style="font-size:11px;font-family:monospace;color:#94a3b8;"></span>
                    <span id="sm-hd-cat"  style="font-size:11px;color:#7c3aed;font-weight:600;"></span>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                <span id="sm-mode-badge"
                      style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
                             padding:3px 9px;border-radius:20px;background:#f1f5f9;color:#64748b;">View</span>
                <button onclick="closeSupplierModal()"
                        style="width:28px;height:28px;border-radius:8px;background:#f1f5f9;border:none;
                               cursor:pointer;display:flex;align-items:center;justify-content:center;"
                        onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                    <svg width="12" height="12" fill="none" stroke="#64748b" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- ── Form body (scrollable) ── --}}
        <form id="supplier-form" method="POST">
            @csrf
            <input type="hidden" name="_method" id="sm-method-field" value="PUT">

            <div style="padding:14px 18px;overflow-y:auto;max-height:calc(100vh - 220px);">

                {{-- ·· Row 1: Name + Status toggle ·· --}}
                <div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end;margin-bottom:10px;">
                    <div>
                        <label class="sm-lbl">Company Name <span style="color:#ef4444;">*</span></label>
                        <input id="sm-name" name="name" type="text" required class="sm-inp"
                               style="font-size:14px;font-weight:600;">
                    </div>
                    <div>
                        <label class="sm-lbl">Status</label>
                        <div id="sm-status-wrap" style="display:flex;border-radius:9px;overflow:hidden;border:1.5px solid #e2e8f0;">
                            <button id="sm-s-active" type="button" onclick="setSmStatus(1)"
                                    style="padding:7px 14px;border:none;cursor:pointer;font-size:12px;font-weight:600;
                                           display:flex;align-items:center;gap:5px;white-space:nowrap;transition:background .15s,color .15s;">
                                <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>Active
                            </button>
                            <button id="sm-s-inactive" type="button" onclick="setSmStatus(0)"
                                    style="padding:7px 14px;border:none;border-left:1px solid #e2e8f0;cursor:pointer;
                                           font-size:12px;font-weight:600;display:flex;align-items:center;gap:5px;white-space:nowrap;transition:background .15s,color .15s;">
                                <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>Inactive
                            </button>
                        </div>
                        <input type="hidden" id="sm-active" name="is_active" value="1">
                    </div>
                </div>

                {{-- ·· Row 2: Code + Category (datalist) + Website ·· --}}
                <div style="display:grid;grid-template-columns:130px 1fr 1fr;gap:10px;margin-bottom:10px;">
                    <div>
                        <label class="sm-lbl">Code</label>
                        <input id="sm-code" name="supplier_code" type="text" class="sm-inp"
                               style="font-family:monospace;font-size:12px;">
                    </div>
                    <div>
                        <label class="sm-lbl">Category</label>
                        <input id="sm-category" name="category" type="text" list="sm-category-list"
                               class="sm-inp" placeholder="Select or type…">
                    </div>
                    <div>
                        <label class="sm-lbl">Website</label>
                        <input id="sm-website" name="website" type="text" class="sm-inp" placeholder="https://…">
                    </div>
                </div>

                {{-- ·· Divider ·· --}}
                <div style="display:flex;align-items:center;gap:8px;margin:12px 0 10px;">
                    <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#cbd5e1;white-space:nowrap;">Contact</span>
                    <div style="flex:1;height:1px;background:#f1f5f9;"></div>
                </div>

                {{-- ·· Row 3: Contact + Email + Email2 ·· --}}
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:10px;">
                    <div>
                        <label class="sm-lbl">Contact Person</label>
                        <input id="sm-contact" name="contact_person" type="text" class="sm-inp">
                    </div>
                    <div>
                        <label class="sm-lbl">Primary Email</label>
                        <input id="sm-email" name="email" type="email" class="sm-inp">
                    </div>
                    <div>
                        <label class="sm-lbl">Secondary Email</label>
                        <input id="sm-email2" name="secondary_email" type="email" class="sm-inp">
                    </div>
                </div>

                {{-- ·· Row 4: Phone + Phone2 + WhatsApp ·· --}}
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:4px;">
                    <div>
                        <label class="sm-lbl">Phone 1</label>
                        <input id="sm-phone" name="phone" type="text" class="sm-inp" style="font-family:monospace;font-size:12px;">
                    </div>
                    <div>
                        <label class="sm-lbl">Phone 2</label>
                        <input id="sm-phone2" name="phone2" type="text" class="sm-inp" style="font-family:monospace;font-size:12px;">
                    </div>
                    <div>
                        <label class="sm-lbl">WhatsApp</label>
                        <input id="sm-whatsapp" name="whatsapp" type="text" class="sm-inp" style="font-family:monospace;font-size:12px;">
                    </div>
                </div>

                {{-- ·· Divider ·· --}}
                <div style="display:flex;align-items:center;gap:8px;margin:12px 0 10px;">
                    <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#cbd5e1;white-space:nowrap;">Financial</span>
                    <div style="flex:1;height:1px;background:#f1f5f9;"></div>
                </div>

                {{-- ·· Row 5: Address + Tax ·· --}}
                <div style="display:grid;grid-template-columns:1fr 160px;gap:10px;margin-bottom:10px;">
                    <div>
                        <label class="sm-lbl">Address</label>
                        <input id="sm-address" name="address" type="text" class="sm-inp">
                    </div>
                    <div>
                        <label class="sm-lbl">Tax / VAT Number</label>
                        <input id="sm-tax" name="tax_number" type="text" class="sm-inp"
                               style="font-family:monospace;font-size:12px;">
                    </div>
                </div>

                {{-- ·· Row 6: Credit toggle + Days + Remarks ·· --}}
                <div style="display:grid;grid-template-columns:auto 90px 1fr;gap:10px;align-items:start;">
                    <div>
                        <label class="sm-lbl">Credit</label>
                        <div id="sm-credit-wrap" style="display:flex;border-radius:9px;overflow:hidden;border:1.5px solid #e2e8f0;">
                            <button id="sm-c-yes" type="button" onclick="setSmCredit('Y')"
                                    style="padding:7px 12px;border:none;cursor:pointer;font-size:12px;font-weight:600;
                                           white-space:nowrap;transition:background .15s,color .15s;">Yes</button>
                            <button id="sm-c-no" type="button" onclick="setSmCredit('N')"
                                    style="padding:7px 12px;border:none;border-left:1px solid #e2e8f0;cursor:pointer;
                                           font-size:12px;font-weight:600;white-space:nowrap;transition:background .15s,color .15s;">No</button>
                        </div>
                        <input type="hidden" id="sm-credit" name="credit_terms" value="">
                    </div>
                    <div>
                        <label class="sm-lbl">Days</label>
                        <input id="sm-days" name="credit_days" type="number" min="0" class="sm-inp" placeholder="—">
                    </div>
                    <div>
                        <label class="sm-lbl">Remarks</label>
                        <input id="sm-remarks" name="remarks" type="text" class="sm-inp">
                    </div>
                </div>

            </div>{{-- end scrollable --}}

            {{-- ── Footer ── --}}
            <div style="padding:12px 18px 14px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
                <button type="button" onclick="closeSupplierModal()"
                        style="padding:8px 16px;border-radius:9px;border:1.5px solid #e2e8f0;background:#fff;
                               font-size:12.5px;font-weight:600;color:#64748b;cursor:pointer;">
                    Close
                </button>
                <div style="display:flex;gap:8px;">
                    <button id="sm-edit-btn" type="button" onclick="enableSupplierEdit()"
                            style="display:flex;align-items:center;gap:5px;padding:8px 16px;border-radius:9px;
                                   border:1.5px solid #fbbf24;background:#fffbeb;
                                   font-size:12.5px;font-weight:600;color:#92400e;cursor:pointer;">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>Edit
                    </button>
                    <button id="sm-save-btn" type="submit"
                            style="display:none;align-items:center;gap:5px;padding:8px 18px;border-radius:9px;
                                   border:none;background:linear-gradient(135deg,#2563eb,#1d4ed8);
                                   font-size:12.5px;font-weight:600;color:#fff;cursor:pointer;
                                   box-shadow:0 3px 10px rgba(37,99,235,.35);">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg><span id="sm-save-label">Save Changes</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════ Delete Confirmation Modal ═══════════ --}}
<div id="delete-modal" style="display:none;position:fixed;inset:0;z-index:300;
     background:rgba(15,23,42,.7);backdrop-filter:blur(6px);
     display:none;align-items:center;justify-content:center;padding:20px;">

    <div style="background:#fff;border-radius:20px;width:100%;max-width:420px;overflow:hidden;
                box-shadow:0 30px 80px rgba(0,0,0,.3);
                animation:modalIn .25s cubic-bezier(.34,1.56,.64,1);">

        {{-- Red danger header --}}
        <div style="background:linear-gradient(135deg,#991b1b 0%,#dc2626 60%,#ef4444 100%);padding:24px 24px 20px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:44px;height:44px;border-radius:14px;background:rgba(255,255,255,.15);
                            display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg width="22" height="22" fill="none" stroke="#fff" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 style="color:#fff;font-size:16px;font-weight:700;line-height:1.2;">Permanent Deletion</h2>
                    <p style="color:rgba(255,255,255,.65);font-size:12px;margin-top:2px;">This action cannot be undone</p>
                </div>
            </div>
        </div>

        <div style="padding:22px 24px;">
            <p style="font-size:14px;color:#334155;line-height:1.6;margin-bottom:6px;">
                You are about to permanently delete:
            </p>
            <div id="del-supplier-name"
                 style="font-size:14px;font-weight:700;color:#0f172a;background:#fef2f2;
                        border:1px solid #fecaca;border-radius:8px;padding:10px 14px;
                        margin-bottom:18px;word-break:break-word;"></div>

            <p style="font-size:13px;color:#64748b;margin-bottom:6px;line-height:1.5;">
                All purchase orders, invoices, and payment records linked to this supplier may be affected.
                To confirm, type the supplier name exactly as shown above:
            </p>

            <input id="del-confirm-input" type="text" autocomplete="off" spellcheck="false"
                   placeholder="Type supplier name to confirm…"
                   style="width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:10px;
                          font-size:13px;outline:none;transition:border-color .15s,box-shadow .15s;box-sizing:border-box;">

            <div id="del-match-hint" style="font-size:11.5px;margin-top:6px;height:16px;color:#94a3b8;"></div>
        </div>

        <div style="display:flex;gap:10px;padding:0 24px 22px;">
            <button type="button" onclick="closeDeleteModal()"
                    style="flex:1;padding:11px;border-radius:10px;border:1.5px solid #e2e8f0;
                           background:#fff;font-size:13.5px;font-weight:600;color:#475569;cursor:pointer;">
                Cancel — Keep Supplier
            </button>
            <form id="delete-form" method="POST" style="flex:1;">
                @csrf
                @method('DELETE')
                <button id="del-confirm-btn" type="submit" disabled
                        style="width:100%;padding:11px;border-radius:10px;border:none;
                               background:#e2e8f0;font-size:13.5px;font-weight:600;
                               color:#94a3b8;cursor:not-allowed;transition:background .2s,color .2s;">
                    Yes, Delete Permanently
                </button>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════ Import Modal ═══════════ --}}
<div id="import-modal"
     class="hidden fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4"
     style="background:rgba(15,23,42,.6); backdrop-filter:blur(4px);">

    <div style="background:#fff;border-radius:18px;box-shadow:0 25px 60px rgba(0,0,0,.25);
                width:100%;max-width:480px;overflow:hidden;
                animation:modalIn .22s cubic-bezier(.34,1.56,.64,1);">

        <div style="background:linear-gradient(135deg,#1d4ed8 0%,#2563eb 60%,#3b82f6 100%); padding:22px 24px 20px;">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <div style="width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="20" height="20" fill="none" stroke="#fff" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 17v-2m3 2v-4m3 4v-6M3 21h18M3 10l9-7 9 7M5 21V10"/>
                        </svg>
                    </div>
                    <div>
                        <h2 style="color:#fff;font-size:15px;font-weight:700;line-height:1.2;">Import Suppliers</h2>
                        <p style="color:rgba(255,255,255,.65);font-size:12px;margin-top:2px;">Upload an Excel file to add suppliers in bulk</p>
                    </div>
                </div>
                <button onclick="closeImportModal()"
                        style="width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,.12);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;"
                        onmouseover="this.style.background='rgba(255,255,255,.22)'"
                        onmouseout="this.style.background='rgba(255,255,255,.12)'">
                    <svg width="14" height="14" fill="none" stroke="#fff" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <form action="{{ route('purchase.suppliers.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="padding:20px 24px 0;">
                <label for="import-file" id="drop-zone"
                       ondragover="event.preventDefault(); dzActivate()"
                       ondragleave="dzDeactivate()"
                       ondrop="handleDrop(event)"
                       style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;
                              padding:28px 20px;border-radius:14px;cursor:pointer;
                              border:2px dashed #cbd5e1;background:#f8fafc;transition:border-color .15s,background .15s;">
                    <div id="dz-icon" style="width:48px;height:48px;border-radius:12px;background:#dcfce7;display:flex;align-items:center;justify-content:center;transition:transform .2s;">
                        <svg width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <rect x="3" y="3" width="18" height="18" rx="3" fill="#16a34a"/>
                            <path d="M8 8l2.5 4L8 16h1.8l1.7-2.8L13.2 16H15l-2.5-4 2.5-4h-1.8l-1.7 2.8L9.8 8H8z" fill="#fff"/>
                        </svg>
                    </div>
                    <div id="dz-idle" style="text-align:center;">
                        <p style="font-size:13.5px;font-weight:600;color:#334155;">Drop your Excel file here</p>
                        <p style="font-size:12px;color:#94a3b8;margin-top:3px;">or <span style="color:#2563eb;font-weight:600;">browse files</span></p>
                        <p style="font-size:11px;color:#cbd5e1;margin-top:5px;">.xlsx &nbsp;·&nbsp; .xls &nbsp;·&nbsp; max 10 MB</p>
                    </div>
                    <div id="dz-selected" style="display:none;text-align:center;">
                        <p id="dz-filename" style="font-size:13px;font-weight:700;color:#1e293b;word-break:break-all;"></p>
                        <p style="font-size:11px;color:#64748b;margin-top:4px;">
                            <span id="dz-filesize"></span> &nbsp;·&nbsp; <span style="color:#2563eb;font-weight:500;">Change file</span>
                        </p>
                    </div>
                    <input id="import-file" type="file" name="file" accept=".xlsx,.xls" class="sr-only" onchange="handleFileSelect(this)">
                </label>
            </div>

            <div style="margin:14px 24px 0;padding:10px 14px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;">
                <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:8px;">Accepted formats</p>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;background:#eff6ff;border:1px solid #bfdbfe;font-size:11px;font-weight:600;color:#1d4ed8;">
                        <span style="width:5px;height:5px;border-radius:50%;background:#2563eb;display:inline-block;"></span>MRF Comparison
                    </span>
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;background:#f0fdf4;border:1px solid #bbf7d0;font-size:11px;font-weight:600;color:#15803d;">
                        <span style="width:5px;height:5px;border-radius:50%;background:#16a34a;display:inline-block;"></span>Standard Template
                    </span>
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;background:#fefce8;border:1px solid #fde68a;font-size:11px;font-weight:600;color:#92400e;">
                        <span style="width:5px;height:5px;border-radius:50%;background:#ca8a04;display:inline-block;"></span>Duplicates skipped
                    </span>
                </div>
            </div>

            <div style="height:20px;"></div>

            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 24px 20px;border-top:1px solid #f1f5f9;">
                <a href="{{ route('purchase.suppliers.template') }}"
                   style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:#64748b;text-decoration:none;font-weight:500;"
                   onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#64748b'">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download template
                </a>
                <div style="display:flex;gap:8px;">
                    <button type="button" onclick="closeImportModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" id="import-submit-btn" class="btn-primary" disabled
                            style="opacity:.45;cursor:not-allowed;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        Import Suppliers
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes modalIn {
    from { opacity:0; transform:scale(.94) translateY(10px); }
    to   { opacity:1; transform:scale(1)   translateY(0); }
}
@keyframes menuIn {
    from { opacity:0; transform:scale(.95) translateY(-6px); }
    to   { opacity:1; transform:scale(1)   translateY(0); }
}
#drop-zone:hover  { border-color:#93c5fd !important; background:#eff6ff !important; }
#drop-zone.dz-active  { border-color:#2563eb !important; background:#eff6ff !important; }
#drop-zone.dz-active #dz-icon { transform:scale(1.1); }
#drop-zone.dz-has-file { border-color:#4ade80 !important; background:#f0fdf4 !important; }
#supplier-search:focus { border-color:#2563eb !important; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.supplier-row.hidden-by-search { display:none; }
#row-menu a:hover, #row-menu button:hover { background:#f8fafc !important; }
#row-menu #rm-delete:hover { background:#fef2f2 !important; }
#delete-modal.open   { display:flex !important; }
#supplier-modal.open { display:flex !important; }

/* Shared label / input styles used in the supplier modal */
.sm-lbl {
    display:block;font-size:10.5px;font-weight:600;
    color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;
}
.sm-inp {
    width:100%;padding:7px 11px;border:1.5px solid #e2e8f0;border-radius:8px;
    font-size:13px;outline:none;box-sizing:border-box;transition:border-color .15s,background .15s,color .15s;
}
/* View mode */
#supplier-form.view .sm-inp {
    background:#f8fafc;color:#334155;border-color:#f1f5f9;cursor:default;
}
/* Edit mode */
#supplier-form.edit .sm-inp:focus {
    border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1);
}
</style>

<script>
// ══════════════════════════════════════════════════════════
// Import modal
// ══════════════════════════════════════════════════════════
function closeImportModal() { document.getElementById('import-modal').classList.add('hidden'); }
function dzActivate()   { document.getElementById('drop-zone').classList.add('dz-active'); }
function dzDeactivate() { document.getElementById('drop-zone').classList.remove('dz-active'); }
function formatBytes(b) {
    return b < 1024 ? b+' B' : b < 1048576 ? (b/1024).toFixed(1)+' KB' : (b/1048576).toFixed(1)+' MB';
}
function applyFile(file) {
    var zone = document.getElementById('drop-zone');
    zone.classList.remove('dz-active'); zone.classList.add('dz-has-file');
    document.getElementById('dz-idle').style.display = 'none';
    document.getElementById('dz-selected').style.display = 'block';
    document.getElementById('dz-filename').textContent = file.name;
    document.getElementById('dz-filesize').textContent = formatBytes(file.size);
    var btn = document.getElementById('import-submit-btn');
    btn.disabled = false; btn.style.opacity = '1'; btn.style.cursor = 'pointer';
}
function handleFileSelect(input) { if (input.files.length) applyFile(input.files[0]); }
function handleDrop(e) {
    e.preventDefault(); dzDeactivate();
    var file = e.dataTransfer.files[0]; if (!file) return;
    var input = document.getElementById('import-file');
    var dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;
    applyFile(file);
}
document.getElementById('import-modal').addEventListener('click', function(e) {
    if (e.target === this) closeImportModal();
});

// ══════════════════════════════════════════════════════════
// Supplier view / edit / create modal
// ══════════════════════════════════════════════════════════
var _smIsEdit    = false;
var _smStoreUrl  = '{{ route("purchase.suppliers.store") }}';

// Colour palette for avatar (cycles by first char code)
var _avatarColors = [
    'linear-gradient(135deg,#2563eb,#7c3aed)',
    'linear-gradient(135deg,#0ea5e9,#2563eb)',
    'linear-gradient(135deg,#7c3aed,#db2777)',
    'linear-gradient(135deg,#16a34a,#0ea5e9)',
    'linear-gradient(135deg,#ea580c,#dc2626)',
    'linear-gradient(135deg,#0891b2,#7c3aed)',
];
function _avatarGrad(name) {
    return _avatarColors[(name.charCodeAt(0) || 0) % _avatarColors.length];
}

function _smSetToggle(activeId, inactiveId, activeStyles, inactiveStyles, selectedId) {
    var a = document.getElementById(activeId), b = document.getElementById(inactiveId);
    var sel = selectedId === activeId ? a : b;
    var unsel = sel === a ? b : a;
    Object.assign(sel.style,   activeStyles);
    Object.assign(unsel.style, inactiveStyles);
}

function setSmStatus(val) {
    if (!_smIsEdit) return;
    document.getElementById('sm-active').value = val;
    var on  = { background:'#dcfce7', color:'#15803d' };
    var off = { background:'#f8fafc', color:'#94a3b8' };
    if (val == 1) {
        Object.assign(document.getElementById('sm-s-active').style,   on);
        Object.assign(document.getElementById('sm-s-inactive').style, off);
    } else {
        Object.assign(document.getElementById('sm-s-active').style,   off);
        Object.assign(document.getElementById('sm-s-inactive').style, { background:'#fef2f2', color:'#dc2626' });
    }
}

function _renderStatus(val) {
    // Force-render status toggle without _smIsEdit guard
    var was = _smIsEdit; _smIsEdit = true; setSmStatus(val); _smIsEdit = was;
}

function setSmCredit(val) {
    if (!_smIsEdit) return;
    document.getElementById('sm-credit').value = val;
    var yes = document.getElementById('sm-c-yes'), no = document.getElementById('sm-c-no');
    var neutral = { background:'#f8fafc', color:'#94a3b8' };
    if (val === 'Y') {
        Object.assign(yes.style, { background:'#dcfce7', color:'#15803d' });
        Object.assign(no.style,  neutral);
    } else if (val === 'N') {
        Object.assign(no.style,  { background:'#fef2f2', color:'#dc2626' });
        Object.assign(yes.style, neutral);
    } else {
        Object.assign(yes.style, neutral); Object.assign(no.style, neutral);
    }
}

function _renderCredit(val) {
    var was = _smIsEdit; _smIsEdit = true; setSmCredit(val); _smIsEdit = was;
}

function openSupplierModal(s, updateUrl, mode) {
    // Header identity
    var initial = (s.name || '?').charAt(0).toUpperCase();
    var avatar  = document.getElementById('sm-avatar');
    avatar.textContent  = initial;
    avatar.style.background = _avatarGrad(s.name || '');
    document.getElementById('sm-hd-name').textContent = s.name || '';
    document.getElementById('sm-hd-code').textContent = s.supplier_code ? s.supplier_code : '';
    document.getElementById('sm-hd-cat').textContent  = s.category ? '· ' + s.category : '';

    // Fields
    document.getElementById('sm-code').value     = s.supplier_code  || '';
    document.getElementById('sm-name').value     = s.name           || '';
    document.getElementById('sm-category').value = s.category       || '';
    document.getElementById('sm-website').value  = s.website        || '';
    document.getElementById('sm-contact').value  = s.contact_person || '';
    document.getElementById('sm-email').value    = s.email          || '';
    document.getElementById('sm-email2').value   = s.secondary_email|| '';
    document.getElementById('sm-phone').value    = s.phone          || '';
    document.getElementById('sm-phone2').value   = s.phone2         || '';
    document.getElementById('sm-whatsapp').value = s.whatsapp       || '';
    document.getElementById('sm-address').value  = s.address        || '';
    document.getElementById('sm-tax').value      = s.tax_number     || '';
    document.getElementById('sm-days').value     = s.credit_days != null ? s.credit_days : '';
    document.getElementById('sm-remarks').value  = s.remarks        || '';

    // Toggles (render without edit guard)
    _renderStatus(s.is_active ? 1 : 0);
    var ct = (s.credit_terms || '').toUpperCase();
    _renderCredit(ct === 'Y' || ct === 'YES' ? 'Y' : ct === 'N' || ct === 'NO' ? 'N' : '');

    document.getElementById('supplier-form').action = updateUrl;
    setSupplierMode(mode);
    document.getElementById('supplier-modal').classList.add('open');
}

function openNewSupplierModal() {
    // Avatar — plus icon style
    var avatar = document.getElementById('sm-avatar');
    avatar.textContent       = '+';
    avatar.style.background  = 'linear-gradient(135deg,#16a34a,#059669)';
    avatar.style.boxShadow   = '0 4px 10px rgba(22,163,74,.3)';
    document.getElementById('sm-hd-name').textContent = 'New Supplier';
    document.getElementById('sm-hd-code').textContent = '';
    document.getElementById('sm-hd-cat').textContent  = '';

    // Clear every field
    ['sm-code','sm-name','sm-category','sm-website','sm-contact',
     'sm-email','sm-email2','sm-phone','sm-phone2','sm-whatsapp',
     'sm-address','sm-tax','sm-days','sm-remarks'].forEach(function(id) {
        document.getElementById(id).value = '';
    });

    // Default toggles
    _renderStatus(1);         // Active by default
    _renderCredit('');        // Credit unset

    // Form targets the store (POST) route — disable the _method override
    document.getElementById('supplier-form').action = _smStoreUrl;
    document.getElementById('sm-method-field').disabled = true;

    setSupplierMode('create');
    document.getElementById('supplier-modal').classList.add('open');
    setTimeout(function() { document.getElementById('sm-name').focus(); }, 80);
}

function setSupplierMode(mode) {
    _smIsEdit = (mode === 'edit' || mode === 'create');
    var isCreate = mode === 'create';
    var form     = document.getElementById('supplier-form');
    var inputs   = form.querySelectorAll('input:not([type=hidden]):not([disabled~=permanent]), select, textarea');

    form.classList.toggle('view',  mode === 'view');
    form.classList.toggle('edit',  _smIsEdit);

    inputs.forEach(function(el) {
        if (_smIsEdit) el.removeAttribute('disabled');
        else el.setAttribute('disabled', '');
    });

    // Toggles
    document.getElementById('sm-status-wrap').style.pointerEvents = _smIsEdit ? '' : 'none';
    document.getElementById('sm-credit-wrap').style.pointerEvents = _smIsEdit ? '' : 'none';

    // Mode badge
    var badge = document.getElementById('sm-mode-badge');
    var badgeCfg = {
        view:   { text:'View',    bg:'#f1f5f9', color:'#64748b' },
        edit:   { text:'Editing', bg:'#fffbeb', color:'#92400e' },
        create: { text:'New',     bg:'#f0fdf4', color:'#15803d' },
    }[mode];
    badge.textContent      = badgeCfg.text;
    badge.style.background = badgeCfg.bg;
    badge.style.color      = badgeCfg.color;

    // Top stripe colour
    var stripes = {
        view:   'linear-gradient(90deg,#2563eb,#7c3aed,#0ea5e9)',
        edit:   'linear-gradient(90deg,#f59e0b,#d97706,#b45309)',
        create: 'linear-gradient(90deg,#16a34a,#059669,#0ea5e9)',
    };
    document.getElementById('sm-stripe').style.background = stripes[mode];

    // Save button label & colour
    var saveBtn = document.getElementById('sm-save-btn');
    document.getElementById('sm-save-label').textContent = isCreate ? 'Add Supplier' : 'Save Changes';
    saveBtn.style.background = isCreate
        ? 'linear-gradient(135deg,#16a34a,#059669)'
        : 'linear-gradient(135deg,#2563eb,#1d4ed8)';
    saveBtn.style.boxShadow = isCreate
        ? '0 3px 10px rgba(22,163,74,.35)'
        : '0 3px 10px rgba(37,99,235,.35)';

    // Show/hide footer buttons
    document.getElementById('sm-edit-btn').style.display = (mode === 'view') ? 'flex' : 'none';
    saveBtn.style.display = _smIsEdit ? 'flex' : 'none';

    // Restore method field for edit mode
    if (!isCreate) {
        document.getElementById('sm-method-field').disabled = false;
        document.getElementById('sm-method-field').value    = 'PUT';
    }
}

function enableSupplierEdit() { setSupplierMode('edit'); }

function closeSupplierModal() {
    document.getElementById('supplier-modal').classList.remove('open');
}

document.getElementById('supplier-modal').addEventListener('click', function(e) {
    if (e.target === this) closeSupplierModal();
});

// ══════════════════════════════════════════════════════════
// Row context menu
// ══════════════════════════════════════════════════════════
(function () {
    var menu    = document.getElementById('row-menu');
    var current = null;

    function closeMenu() {
        menu.style.display = 'none';
        current = null;
    }

    function openMenu(row, x, y) {
        var s        = JSON.parse(row.dataset.supplier);
        var delUrl   = row.dataset.delete;
        var updUrl   = row.dataset.update;
        var email    = s.email;
        var waNum    = s.whatsapp || s.phone;

        document.getElementById('rm-supplier-label').textContent = s.name;

        var rmEmail = document.getElementById('rm-email');
        if (email) {
            rmEmail.href = 'mailto:' + email;
            rmEmail.style.opacity = '1'; rmEmail.style.pointerEvents = '';
        } else {
            rmEmail.href = '#'; rmEmail.style.opacity = '.35'; rmEmail.style.pointerEvents = 'none';
        }

        var rmWa = document.getElementById('rm-whatsapp');
        if (waNum) {
            rmWa.href = 'https://wa.me/' + waNum.replace(/[^0-9]/g, '');
            rmWa.style.opacity = '1'; rmWa.style.pointerEvents = '';
        } else {
            rmWa.href = '#'; rmWa.style.opacity = '.35'; rmWa.style.pointerEvents = 'none';
        }

        document.getElementById('rm-view').onclick = function() {
            closeMenu(); openSupplierModal(s, updUrl, 'view');
        };
        document.getElementById('rm-delete').onclick = function() {
            closeMenu(); openDeleteModal(s.name, delUrl);
        };

        menu.style.display = 'block';
        menu.style.animation = 'menuIn .18s cubic-bezier(.34,1.56,.64,1)';
        var mw = menu.offsetWidth, mh = menu.offsetHeight;
        var vw = window.innerWidth,  vh = window.innerHeight;
        var left = x + 4, top = y + 4;
        if (left + mw > vw - 10) left = vw - mw - 10;
        if (top  + mh > vh - 10) top  = y - mh - 4;
        menu.style.left = left + 'px'; menu.style.top = top + 'px';
        current = row;
    }

    document.querySelectorAll('.supplier-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.closest('a, button, form')) return;
            e.stopPropagation();
            if (current === row && menu.style.display !== 'none') { closeMenu(); return; }
            closeMenu();
            openMenu(row, e.clientX, e.clientY);
        });
    });

    document.addEventListener('click', function(e) {
        if (!menu.contains(e.target)) closeMenu();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeMenu(); closeDeleteModal(); closeSupplierModal(); closeImportModal(); }
    });
})();

// ══════════════════════════════════════════════════════════
// Delete confirmation modal
// ══════════════════════════════════════════════════════════
var _delExpected = '';

function openDeleteModal(name, actionUrl) {
    _delExpected = name;
    document.getElementById('del-supplier-name').textContent = name;
    var inp = document.getElementById('del-confirm-input');
    inp.value = ''; inp.style.borderColor = '#e2e8f0';
    document.getElementById('del-match-hint').textContent = '';
    var btn = document.getElementById('del-confirm-btn');
    btn.disabled = true; btn.style.background = '#e2e8f0'; btn.style.color = '#94a3b8'; btn.style.cursor = 'not-allowed';
    document.getElementById('delete-form').action = actionUrl;
    document.getElementById('delete-modal').classList.add('open');
    setTimeout(function() { inp.focus(); }, 80);
}
function closeDeleteModal() { document.getElementById('delete-modal').classList.remove('open'); }

document.getElementById('del-confirm-input').addEventListener('input', function() {
    var btn = document.getElementById('del-confirm-btn');
    var hint = document.getElementById('del-match-hint');
    if (this.value === _delExpected) {
        btn.disabled = false; btn.style.background = '#dc2626'; btn.style.color = '#fff'; btn.style.cursor = 'pointer';
        hint.textContent = '✓ Name matches — you may now confirm deletion.'; hint.style.color = '#16a34a';
        this.style.borderColor = '#4ade80';
    } else {
        btn.disabled = true; btn.style.background = '#e2e8f0'; btn.style.color = '#94a3b8'; btn.style.cursor = 'not-allowed';
        if (this.value.length > 0) { hint.textContent = 'Name does not match yet…'; hint.style.color = '#f59e0b'; this.style.borderColor = '#fbbf24'; }
        else { hint.textContent = ''; this.style.borderColor = '#e2e8f0'; }
    }
});
document.getElementById('delete-modal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// ══════════════════════════════════════════════════════════
// Live search (client-side, no page reload)
// ══════════════════════════════════════════════════════════
(function () {
    var searchInput = document.getElementById('supplier-search');
    var countEl     = document.getElementById('search-count');
    var noResults   = document.getElementById('no-results');
    var rows        = document.querySelectorAll('.supplier-row');
    var total       = rows.length;

    searchInput.addEventListener('input', function() {
        var q = this.value.trim().toLowerCase(), visible = 0;
        rows.forEach(function(row) {
            var text = row.textContent.toLowerCase();
            if (!q || text.indexOf(q) !== -1) { row.classList.remove('hidden-by-search'); visible++; }
            else { row.classList.add('hidden-by-search'); }
        });
        countEl.textContent = q ? (visible + ' of ' + total + ' suppliers') : (total + ' suppliers');
        noResults.style.display = (visible === 0 && q) ? 'block' : 'none';
    });

    searchInput.focus();
})();
</script>

@endsection
