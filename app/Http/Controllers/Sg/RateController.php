<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Log;
use App\Models\Setting;
use App\Services\AppSettings;
use Illuminate\Http\Request;

/** سعر صرف الدولار اللي تنحسب بيه السلات — للمدير بس */
class RateController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Admin');
    }

    public function update(Request $request)
    {
        $data = $request->validate(['rate' => ['required', 'numeric', 'min:0.1', 'max:100']], ['rate.required' => 'اكتب سعر الصرف']);
        $s = Setting::find(1);
        abort_unless($s, 404);
        $old = $s->usd_to_lyd_rate;
        $s->usd_to_lyd_rate = (string) round((float) $data['rate'], 6);
        $s->save();
        AppSettings::put(['rate_updated_at' => now()->toIso8601String()]);
        Log::create(['user_id' => auth()->id(), 'type' => 'setting', 'title' => ' سعر صرف السلات', 'log' => ' من ' . (float) $old . ' إلى ' . $data['rate']]);

        return back()->with('success', 'سعر الصرف توا ' . $data['rate'] . ' د.ل — السلات الجديدة تنحسب بيه');
    }

    public function touch()
    {
        AppSettings::put(['rate_updated_at' => now()->toIso8601String()]);

        return back()->with('success', 'تمام، السعر نفسه صحيح ✓');
    }
}
