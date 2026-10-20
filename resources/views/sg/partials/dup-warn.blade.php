@if (session('dup_warn'))
<div style="background:#fff4e6;border:2px solid #e8590c;border-radius:14px;padding:12px 14px;margin:10px 0;font-size:15px;line-height:1.8">
    {!! session('dup_warn') !!}
    <label class="ck" style="margin-top:6px;font-weight:800;color:#c2410c"><input type="checkbox" name="confirm_dup" value="1"> سجّله على أي حال (مش مكرر)</label>
</div>
@endif
