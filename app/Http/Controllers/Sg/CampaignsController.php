<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Services\CampaignService as CS;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** حملات واتساب على دفعات */
class CampaignsController extends Controller
{
    public function __construct()
    {
        $this->middleware(fn ($r, $n) => auth()->user()->hasRole('Admin') ? $n($r) : abort(403, 'للمدير فقط'));
    }

    protected function sessions(): array
    {
        try {
            return app(\App\Services\WhatsAppService::class)->sessions();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function index()
    {
        $camps = DB::table('campaigns')->latest('id')->take(20)->get();
        $stats = DB::table('campaign_recipients')->whereIn('campaign_id', $camps->pluck('id'))
            ->selectRaw('campaign_id, segment, session_uuid, status, COUNT(*) n')->groupBy('campaign_id', 'segment', 'session_uuid', 'status')->get()->groupBy('campaign_id');

        return view('sg.campaigns.index', [
            'nav' => 'camp', 'camps' => $camps, 'stats' => $stats, 'counts' => CS::counts(), 'sessions' => $this->sessions(),
            'sentToday' => CS::sentToday(), 'limit' => CS::dailyLimit(), 'reserve' => CS::reserve(), 'main' => CS::mainSession(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'message' => 'required|string|max:1500',
            'segments' => 'required|array|min:1', 'segments.*' => 'in:active,medium,idle',
            'sessions' => 'nullable|array', 'sessions.*' => 'string|regex:/^[a-fA-F0-9]{32}$/',
            'per_day' => 'required|integer|min:10|max:450', 'from_hour' => 'required|integer|min:0|max:23', 'to_hour' => 'required|integer|min:1|max:24',
        ], ['segments.required' => 'اختار مجموعة وحدة على الأقل']);
        $id = DB::table('campaigns')->insertGetId([
            'name' => $data['name'], 'message' => $data['message'], 'status' => 'running', 'per_day' => $data['per_day'],
            'from_hour' => $data['from_hour'], 'to_hour' => max($data['from_hour'] + 1, $data['to_hour']),
            'sessions' => json_encode(array_values($data['sessions'] ?? [])), 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $n = CS::fill($id, $data['segments'], $data['sessions'] ?? []);
        $nums = max(1, count($data['sessions'] ?? []));

        return back()->with('success', 'بدت الحملة ✓ ' . $n . ' عميل · تقريباً ' . (int) ceil($n / ($data['per_day'] * $nums)) . ' يوم');
    }

    public function toggle(int $id)
    {
        $c = DB::table('campaigns')->find($id);
        abort_unless($c, 404);
        DB::table('campaigns')->where('id', $id)->update(['status' => $c->status === 'running' ? 'paused' : 'running', 'updated_at' => now()]);

        return back()->with('success', $c->status === 'running' ? 'وقفت الحملة مؤقتاً' : 'رجعت الحملة تشتغل');
    }

    public function destroy(int $id)
    {
        DB::table('campaign_recipients')->where('campaign_id', $id)->delete();
        DB::table('campaigns')->where('id', $id)->delete();

        return back()->with('success', 'انحذفت الحملة');
    }
}
