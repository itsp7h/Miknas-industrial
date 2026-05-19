<div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead>
      <tr style="border-bottom:1px solid #f1f5f9;">
        <th style="padding:12px 18px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">Request #</th>
        <th style="padding:12px 18px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">Project / Description</th>
        <th style="padding:12px 18px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">Department</th>
        <th style="padding:12px 18px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">Requested By</th>
        <th style="padding:12px 18px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">Stage</th>
        <th style="padding:12px 18px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">Progress</th>
        <th style="padding:12px 18px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">Date</th>
        <th style="padding:12px 18px;"></th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $pr)
      @php
        $stageIdx  = $stages->stageIndex($pr->stage ?? 'draft');
        $total     = count(\App\Services\PurchaseStageService::STAGES);
        $pct       = $total > 1 ? round(($stageIdx / ($total - 1)) * 100) : 100;
        $isDone    = $pr->stage === 'complete';
      @endphp
      <tr class="pr-row" onclick="window.location='{{ route('purchase.pipeline.show', $pr) }}'"
          style="border-bottom:1px solid #f8fafc;transition:background .12s;">
        <td style="padding:14px 18px;font-weight:700;color:#0f172a;">{{ $pr->request_number }}</td>
        <td style="padding:14px 18px;color:#374151;max-width:200px;">
          <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            {{ $pr->project_name ?: '—' }}
          </div>
          @if($pr->remarks)
            <div style="font-size:11px;color:#94a3b8;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              {{ Str::limit($pr->remarks, 50) }}
            </div>
          @endif
        </td>
        <td style="padding:14px 18px;color:#64748b;">{{ $pr->department ?: '—' }}</td>
        <td style="padding:14px 18px;color:#64748b;">{{ $pr->requested_by_name ?? $pr->requestedBy?->name ?? '—' }}</td>
        <td style="padding:14px 18px;">
          <span class="stage-pill"
            style="background:{{ $isDone ? '#dcfce7' : '#fffbeb' }};color:{{ $isDone ? '#15803d' : '#92400e' }};">
            {{ $stages->stageLabel($pr->stage) }}
          </span>
        </td>
        <td style="padding:14px 18px;min-width:100px;">
          <div style="height:5px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
            <div style="height:5px;background:{{ $isDone ? '#22c55e' : '#f59e0b' }};width:{{ $pct }}%;border-radius:3px;"></div>
          </div>
          <div style="font-size:10px;color:#94a3b8;margin-top:3px;">{{ $pct }}%</div>
        </td>
        <td style="padding:14px 18px;color:#94a3b8;white-space:nowrap;">
          {{ $pr->date ? \Carbon\Carbon::parse($pr->date)->format('d M Y') : '—' }}
        </td>
        <td style="padding:14px 18px;">
          <svg width="16" height="16" fill="none" stroke="#cbd5e1" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
          </svg>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
