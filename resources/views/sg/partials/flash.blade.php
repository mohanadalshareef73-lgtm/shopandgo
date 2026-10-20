@if (session('success'))
    <div class="flash ok">✓ {{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="flash err">⚠ {{ session('error') }}</div>
@endif
@if (session('warning'))
    <div class="flash err" style="background:var(--warn-bg);color:#7a5300;border-color:#f3dfb2">⚠ {{ session('warning') }}</div>
@endif
@if ($errors->any())
    <div class="flash err"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
