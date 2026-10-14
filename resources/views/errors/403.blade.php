@extends('layouts.app')

@section('title', 'Not allowed — Attendance')

@section('content')
<div class="page-wrap">
  <div class="sub-nav">
    <div class="sub-nav__inner">
      <a href="{{ url('/') }}" class="sub-nav__link">Dashboard</a>
      <span class="sub-nav__title">Forbidden</span>
    </div>
  </div>

  <div class="content-wrap" style="padding-top:96px;padding-bottom:96px;text-align:center;">
    <p class="t-tagline t-blue" style="margin:0 0 16px;">Error 403</p>
    <h1 class="t-display t-ink" style="margin:0 0 20px;">Not allowed</h1>
    <p class="t-body t-muted" style="margin:0 auto 40px;max-width:520px;">
      Your account does not have access to this page. If you think that is
      wrong, ask an administrator to check your role.
    </p>
    <a href="{{ url('/') }}" class="btn-primary">Back to dashboard</a>
  </div>
</div>
@endsection
