@extends('layouts.app')
@section('title', 'Working Hours Schedule')

@section('content')
@php
  $dayLabels = [
    'monday'    => 'Monday',
    'tuesday'   => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday'  => 'Thursday',
    'friday'    => 'Friday',
    'saturday'  => 'Saturday',
    'sunday'    => 'Sunday',
  ];
  $specialDays = collect($schedule['special_days'] ?? [])->sortKeys();
@endphp

<div class="page-wrap">

  <div class="sub-nav">
    <div class="sub-nav__inner">
      <a href="{{ route('reports.payroll') }}" class="sub-nav__link" style="display:flex;align-items:center;gap:6px;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        Payroll Report
      </a>
      <span class="sub-nav__title">Schedule & Holidays</span>
      <div style="display:flex;gap:8px;">
        <a href="#special-days" class="sub-nav__link">Holidays</a>
        <a href="#working-hours" class="sub-nav__link">Hours</a>
      </div>
    </div>
  </div>

  @if(session('success'))
  <div style="background:var(--canvas);padding:16px 22px 0;">
    <div style="max-width:640px;margin:0 auto;">
      <div class="alert alert-success">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        {{ session('success') }}
      </div>
    </div>
  </div>
  @endif

  {{-- ══════════════════════════════════════════════════════════
       SECTION 1 — Special Days (holidays & overtime Sundays)
  ══════════════════════════════════════════════════════════ --}}
  <section id="special-days" style="background:var(--canvas);padding:var(--space-section) 22px;">
    <div style="max-width:640px;margin:0 auto;">

      <h1 class="t-display t-ink" style="margin:0 0 8px;">Public Holidays & Special Days</h1>
      <p class="t-body t-muted" style="margin:0 0 32px;">
        Mark dates as public holidays or authorised overtime days.
        Employees who work on these days are paid at the holiday overtime rate — no base deduction, no late penalty.
        You can set a per-day rate override to override the global holiday rate.
      </p>

      {{-- Add form --}}
      <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><p class="t-tagline t-ink" style="margin:0;">Add Special Day</p></div>
        <div class="card-body">
          <form method="POST" action="{{ route('reports.schedule.special-days.add') }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
              <div>
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-input" required
                       min="{{ now()->format('Y-m-d') }}">
                @error('date')<p class="form-error">{{ $message }}</p>@enderror
              </div>
              <div>
                <label class="form-label">Type</label>
                <select name="type" class="form-input" required id="sd-type" onchange="toggleSdRate()">
                  <option value="holiday">Public Holiday</option>
                  <option value="overtime_sunday">Overtime Sunday / Special Work Day</option>
                </select>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
              <div>
                <label class="form-label">Name / Description</label>
                <input type="text" name="name" class="form-input" required
                       placeholder="e.g. New Year's Day">
                @error('name')<p class="form-error">{{ $message }}</p>@enderror
              </div>
              <div>
                <label class="form-label">
                  Rate Override per Hour (Rp)
                  <span class="t-fine t-muted">(optional)</span>
                </label>
                <input type="number" name="rate_per_hour" class="form-input"
                       min="0" step="1000"
                       placeholder="Leave blank = global holiday rate (Rp {{ number_format($schedule['holiday_overtime_rate_per_hour'] ?? 50000) }})">
                @error('rate_per_hour')<p class="form-error">{{ $message }}</p>@enderror
              </div>
            </div>
            <button type="submit" class="btn-primary btn-sm">Add Special Day</button>
          </form>
        </div>
      </div>

      {{-- List --}}
      @if($specialDays->isEmpty())
      <div class="card" style="padding:32px;text-align:center;">
        <p class="t-body t-muted" style="margin:0;">No special days configured yet.</p>
      </div>
      @else
      <div class="card" style="overflow:hidden;">
        <div style="overflow-x:auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Name</th>
              <th>Type</th>
              <th>Rate / hr</th>
              <th style="text-align:right;">Remove</th>
            </tr>
          </thead>
          <tbody>
            @foreach($specialDays as $dateStr => $sd)
            @php
              $dt       = \Carbon\Carbon::parse($dateStr);
              $isPast   = $dt->isPast();
              $rateDisp = isset($sd['rate_per_hour'])
                ? 'Rp ' . number_format($sd['rate_per_hour'])
                : 'Global (' . number_format($schedule['holiday_overtime_rate_per_hour'] ?? 50000) . ')';
            @endphp
            <tr style="{{ $isPast ? 'opacity:0.5;' : '' }}">
              <td style="white-space:nowrap;font-weight:600;">
                {{ $dt->format('D, d M Y') }}
                @if($isPast)<span class="badge badge-gray" style="margin-left:6px;">Past</span>@endif
              </td>
              <td>{{ $sd['name'] }}</td>
              <td>
                @if(($sd['type'] ?? '') === 'holiday')
                  <span class="badge badge-error">Public Holiday</span>
                @else
                  <span class="badge badge-warning">Overtime Sunday</span>
                @endif
              </td>
              <td class="t-caption t-ink">{{ $rateDisp }}</td>
              <td style="text-align:right;">
                <form method="POST" action="{{ route('reports.schedule.special-days.remove') }}"
                      onsubmit="return confirm('Remove {{ addslashes($sd['name']) }}?')">
                  @csrf @method('DELETE')
                  <input type="hidden" name="date" value="{{ $dateStr }}">
                  <button type="submit" class="btn-danger btn-sm">Remove</button>
                </form>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
        </div>
      </div>
      @endif

    </div>
  </section>

  {{-- ══════════════════════════════════════════════════════════
       SECTION 2 — Working hours per day
  ══════════════════════════════════════════════════════════ --}}
  <section id="working-hours" style="background:var(--parchment);padding:var(--space-section) 22px;">
    <div style="max-width:640px;margin:0 auto;">

      <h2 class="t-section t-ink" style="margin:0 0 8px;">Working Hours</h2>
      <p class="t-body t-muted" style="margin:0 0 32px;">
        Set work segments per day. Mark a day as <strong>Off</strong> to exclude it from regular attendance.
        Employees who work on off-days are treated as special overtime.
      </p>

      <form method="POST" action="{{ route('reports.schedule.update') }}">
        @csrf @method('PUT')

        {{-- Per-day cards --}}
        @foreach($dayLabels as $dayKey => $dayLabel)
        @php
          $dayCfg = $schedule['days'][$dayKey] ?? ['off' => true];
          $isOff  = $dayCfg['off'] ?? false;
          $seg0   = $dayCfg['segments'][0] ?? ['start' => '07:00', 'end' => '12:00'];
          $seg1   = $dayCfg['segments'][1] ?? null;
        @endphp

        <div class="card" style="margin-bottom:10px;" id="card-{{ $dayKey }}">
          <div class="card-header" style="cursor:pointer;" onclick="toggleDay('{{ $dayKey }}')">
            <div style="display:flex;align-items:center;gap:12px;">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;" onclick="event.stopPropagation()">
                <input type="checkbox"
                       name="day_{{ $dayKey }}_off"
                       id="off_{{ $dayKey }}"
                       class="checkbox"
                       value="1"
                       {{ $isOff ? 'checked' : '' }}
                       onchange="handleOffToggle('{{ $dayKey }}')">
                <span class="t-caption t-muted">Off</span>
              </label>
              <p class="t-tagline t-ink" style="margin:0;">{{ $dayLabel }}</p>
            </div>
            <span class="t-caption t-muted">
              @if($isOff)
                <span class="badge badge-gray">Day off</span>
              @else
                {{ $seg0['start'] }}–{{ $seg0['end'] }}
                @if($seg1) · {{ $seg1['start'] }}–{{ $seg1['end'] }} @endif
              @endif
            </span>
          </div>

          <div id="body-{{ $dayKey }}" style="{{ $isOff ? 'display:none;' : '' }}padding:0 24px 20px;">
            <p class="t-fine t-muted" style="margin:16px 0 8px;text-transform:uppercase;letter-spacing:0.06em;">Morning</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div>
                <label class="form-label">Start</label>
                <input type="time" name="day_{{ $dayKey }}_seg0_start" class="form-input"
                       value="{{ $seg0['start'] }}" {{ $isOff ? 'disabled' : '' }}>
              </div>
              <div>
                <label class="form-label">End</label>
                <input type="time" name="day_{{ $dayKey }}_seg0_end" class="form-input"
                       value="{{ $seg0['end'] }}" {{ $isOff ? 'disabled' : '' }}>
              </div>
            </div>

            <div style="text-align:center;margin:10px 0 4px;">
              <span class="badge badge-gray">Break</span>
            </div>

            <p class="t-fine t-muted" style="margin:8px 0;text-transform:uppercase;letter-spacing:0.06em;">
              Afternoon <span style="text-transform:none;font-weight:400;">(optional)</span>
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div>
                <label class="form-label">Start</label>
                <input type="time" name="day_{{ $dayKey }}_seg1_start" class="form-input"
                       value="{{ $seg1['start'] ?? '' }}" {{ $isOff ? 'disabled' : '' }}>
              </div>
              <div>
                <label class="form-label">End</label>
                <input type="time" name="day_{{ $dayKey }}_seg1_end" class="form-input"
                       value="{{ $seg1['end'] ?? '' }}" {{ $isOff ? 'disabled' : '' }}>
              </div>
            </div>
          </div>
        </div>
        @endforeach

        {{-- Pay & grace --}}
        <div class="card" style="margin-top:20px;margin-bottom:28px;">
          <div class="card-header"><p class="t-tagline t-ink" style="margin:0;">Pay & Grace</p></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">

            <div>
              <label class="form-label">Grace Period (minutes)</label>
              <input type="number" name="grace_minutes" class="form-input"
                     value="{{ $schedule['grace_minutes'] ?? 15 }}" min="0" max="120" required>
              <p class="form-hint">Late is counted after this many minutes past the day's official start.</p>
            </div>

            <div>
              <label class="form-label">Daily Base Fee (Rp)</label>
              <input type="number" name="daily_base_fee" class="form-input"
                     value="{{ $schedule['daily_base_fee'] ?? 150000 }}" min="0" required>
              <p class="form-hint">Global default. Overridden per employee on the user edit page.</p>
            </div>

            <div>
              <label class="form-label">Regular Overtime Rate per Hour (Rp)</label>
              <input type="number" name="overtime_rate_per_hour" class="form-input"
                     value="{{ $schedule['overtime_rate_per_hour'] ?? 25000 }}" min="0" required>
              <p class="form-hint">Applied to overtime on regular working days.</p>
            </div>

            <div style="background:var(--parchment);border:1px solid var(--line);border-radius:10px;padding:16px;">
              <label class="form-label" style="color:var(--ink);">
                Holiday / Sunday Overtime Rate per Hour (Rp)
              </label>
              <input type="number" name="holiday_overtime_rate_per_hour" class="form-input"
                     value="{{ $schedule['holiday_overtime_rate_per_hour'] ?? 50000 }}" min="0" required>
              <p class="form-hint">
                Applied when an employee works on a public holiday or an authorised overtime Sunday.
                All hours worked count as overtime at this rate — no base pay, no late deduction.
                Individual special days can override this with their own rate.
              </p>
            </div>

          </div>
        </div>

        <button type="submit" class="btn-primary btn-block">Save Working Hours</button>
      </form>
    </div>
  </section>

  <footer style="background:var(--parchment);border-top:1px solid var(--line);padding:24px 22px;">
    <div style="max-width:980px;margin:0 auto;text-align:center;">
      <p class="t-fine t-muted" style="margin:0;">© {{ date('Y') }} Employee Attendance System</p>
    </div>
  </footer>

</div>

<script>
function handleOffToggle(day) {
  const isOff  = document.getElementById('off_' + day).checked;
  const body   = document.getElementById('body-' + day);
  body.style.display = isOff ? 'none' : '';
  body.querySelectorAll('input[type=time]').forEach(i => i.disabled = isOff);
}

function toggleDay(day) {
  const body  = document.getElementById('body-' + day);
  const isOff = document.getElementById('off_' + day).checked;
  if (!isOff) body.style.display = body.style.display === 'none' ? '' : 'none';
}
</script>
@endsection
