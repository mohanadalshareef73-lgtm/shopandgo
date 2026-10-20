@extends('sg.layout')
@section('title', 'تعديل قاعدة')
@section('bodyClass', 'soft')
@php $nav = 'msg'; @endphp
@section('content')
<div class="crumb"><a href="{{ route('ai-roles.index') }}">قواعد الذكاء الاصطناعي</a> › تعديل</div>
<div class="top"><div><h1>تعديل قاعدة</h1></div><div class="acts"><a class="btn ghost" href="{{ route('ai-roles.index') }}">رجوع</a><button class="sg-burger">☰</button></div></div>
@if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif
@if (session('error'))<div class="flash err">{{ session('error') }}</div>@endif
<form class="card" method="post" action="{{ route('ai-roles.update', $role->id) }}" style="max-width:760px">
    @csrf @method('PUT')
    <div class="f"><label class="l">اسم القاعدة</label><input name="role" class="inp" required value="{{ old('role', $role->role) }}"></div>
    <div class="f"><label class="l">التعليمات</label><textarea name="message" class="inp" rows="12" style="height:auto" required>{{ old('message', $role->message) }}</textarea></div>
    <button class="btn lg">حفظ التعديلات</button>
</form>
@endsection
