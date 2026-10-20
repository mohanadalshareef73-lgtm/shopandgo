<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Models\Notif_orgs;
use Illuminate\Support\Facades\DB;

class PaymentMethodController extends Controller
{

    function __construct()
    {
        $this->middleware('permission:payment-methods-list', ['only' => ['index', 'search']]);
        $this->middleware('permission:payment-methods-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:payment-methods-edit', ['only' => ['edit', 'show', 'update']]);
        $this->middleware('permission:payment-methods-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $payments = PaymentMethod::latest()->paginate(25);
                $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.payment-methods.index', compact('payments', 'main_notifications'));
    }

    public function search(Request $request)
    {
        if (!empty($request)) {
            $query = $request->all();
        }
        if (!request('search')) {
            return redirect()->back()->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
        }
        $payments = PaymentMethod::query(); // Start a new query
        if ($request->filled('search')) {
            $search = $request->search;
            $payments->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }
        $payments = $payments->latest()->paginate(25);
                        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.payment-methods.index', compact('payments', 'query', 'main_notifications'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
                        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.payment-methods.create', compact('main_notifications'));
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        request()->validate(
            [
                'name'  => "required|string|unique:payment_methods",
            ],
            [
                'name.required' => 'يجب إدخال اسم طريقة الدفع',
                'name.unique' => 'اسم طريقة الدفع الذي قمت بإدخاله موجود',
            ]
        );

        DB::beginTransaction();
        try {

            $payment = new PaymentMethod;
            $payment->name = request('name');
            $payment->notes = request('notes');
            if (request('status')) {
                $payment->status = true;
            } else {
                $payment->status = false;
            }
            $payment->save();

            Log::create([
                'type' => 'payment-methods',
                'title' => ' تم اضافة طريقة الدفع جديدة (' . $payment->name . ')',
                'log' => 'تم اضافة طريقة الدفع بنسبة  (' . $payment->rate . ')',
                'user_id' => auth()->user()->id,
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'تــمــت إضــافــة طـريـقـة الـدفـع إضـافـيـة بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            report($e); // كان dd($e) يطلع كود للمستخدم ويمنع الرجوع
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $payment  = PaymentMethod::find($id);
        if ($payment->status) {
            $payment->status = false;
            $payment->update();
            Log::create([
                'type' => 'payment-methods',
                'title' => ' الغاء تفعيل طريقة الدفع (' . $payment->name . ')',
                'log' => 'تم الغاء تفعيل طريقة الدفع بنسبة  (' . $payment->rate . ')',
                'user_id' => auth()->user()->id,
            ]);

            return redirect()->route('payment-methods.index')->with('success', 'تم الغاء تفعيل طريقة الدفع  (' . $payment->name . ')');
        } else {
            $payment->status = true;
            $payment->update();
            Log::create([
                'type' => 'payment-methods',
                'title' => ' تم تفعيل طريقة الدفع (' . $payment->name . ')',
                'log' => 'تم تفعيل طريقة الدفع بنسبة (' . $payment->rate . ')',
                'user_id' => auth()->user()->id,
            ]);
            return redirect()->route('payment-methods.index')->with('success', 'تم تفعيل طريقة الدفع  (' . $payment->name . ')');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $payment = PaymentMethod::find($id);
                        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.payment-methods.edit', compact('payment', 'main_notifications'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $payment = PaymentMethod::findOrFail($id);
        request()->validate(
            [
                'name' => 'required|string|unique:payment_methods,name,' . $payment->id,
            ],
            [
                'name.required' => 'يجب إدخال اسم طريقة الدفع',
                'name.unique' => 'اسم طريقة الدفع الذي قمت بإدخاله موجود',
            ]
        );

        DB::beginTransaction();
        try {

            $oldPrice = $payment->rate;
            $oldName = $payment->name;
            $payment->name = request('name');
            $payment->notes = request('notes');
            if (request('status')) {
                $payment->status = true;
            } else {
                $payment->status = false;
            }
            $payment->save();
            Log::create([
                'type' => 'payment-methods',
                'title' => ' تم تعديل طريقة الدفع من  (' . $oldName . ') الي (' . $payment->name . ')',
                'log' => 'تم تعديل طريقة الدفع من نسبة  (' . $oldPrice . ') الي (' . $payment->rate . ')',
                'user_id' => auth()->user()->id,
            ]);

            DB::commit();
            return redirect()->route('payment-methods.index')->with('success', 'تــمــت تـعـديـل طـريـقـة الـدفـع بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            report($e); // كان dd($e) يطلع كود للمستخدم ويمنع الرجوع
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {

            $payment = PaymentMethod::find($id);
            if ($payment->transactions->count() > 0) {
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف لا يمكن حذف هذه طريقة الدفع وذلك لوجود تحويلات متعلقة بها');
            }
            Log::create([
                'type' => 'payment-methods',
                'title' => ' تم حذف طريقة الدفع (' . $payment->name . ')',
                'log' => 'تم حذف طريقة الدفع بنسبة (' . $payment->rate . ')',
                'user_id' => auth()->user()->id,
            ]);
            $payment->delete();

            DB::commit();
            return redirect()->back()->with('success', 'تــمــت حــذفــ طـريـقـة الـدفـع بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            report($e); // كان dd($e) يطلع كود للمستخدم ويمنع الرجوع
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }
}
