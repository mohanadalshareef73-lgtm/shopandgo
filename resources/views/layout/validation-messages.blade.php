
@if ($errors->any())
<div class="alert alert-danger" id="hide2">
    <a href="#" onclick="hide2()" style="font-size: 20px; color: darkred;"> X </a>
    <ul style="text-align: center; color: darkred; list-style-type: none; font-size: 15px;">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

@if(session('error'))
<div style="text-align: center; color: darkred; font-size: 15px;" id="hide" class="alert alert-danger">
    {{ session('error') }}
    <a href="#" onclick="hide()" style="font-size: 20px; float: right; color: darkred;"> X </a>
</div>
@endif

@if(session('warning'))
<div style="text-align: center; color: rgb(182, 118, 0); font-size: 15px;" id="hide" class="alert alert-warning">
    {{ session('warning') }}
    <a href="#" onclick="hide()" style="font-size: 20px; float: right; color: rgb(182, 118, 0);"> X </a>
</div>
@endif

@if(session('success'))
<div style="text-align: center; font-size: 15px; color: green;" id="hide3" class="alert alert-success">
    {{ session('success') }}
    <a href="#" onclick="hide3()" style="font-size: 20px; float: right; color: darkgreen;"> X </a>
</div>
@endif
