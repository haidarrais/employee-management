@extends('layouts.app')

@section('title', 'Page not found — Attendance')

@section('content')
<div class="page-wrap">
  <div class="sub-nav">
    <div class="sub-nav__inner">
      <a href="{{ url('/') }}" class="sub-nav__link">Dashboard</a>
      <span class="sub-nav__title">Not Found</span>
    </div>
  </div>

  <div class="content-wrap" style="padding-top:96px;padding-bottom:96px;text-align:center;">
    <p class="t-tagline t-blue" style="margin:0 0 16px;">Error 404</p>
    <h1 class="t-display t-ink" style="margin:0 0 20px;">Page not found</h1>
    <p class="t-body t-muted" style="margin:0 auto 40px;max-width:520px;">
      The page you asked for does not exist, or it has moved.
    </p>
    <a href="{{ url('/') }}" class="btn-primary">Back to dashboard</a>
  </div>
</div>
@endsection
