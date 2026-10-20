@extends('sg.layout')
@section('title', 'قواعد الذكاء الاصطناعي')
@section('bodyClass', 'soft')
@php $nav = 'msg'; @endphp
@section('content')
<div class="crumb"><a href="{{ route('sg.messages') }}">رسائل الواتساب</a> › قواعد الذكاء الاصطناعي</div>
<div class="top"><div><h1>قواعد الذكاء الاصطناعي</h1><div class="sub">التعليمات التي يتبعها الرد الآلي على رسائل العملاء في واتساب</div></div><div class="acts"><button class="sg-burger">☰</button></div></div>
@if (session('success'))<div class="flash ok">{{ session('success') }}</div>@endif
@if (session('error'))<div class="flash err">{{ session('error') }}</div>@endif
@if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif
<div class="side-lay" style="grid-template-columns:1fr 380px;align-items:start">
    <div class="card tw">
        <form class="tw-h" method="get" action="{{ route('ai-roles.search') }}" style="display:flex;gap:8px;flex-wrap:wrap">
            <div class="sx" style="flex:1"><input name="role" value="{{ $query['role'] ?? '' }}" placeholder="اسم القاعدة…"></div>
            <div class="sx" style="flex:1"><input name="message" value="{{ $query['message'] ?? '' }}" placeholder="محتوى القاعدة…"></div>
            <button class="btn sm">بحث</button>@if (isset($query))<a class="btn ghost sm" href="{{ route('ai-roles.index') }}">مسح</a>@endif
        </form>
        @forelse ($roles as $role)
            <div style="display:flex;gap:12px;align-items:flex-start;padding:12px 16px;border-bottom:1px solid var(--line-soft)">
                <span class="chip n">#{{ $role->id }}</span>
                <div style="flex:1"><b>{{ $role->role }}</b><div style="white-space:pre-line;font-size:13.5px;color:var(--ink-2);margin-top:4px">{{ \Illuminate\Support\Str::limit($role->message, 500) }}</div><div class="note">{{ optional($role->updated_at)->diffForHumans() }}</div></div>
                <div style="display:flex;gap:6px">
                    @can('aiRoles-edit')<a class="btn ghost sm" href="{{ route('ai-roles.edit', $role->id) }}">تعديل</a>@endcan
                    @can('aiRoles-delete')<form method="post" action="{{ route('ai-roles.destroy', $role->id) }}" data-confirm="حذف القاعدة «{{ $role->role }}»؟">@csrf @method('DELETE')<button class="btn ghost sm" style="color:var(--down)">حذف</button></form>@endcan
                </div>
            </div>
        @empty
            <div class="empty"><span class="ic">🤖</span>لا توجد قواعد بعد</div>
        @endforelse
    </div>
    @can('aiRoles-create')
    <form class="card sticky" method="post" action="{{ route('ai-roles.store') }}">
        @csrf
        <h3>قاعدة جديدة</h3>
        <div class="f"><label class="l">اسم القاعدة</label><input name="role" class="inp" required value="{{ old('role') }}" placeholder="مثلاً: الرد على السؤال عن الأسعار"></div>
        <div class="f"><label class="l">التعليمات</label><textarea name="message" class="inp" rows="8" style="height:auto" required>{{ old('message') }}</textarea></div>
        <button class="btn block lg">حفظ</button>
    </form>
    @endcan
</div>
@if (method_exists($roles, 'links'))@include('sg.partials.pager', ['p' => $roles])@endif
@endsection
