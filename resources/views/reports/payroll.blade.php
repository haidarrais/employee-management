@extends('layouts.app')
@section('title', 'Payroll Report')

@section('content')
<div class="page-wrap">

  {{-- Sub-nav --}}
  <div class="sub-nav">
    <div class="sub-nav__inner">
      <a href="{{ route('dashboard') }}" class="sub-nav__link" style="display:flex;align-items:center;gap:6px;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        Dashboard
      </a>
      <span class="sub-nav__title">Payroll Report</span>
      <span class="t-caption t-muted">{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</span>
    </div>
  </div>

  {{-- ── Filters ──────────────────────────────────────────────── --}}
  <section style="background:var(--canvas);padding:28px 22px 0;">
    <div style="max-width:1200px;margin:0 auto;">
      <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        <input type="month" name="month" value="{{ $month }}"
               class="form-input" style="width:auto;" onchange="this.form.submit()">

        <select name="employee_id" class="form-input" style="width:auto;" onchange="this.form.submit()">
          <option value="">All employees</option>
          @foreach($employees as $emp)
          <option value="{{ $emp->id }}" {{ $employeeId == $emp->id ? 'selected' : '' }}>
            {{ $emp->name }}
          </option>
          @endforeach
        </select>

        <button type="submit" class="btn-primary btn-sm">Apply</button>
        @if($employeeId)
        <a href="{{ route('reports.payroll', ['month' => $month]) }}" class="btn-ghost-pill btn-sm">Clear</a>
        @endif
      </form>
    </div>
  </section>

  {{-- ── Schedule info bar ───────────────────────────────────── --}}
  <section style="background:var(--canvas);padding:16px 22px 28px;">
    <div style="max-width:1200px;margin:0 auto;">
      <div style="background:var(--parchment);border:1px solid var(--line);border-radius:12px;padding:14px 20px;">
        <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;">

          @foreach(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $dayName)
          @php $dayCfg = $schedule['days'][$dayName] ?? ['off' => true]; @endphp
          <div style="min-width:100px;">
            <p class="t-fine t-muted" style="margin:0 0 4px;text-transform:uppercase;letter-spacing:0.06em;">
              {{ ucfirst(substr($dayName,0,3)) }}
            </p>
            @if($dayCfg['off'] ?? false)
              <span class="badge badge-gray">Off</span>
            @else
              @foreach($dayCfg['segments'] as $seg)
              <span class="badge badge-blue" style="display:block;margin-bottom:3px;">{{ $seg['start'] }}–{{ $seg['end'] }}</span>
              @endforeach
            @endif
          </div>
          @endforeach

          <div style="margin-left:auto;display:flex;flex-direction:column;gap:4px;align-items:flex-end;">
            <span class="t-caption t-muted">Grace: <strong>{{ $schedule['grace_minutes'] }} min</strong></span>
            <span class="t-caption t-muted">Base: <strong>Rp {{ number_format($schedule['daily_base_fee']) }}/day</strong></span>
            <span class="t-caption t-muted">OT: <strong>Rp {{ number_format($schedule['overtime_rate_per_hour']) }}/hr</strong></span>
            <a href="{{ route('reports.schedule') }}" class="t-caption t-blue" style="margin-top:4px;">Edit →</a>
          </div>

        </div>
      </div>
    </div>
  </section>

  {{-- ── Summary table ────────────────────────────────────────── --}}
  <section style="background:var(--parchment);padding:32px 22px;">
    <div style="max-width:1200px;margin:0 auto;">
      <h2 class="t-section t-ink" style="margin:0 0 20px;">Summary</h2>

      @if($summary->isEmpty())
      <div class="card" style="padding:48px;text-align:center;">
        <p class="t-body t-muted" style="margin:0;">No confirmed attendance records for this period.</p>
      </div>
      @else

      {{-- Summary cards --}}
      <div class="card" style="overflow:hidden;margin-bottom:40px;">
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Employee</th>
                <th style="text-align:center;">Days</th>
                <th style="text-align:center;">Total Late</th>
                <th style="text-align:center;">Total Overtime</th>
                <th style="text-align:right;">Total Fee</th>
              </tr>
            </thead>
            <tbody>
              @foreach($summary as $row)
              <tr>
                <td>
                  <p style="margin:0;font-weight:600;color:var(--ink);">{{ $row['user']->name }}</p>
                  <p class="t-fine t-muted" style="margin:0;">{{ $row['user']->email }}</p>
                  @if($row['user']->daily_rate)
                  <p class="t-fine" style="margin:2px 0 0;color:var(--blue);">
                    Rp {{ number_format($row['user']->daily_rate) }}/day
                    · OT Rp {{ number_format($row['user']->overtime_rate ?? $schedule['overtime_rate_per_hour']) }}/hr
                  </p>
                  @else
                  <p class="t-fine t-muted" style="margin:2px 0 0;">Global rate</p>
                  @endif
                </td>
                <td style="text-align:center;">
                  <span style="font-size:22px;font-weight:600;color:var(--ink);">{{ $row['days_attended'] }}</span>
                  <span class="t-fine t-muted"> days</span>
                </td>
                <td style="text-align:center;">
                  @php $lh = intdiv($row['total_late_min'],60); $lm = $row['total_late_min']%60; @endphp
                  @if($row['total_late_min'] > 0)
                    <span class="badge badge-error">{{ $lh > 0 ? $lh.'h ' : '' }}{{ $lm }}m</span>
                  @else
                    <span class="badge badge-success">On time</span>
                  @endif
                </td>
                <td style="text-align:center;">
                  @php $oh = intdiv($row['total_overtime_min'],60); $om = $row['total_overtime_min']%60; @endphp
                  @if($row['total_overtime_min'] > 0)
                    <span class="badge badge-blue">{{ $oh > 0 ? $oh.'h ' : '' }}{{ $om }}m</span>
                  @else
                    <span class="t-fine t-muted">—</span>
                  @endif
                </td>
                <td style="text-align:right;">
                  <span style="font-size:17px;font-weight:600;color:var(--ink);">
                    Rp {{ number_format($row['total_fee'],0,'.',',') }}
                  </span>
                </td>
              </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr style="background:var(--parchment);border-top:2px solid var(--line);">
                <td style="font-weight:600;color:var(--ink);padding:14px 20px;">Total</td>
                <td style="text-align:center;font-weight:600;color:var(--ink);padding:14px 20px;">
                  {{ $summary->sum('days_attended') }}
                </td>
                <td style="text-align:center;padding:14px 20px;">
                  @php $tl = $summary->sum('total_late_min'); @endphp
                  <span class="{{ $tl > 0 ? 'badge badge-error' : 't-fine t-muted' }}">
                    {{ $tl > 0 ? intdiv($tl,60).'h '.($tl%60).'m' : '—' }}
                  </span>
                </td>
                <td style="text-align:center;padding:14px 20px;">
                  @php $tot = $summary->sum('total_overtime_min'); @endphp
                  <span class="{{ $tot > 0 ? 'badge badge-blue' : 't-fine t-muted' }}">
                    {{ $tot > 0 ? intdiv($tot,60).'h '.($tot%60).'m' : '—' }}
                  </span>
                </td>
                <td style="text-align:right;font-weight:600;color:var(--ink);padding:14px 20px;">
                  Rp {{ number_format($summary->sum('total_fee'),0,'.',',') }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      {{-- ── Daily detail per employee ───────────────────────── --}}
      <h2 class="t-section t-ink" style="margin:0 0 20px;">Daily Detail</h2>

      @foreach($summary as $row)
      <div class="card" style="overflow:hidden;margin-bottom:24px;">
        <div class="card-header">
          <div>
            <p style="margin:0;font-weight:600;color:var(--ink);">{{ $row['user']->name }}</p>
            <p class="t-fine t-muted" style="margin:0;">{{ $row['user']->email }}</p>
          </div>
          <span class="badge badge-gray">
            {{ $row['days_attended'] }} days · Rp {{ number_format($row['total_fee'],0,'.',',') }}
          </span>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Day</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th style="text-align:center;">Late</th>
                <th style="text-align:center;">Overtime</th>
                <th style="text-align:right;">Fee</th>
                <th style="min-width:220px;">Timeline</th>
              </tr>
            </thead>
            <tbody>
              @foreach($row['records'] as $rec)
              @php
                $dayName     = strtolower($rec->check_in_at->format('l'));   // 'monday', 'saturday' …
                $dayCfg      = $schedule['days'][$dayName] ?? null;
                $daySegs     = $dayCfg['segments'] ?? [['start'=>'07:00','end'=>'16:00']];

                // Official start/end for this specific day
                $offStartMin = (int) substr($daySegs[0]['start'],0,2) * 60
                             + (int) substr($daySegs[0]['start'],3,2);
                $lastSeg     = end($daySegs);
                $offEndMin   = (int) substr($lastSeg['end'],0,2) * 60
                             + (int) substr($lastSeg['end'],3,2);
                $graceMin    = (int) ($schedule['grace_minutes'] ?? 15);
                $graceEndMin = $offStartMin + $graceMin;

                // Timeline window: 30 min before official start → 90 min after official end
                $winStart = max(0, $offStartMin - 30);
                $winEnd   = $offEndMin + 90;
                $winLen   = $winEnd - $winStart;

                $checkInMin  = $rec->check_in_at->hour * 60 + $rec->check_in_at->minute;
                $checkOutMin = $rec->check_out_at ? $rec->check_out_at->hour * 60 + $rec->check_out_at->minute : null;

                $ciPct = max(0, min(100, (($checkInMin - $winStart) / $winLen) * 100));
                $coPct = $checkOutMin !== null
                       ? max(0, min(100, (($checkOutMin - $winStart) / $winLen) * 100))
                       : null;

                $startPct = (($offStartMin - $winStart) / $winLen) * 100;
                $gracePct = (($graceEndMin - $winStart) / $winLen) * 100;
                $endPct   = (($offEndMin   - $winStart) / $winLen) * 100;

                $ciColor = $checkInMin <= $offStartMin ? '#34c759'
                         : ($checkInMin <= $graceEndMin ? '#ff9f0a' : '#ff3b30');
              @endphp
              <tr>
                <td style="white-space:nowrap;">
                  <p style="margin:0;font-weight:600;color:var(--ink);">{{ $rec->check_in_at->format('d M Y') }}</p>
                </td>
                <td style="white-space:nowrap;">
                  @php
                    $sdEntry = $schedule['special_days'][$rec->check_in_at->toDateString()] ?? null;
                  @endphp
                  @if($sdEntry)
                    <span class="badge {{ ($sdEntry['type'] ?? '') === 'holiday' ? 'badge-error' : 'badge-warning' }}"
                          title="{{ $sdEntry['name'] }}">
                      {{ $rec->check_in_at->format('D') }} ★
                    </span>
                  @else
                    <span class="badge {{ $rec->check_in_at->isSaturday() ? 'badge-warning' : 'badge-gray' }}">
                      {{ $rec->check_in_at->format('D') }}
                    </span>
                  @endif
                </td>
                <td style="white-space:nowrap;font-weight:600;color:{{ $ciColor }};">
                  {{ $rec->check_in_at->format('H:i') }}
                </td>
                <td style="white-space:nowrap;color:var(--muted);">
                  {{ $rec->check_out_at?->format('H:i') ?? '—' }}
                </td>
                <td style="text-align:center;">
                  @if($rec->late_minutes > 0)
                    <span class="badge badge-error">{{ $rec->late_minutes }}m</span>
                  @else
                    <span class="badge badge-success">✓</span>
                  @endif
                </td>
                <td style="text-align:center;">
                  @if($rec->overtime_minutes > 0)
                    <span class="badge badge-blue">
                      {{ intdiv($rec->overtime_minutes,60) }}h {{ $rec->overtime_minutes%60 }}m
                    </span>
                  @else
                    <span class="t-fine t-muted">—</span>
                  @endif
                </td>
                <td style="text-align:right;font-weight:600;color:var(--ink);">
                  Rp {{ number_format($rec->computed_fee,0,'.',',') }}
                </td>

                {{-- ── Timeline bar ──────────────────────────── --}}
                <td style="padding:10px 20px;">
                  <div style="position:relative;height:18px;background:var(--soft);border-radius:4px;overflow:visible;">

                    {{-- Work segments shaded --}}
                    @foreach($daySegs as $seg)
                    @php
                      $sMin = (int)substr($seg['start'],0,2)*60+(int)substr($seg['start'],3,2);
                      $eMin = (int)substr($seg['end'],0,2)*60+(int)substr($seg['end'],3,2);
                      $sP   = max(0,(($sMin-$winStart)/$winLen)*100);
                      $eP   = min(100,(($eMin-$winStart)/$winLen)*100);
                    @endphp
                    <div style="position:absolute;left:{{ $sP }}%;width:{{ $eP-$sP }}%;
                                top:0;bottom:0;background:rgba(0,102,204,0.08);border-radius:2px;"></div>
                    @endforeach

                    {{-- Official start line --}}
                    <div style="position:absolute;left:{{ $startPct }}%;top:0;bottom:0;width:1px;background:var(--line);"></div>

                    {{-- Grace end line --}}
                    <div style="position:absolute;left:{{ $gracePct }}%;top:0;bottom:0;width:1px;background:#ff9f0a;opacity:0.6;"></div>

                    {{-- Official end line --}}
                    <div style="position:absolute;left:{{ $endPct }}%;top:0;bottom:0;width:1px;background:var(--line);"></div>

                    {{-- Check-in dot --}}
                    <div style="position:absolute;left:calc({{ $ciPct }}% - 5px);top:50%;transform:translateY(-50%);
                                width:10px;height:10px;border-radius:50%;background:{{ $ciColor }};
                                box-shadow:0 0 0 2px #fff,0 0 0 3px {{ $ciColor }};"
                         title="Check-in {{ $rec->check_in_at->format('H:i') }}"></div>

                    {{-- Check-out dot --}}
                    @if($coPct !== null)
                    <div style="position:absolute;left:calc({{ $coPct }}% - 5px);top:50%;transform:translateY(-50%);
                                width:10px;height:10px;border-radius:50%;background:var(--muted);
                                box-shadow:0 0 0 2px #fff,0 0 0 3px var(--muted);"
                         title="Check-out {{ $rec->check_out_at->format('H:i') }}"></div>
                    @endif
                  </div>

                  {{-- Time axis labels --}}
                  <div style="display:flex;justify-content:space-between;margin-top:3px;">
                    <span class="t-fine t-muted">{{ sprintf('%02d:%02d', intdiv($winStart,60), $winStart%60) }}</span>
                    <span class="t-fine" style="color:var(--blue);font-size:11px;">
                      {{ $daySegs[0]['start'] }}
                    </span>
                    <span class="t-fine t-muted">{{ sprintf('%02d:%02d', intdiv($winEnd,60), $winEnd%60) }}</span>
                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endforeach

      @endif
    </div>
  </section>

  <footer style="background:var(--parchment);border-top:1px solid var(--line);padding:24px 22px;">
    <div style="max-width:1200px;margin:0 auto;text-align:center;">
      <p class="t-fine t-muted" style="margin:0;">© {{ date('Y') }} Employee Attendance System</p>
    </div>
  </footer>

</div>
@endsection
