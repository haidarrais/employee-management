@extends('layouts.app')
@section('title', 'Payroll Report')

@section('content')
<div class="page-wrap">
  <div class="sub-nav">
    <div class="sub-nav__inner">
      <a href="{{ route('dashboard') }}" class="sub-nav__link">Dashboard</a>
      <span class="sub-nav__title">Payroll Report</span>
      <span class="t-caption t-muted">{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</span>
    </div>
  </div>

  <section class="content-wrap section-pad">
    <div class="card">
      <div class="card-body">
        <h1 class="t-display">Payroll Overview</h1>
        <p class="t-body t-muted">Attendance-based payroll summary with working time, overtime, and deductions.</p>

        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:24px;">
          <input type="month" name="month" value="{{ $month }}" class="form-input" style="width:auto">
          <select name="employee_id" class="form-input" style="width:auto">
            <option value="">All employees</option>
            @foreach($employees as $emp)
            <option value="{{ $emp->id }}" {{ $employeeId == $emp->id ? 'selected' : '' }}>{{ $emp->name }}</option>
            @endforeach
          </select>
          <button class="btn-primary">Apply Filter</button>
        </form>
      </div>
    </div>

    <div class="card" style="margin-top:24px;">
      <div class="card-header"><h2 class="t-section" style="font-size:24px">Employee Payroll</h2></div>
      <div class="card-body">
        @forelse($summary as $row)
        <div style="padding:20px 0;border-bottom:1px solid var(--hairline)">
          <div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap">
            <div>
              <h3 style="font-size:20px">{{ $row['user']->name }}</h3>
              <p class="t-caption t-muted">{{ $row['user']->email }}</p>
            </div>
            <div style="text-align:right">
              <p class="t-caption t-muted">Total Payroll</p>
              <strong style="font-size:24px">Rp {{ number_format($row['total_fee'],0,'.',',') }}</strong>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-top:16px">
            <div class="card"><div class="card-body"><span class="t-caption">Attendance</span><strong style="display:block;font-size:24px">{{ $row['days_attended'] }} days</strong></div></div>
            <div class="card"><div class="card-body"><span class="t-caption">Late</span><strong style="display:block;font-size:24px">{{ $row['total_late_min'] }} min</strong></div></div>
            <div class="card"><div class="card-body"><span class="t-caption">Overtime</span><strong style="display:block;font-size:24px">{{ $row['total_overtime_min'] }} min</strong></div></div>
          </div>
        </div>
        @empty
        <div class="ux-empty-state"><p class="t-body t-muted">No payroll records found.</p></div>
        @endforelse
      </div>
    </div>
  </section>
</div>
@endsection
