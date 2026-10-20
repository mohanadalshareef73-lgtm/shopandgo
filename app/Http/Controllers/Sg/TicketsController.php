<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\MessageService;
use Illuminate\Http\Request;

/** بلاغات العملاء (عندي مشكلة) والرد عليها */
class TicketsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:ticket-list');
    }

    public function index(Request $request)
    {
        $status = in_array($request->status, ['open', 'answered', 'closed', 'all']) ? $request->status : 'open';
        $q = SupportTicket::with(['customer', 'order', 'replier'])->latest();
        if ($status !== 'all') {
            $q->where('status', $status);
        }

        return view('sg.tickets.index', [
            'nav' => 'tick', 'status' => $status, 'rows' => $q->paginate(30)->withQueryString(),
            'counts' => SupportTicket::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate(['reply' => 'required|string|max:1000', 'close' => 'nullable|boolean', 'send_whatsapp' => 'nullable|boolean'], ['reply.required' => 'اكتب الرد']);
        $ticket->reply = $data['reply'];
        $ticket->replied_by = auth()->id();
        $ticket->replied_at = now();
        $ticket->status = $request->boolean('close') ? 'closed' : 'answered';
        $ticket->save();
        // البلاغات المكررة (نفس العميل ونفس المشكلة) تاخذ نفس الرد وتتقفل معاه
        SupportTicket::where('id', '!=', $ticket->id)->where('customer_id', $ticket->customer_id)->where('type', $ticket->type)
            ->where('order_id', $ticket->order_id)->where('status', 'open')
            ->update(['reply' => $ticket->reply, 'replied_by' => $ticket->replied_by, 'replied_at' => $ticket->replied_at, 'status' => $ticket->status]);
        \Illuminate\Support\Facades\Cache::forget('sg.badge.tickets');
        if ($request->boolean('send_whatsapp') && $ticket->customer) {
            MessageService::sendTemplate('ticket_reply', $ticket->customer, MessageService::customerVars($ticket->customer, ['{الرد}' => $data['reply']]));
        }

        return back()->with('success', 'تم إرسال الرد للعميل');
    }

    /** مسح بلاغ من عندنا بس (العميل ما يوصله حتى إشعار) */
    public function destroy(SupportTicket $ticket)
    {
        $ticket->delete();
        \Illuminate\Support\Facades\Cache::forget('sg.badge.tickets');

        return back()->with('success', 'تمسح البلاغ');
    }
}
