<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Notif_orgs;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{

    function __construct()
    {
         // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
         $this->middleware('permission:invoice-list', ['only' => ['index','show']]);
         $this->middleware('permission:invoice-create', ['only' => ['create','store']]);
         $this->middleware('permission:invoice-edit', ['only' => ['edit','update']]);
         $this->middleware('permission:invoice-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $invoice = Invoice::find(1);
        $invoice2 = Invoice::find(2);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.invoice.index',compact('invoice','invoice2','main_notifications'));
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
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
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
        //
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
                'welcome' => 'required|string',
                'note' => 'required|string',
                'note2' => 'required|string',
                'address' => 'required|string',
                'welcome_2' => 'required|string',
                'note_2' => 'required|string',
                'note2_2' => 'required|string',
                'address_2' => 'required|string',

            ],
            [
                'welcome.required' => 'يجب إدخال رسالة الترحيب الموجودة في الفاتورة',
                'note.required' => 'يجب إدخال الملاحظة الاولي في الفاتورة',
                'note2.required' => 'يجب إدخال الملاحظة الثانية في الفاتورة',
                'address.required' => 'يجب إدخال العنوان وارقام الهواتف',

                'welcome_2.required' => 'يجب إدخال رسالة الترحيب الموجودة في فاتورة الديون',
                'note_2.required' => 'يجب إدخال الملاحظة الاولي في فاتورة الديون',
                'note2_2.required' => 'يجب إدخال الملاحظة الثانية في  فاتورة الديون',
                'address_2.required' => 'يجب إدخال العنوان وارقام الهواتف  فاتورة الديون',
            ]
        );

            DB::beginTransaction();
            try {

                $invoice = Invoice::find(1);
                $invoice->welcome = request('welcome');
                $invoice->note = request('note');
                $invoice->note2 = request('note2');
                $invoice->address = request('address');
                $invoice->update();

                $invoice2 = Invoice::find(2);
                $invoice2->welcome = request('welcome_2');
                $invoice2->note = request('note_2');
                $invoice2->note2 = request('note2_2');
                $invoice2->address = request('address_2');
                $invoice2->update();

                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "invoice";
                $log->title = " تعديل بيانات الفاتورة (".$invoice->welcome.")";
                $log->log = " تم تعديل بيانات الفاتورة (".$invoice->note.") (".$invoice->note2.") (".$invoice->address.")";
                $log->save();

            DB::commit();
            return redirect()->route('invoice.index')->with('success','تــم تـعـديـل بـيـانـات الــفــاتــورة بــنــجــاح');
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
        //
    }
}
