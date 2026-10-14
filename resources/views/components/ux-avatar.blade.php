@props(['name'])
<div style="width:40px;height:40px;border-radius:50%;background:var(--canvas-soft);display:flex;align-items:center;justify-content:center;font-weight:600;">{{ strtoupper(substr($name,0,1)) }}</div>
