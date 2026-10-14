@extends('layouts.app')
@section('title', 'Attendance Timeline')

@section('content')
<div class="page-wrap">
  <div class="sub-nav">
    <div class="sub-nav__inner">
      <a href="{{ route('dashboard') }}" class="sub-nav__link">Dashboard</a>
      <span class="sub-nav__title">Attendance Timeline</span>
    </div>
  </div>

  <section class="section-pad">
    <div class="content-wrap" style="max-width:900px">
      <div class="card">
        <div class="card-header">
          <div>
            <p class="t-tagline">Attendance</p>
            <h1 class="t-section">Your work timeline</h1>
          </div>
        </div>
        <div class="card-body">
          <div style="display:flex;flex-direction:column;gap:24px">
            @forelse($records as $record)
            <article style="border-left:2px solid var(--primary);padding-left:24px">
              <p class="t-body t-ink" style="font-weight:700">{{ optional($record->check_in_at ?? $record->created_at)->format('l, F j Y') }}</p>
              <div class="card" style="margin-top:12px">
                <div class="card-body">
                  <p class="t-body">🟢 Check in: {{ optional($record->check_in_at ?? $record->created_at)->format('g:i A') }}</p>
                  @if($record->check_out_at)
                  <p class="t-body">🔴 Check out: {{ $record->check_out_at->format('g:i A') }}</p>
                  <p class="t-caption">Worked {{ $record->workedMinutes() }} minutes</p>
                  @else
                  <p class="badge badge-success">Currently working</p>
                  @endif
                </div>
              </div>
            </article>
            @empty
            <div class="t-body t-muted">No attendance history yet.</div>
            @endforelse
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
@endsection
