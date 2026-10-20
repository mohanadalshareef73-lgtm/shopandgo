<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Notif_orgs;
use Illuminate\Http\Request;
use App\Models\whatsappMessage;
use Illuminate\Support\Facades\DB;

class WhatsappMessageController extends Controller
{


    function __construct()
    {
        // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
        $this->middleware('permission:status-list', ['only' => ['index', 'show', 'download']]);
        $this->middleware('permission:status-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:status-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:status-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $messages = whatsappMessage::all();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.whatsapp-message.index', compact('messages','main_notifications'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $message = whatsappMessage::find($id);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.whatsapp-message.edit', compact('message','main_notifications'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        request()->validate(
            [
                'message'  => "required|string",
            ],
            [
                'message.required' => 'يجب إدخال محتوي الرسالة',
            ]
        );

        DB::beginTransaction();
        try {
            $message = whatsappMessage::find($id);

            $message->message = request('message');
            $message->update();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "whatsapp-message";
            $log->title = " تعديل بيانات الرسالة (" . $message->title . ")";
            $log->log = " تم تعديل بيانات حالة الرحلة (" . $message->title . ")";
            $log->save();

            DB::commit();
            return redirect()->route('whatsapp-message.index')->with('success', 'تــم تـعـديـل بـيـانـات الــرســالــة بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

    }

}
