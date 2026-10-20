<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Notif_orgs;
use App\Models\Setting;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WhatsAppSettingController extends Controller
{
    public function __construct(private WhatsAppService $whatsApp)
    {
        $this->middleware('permission:system-list|system-edit', ['only' => ['edit']]);
        $this->middleware('permission:system-edit', ['only' => ['update']]);
    }

    public function edit()
    {
        $settings = Setting::findOrFail(1);
        $mainSessionUuid = $settings->whatsappMainSessionUuid();
        $savedMain = collect($settings->whatsapp_numbers ?? [])->firstWhere('session_uuid', $mainSessionUuid);
        $sessions = [];
        $loadError = null;
        try {
            $sessions = $this->whatsApp->sessions();
        } catch (\RuntimeException $e) {
            $loadError = $e->getMessage();
        }
        $main_notifications = Notif_orgs::where('user_id', auth()->id())->latest()->take(5)->get();

        return view('admin.settings.whatsapp', compact('sessions', 'mainSessionUuid', 'savedMain',
            'loadError', 'main_notifications'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'main_session_uuid' => ['required', 'string', 'regex:/^[a-fA-F0-9]{32}$/D'],
        ]);
        try {
            // Verify ownership again when saving; the list may have changed since page load.
            $sessions = $this->whatsApp->sessions();
        } catch (\RuntimeException $e) {
            return redirect()->route('settings.whatsapp.edit')->with('error', $e->getMessage());
        }
        $chosen = collect($sessions)->firstWhere('uuid', $validated['main_session_uuid']);
        if (! $chosen) {
            throw ValidationException::withMessages([
                'main_session_uuid' => 'الرقم المختار غير موجود في حساب Whatsy. حدّث القائمة واختر رقماً متاحاً.',
            ]);
        }

        DB::transaction(function () use ($sessions, $chosen) {
            $settings = Setting::lockForUpdate()->findOrFail(1);
            $settings->update([
                'whatsapp_main_session_uuid' => $chosen['uuid'],
                'whatsapp_numbers' => array_map(fn ($session) => [
                    'name' => $session['name'], 'session_uuid' => $session['uuid'], 'status' => $session['status'],
                ], $sessions),
            ]);
            Log::create(['user_id' => auth()->id(), 'type' => 'settings',
                'title' => 'تعديل الرقم الرئيسي لواتساب',
                'log' => 'تم تحديد ('.$chosen['name'].') للرسائل التلقائية.']);
        });

        return redirect()->route('settings.whatsapp.edit')->with('success', 'تم حفظ الرقم الرئيسي لواتساب بنجاح.');
    }
}
