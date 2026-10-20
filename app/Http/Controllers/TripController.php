<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Log;
use App\Models\Trip;
use App\Models\Order;
use App\Models\Status;
use App\Models\Customer;
use App\Models\Notif_orgs;
use Illuminate\Http\Request;
use App\Models\whatsappMessage;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use App\Jobs\SendWhatsAppMessageJob;
use Illuminate\Support\Facades\Storage;

class TripController extends Controller
{
    function __construct()
    {
        // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
        $this->middleware('permission:trip-list', ['only' => ['index', 'show', 'download']]);
        $this->middleware('permission:trip-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:trip-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:trip-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $trips = Trip::latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        $page = "all";
        return view('admin.trip.index', compact('trips', 'main_notifications', 'page'));
    }
    public function updateTrip(Request $request)
    {
        $request->validate(
            [
                'trip_number'   => 'required|array|min:1',
                'trip_number.*' => 'required|string|max:255',
                'trip_status'   => 'required|string|max:255',
                'trip_note'     => 'nullable|string',
            ],
            [
                'trip_number.required'   => 'يجب اضافة رقم الرحلة',
                'trip_number.*.required' => 'يجب اضافة رقم الرحلة',
                'trip_status.required'  => 'يجب اضافة حالة الرحلة',
                'trip_note.string'      => 'يجب ان تكون ملاحظة الرحلة نص',
            ]
        );

        try {
            DB::beginTransaction();

            $tripsNum = count($request->trip_number);
            $tripupdated = 0;
            $failedList = [];
            $ordersSent = 0;

            // Keep track of WhatsApp failures
            $whatsappFailedList = [];

            $orders = Order::query();

            foreach ($request->trip_number as $tripNum) {

                if ($request->trip_note) {

                    Order::where('trip_details', $tripNum)
                        ->update([
                            'trip_status' => $request->trip_status,
                            'trip_note'   => $request->trip_note,
                        ]);
                } else {

                    Order::where('trip_details', $tripNum)
                        ->update([
                            'trip_status' => $request->trip_status,
                        ]);
                }

                if (Order::where('trip_details', $tripNum)->count() > 0) {

                    $tripupdated++;

                    $orders->orWhere('trip_details', $tripNum);
                } else {

                    $failedList[] = $tripNum;
                }
            }

            $orders = $orders->get();

            $messageChange1 = whatsappMessage::where('type', 'change')->first();
            $messageReady1 = whatsappMessage::where('type', 'ready')->first();

            DB::commit();

            if ($tripupdated == 0) {
                return back()->with(
                    'error',
                    'لم يتم تعديل بيانات اي رحلة، الرجاء التحقق من ارقام الرحلات المدخلة'
                );
            }

            /*
        |--------------------------------------------------------------------------
        | Send WhatsApp Messages
        |--------------------------------------------------------------------------
        */

            foreach ($orders as $order) {

                try {

                    /*
                |--------------------------------------------------------------------------
                | Calculate order information
                |--------------------------------------------------------------------------
                */

                    $total = $order->total_price
                        + $order->ship_price
                        - $order->paid_price;

                    $shipPrice = $order->ship_price == 0
                        ? 'لم تحدد بعد'
                        : $order->ship_price;


                    /*
                |--------------------------------------------------------------------------
                | Build WhatsApp Message
                |--------------------------------------------------------------------------
                */

                    if ($order->trip_status == "جاهز للاستلام") {

                        $message = str_replace(
                            [
                                '{{$order1->customer->name}}',
                                '{{$order1->customer->code}}',
                                '{{$order1->trip_status}}',
                                '{{$shipPrice}}',
                                '{{$total}}',
                                '{{$currency}}',
                                '{{$order1->code}}',
                                '{{$order1->trip_details}}',
                                '{{$app_url}}'
                            ],
                            [
                                $order->customer->name,
                                $order->customer->code,
                                $order->trip_status,
                                $shipPrice,
                                $total,
                                $order->currency_sign,
                                $order->code,
                                $order->trip_details,
                                env('APP_URL')
                            ],
                            $messageReady1->message
                        );
                    } else {

                        $message = str_replace(
                            [
                                '{{$order1->customer->name}}',
                                '{{$order1->customer->code}}',
                                '{{$order1->trip_status}}',
                                '{{$order1->code}}',
                                '{{$order1->trip_details}}',
                                '{{$app_url}}'
                            ],
                            [
                                $order->customer->name,
                                $order->customer->code,
                                $order->trip_status,
                                $order->code,
                                $order->trip_details,
                                env('APP_URL')
                            ],
                            $messageChange1->message
                        );
                    }


                    /*
                |--------------------------------------------------------------------------
                | Get Customer Phone
                |--------------------------------------------------------------------------
                */

                    if (!$order->customer || !$order->customer->phone_1) {

                        $whatsappFailedList[] = [
                            'order' => $order->code,
                            'phone' => 'غير موجود',
                            'reason' => 'رقم الهاتف غير موجود'
                        ];

                        continue;
                    }

                    $originalPhone = $order->customer->phone_1;


                    /*
                |--------------------------------------------------------------------------
                | Normalize Phone Number
                |--------------------------------------------------------------------------
                */

                    // Remove everything except numbers
                    $phone = preg_replace('/\D/', '', $originalPhone);

                    // Handle numbers starting with 00
                    if (str_starts_with($phone, '00')) {
                        $phone = substr($phone, 2);
                    }

                    // Handle Libyan local numbers starting with 0
                    if (str_starts_with($phone, '0')) {
                        $phone = '218' . substr($phone, 1);
                    }

                    // If number doesn't start with 218, add it
                    if (!str_starts_with($phone, '218')) {
                        $phone = '218' . $phone;
                    }


                    /*
                |--------------------------------------------------------------------------
                | Validate Phone Number Before Sending
                |--------------------------------------------------------------------------
                */

                    // Libyan international number should be exactly 12 digits:
                    // 218 + 9 digits
                    if (!preg_match('/^218\d{9}$/', $phone)) {

                        $whatsappFailedList[] = [
                            'order' => $order->code,
                            'phone' => $originalPhone,
                            'reason' => 'رقم الهاتف غير صحيح: ' . $phone
                        ];

                        continue;
                    }


                    /*
                |--------------------------------------------------------------------------
                | Send WhatsApp
                |--------------------------------------------------------------------------
                */

                    $response = app(WhatsAppService::class)->send([
                        'phone' => [$phone],
                        'message' => $message,
                    ], attempts: 3);


                    /*
                |--------------------------------------------------------------------------
                | Check WhatsApp Response
                |--------------------------------------------------------------------------
                */

                    if ($response->successful()) {

                        $ordersSent++;
                    } else {

                        // IMPORTANT:
                        // Do NOT stop the loop if WhatsApp returns 400/404/500/etc.

                        $errorMessage = $response->json('error')
                            ?? $response->body();

                        $whatsappFailedList[] = [
                            'order' => $order->code,
                            'phone' => $phone,
                            'reason' => $errorMessage
                        ];

                        // Skip this customer and continue to next order
                        continue;
                    }
                } catch (\Throwable $e) {

                    /*
                |--------------------------------------------------------------------------
                | IMPORTANT
                |--------------------------------------------------------------------------
                | If WhatsApp request throws an exception (timeout,
                | connection error, etc.), don't stop the entire process.
                */

                    $whatsappFailedList[] = [
                        'order' => $order->code,
                        'phone' => $order->customer->phone_1 ?? 'غير موجود',
                        'reason' => $e->getMessage()
                    ];

                    // Continue with the next customer
                    continue;
                }
            }


            /*
        |--------------------------------------------------------------------------
        | Return Result
        |--------------------------------------------------------------------------
        */

            if ($tripupdated < $tripsNum) {

                $failedStr = implode(', ', $failedList);

                $message =
                    'تم تعديل بيانات '
                    . $tripupdated
                    . ' رحلة بنجاح، ولكن لم يتم تعديل بيانات الرحلات التالية لعدم وجودها: '
                    . $failedStr;

                if (count($whatsappFailedList) > 0) {
                    $message .= ' مع وجود بعض أرقام واتساب غير صالحة أو فشل إرسالها.';
                }

                return back()->with('warning', $message);
            }


            if (count($whatsappFailedList) > 0) {

                $failedPhones = collect($whatsappFailedList)
                    ->map(function ($item) {
                        return $item['phone'];
                    })
                    ->implode(', ');

                return back()->with(
                    'warning',
                    'تم تعديل جميع الرحلات بنجاح ✅ وتم إرسال '
                        . $ordersSent
                        . ' رسالة واتساب، ولكن تعذر إرسال بعض الرسائل للأرقام التالية: '
                        . $failedPhones
                );
            }


            return back()->with(
                'success',
                'تم تعديل بيانات كل الرحلات المدخلة بنجاح ✅ وتم إرسال رسائل واتساب للزبائن'
            );
        } catch (\Throwable $e) {

            DB::rollBack();

            return back()->with(
                'error',
                'حصل خطأ أثناء الحفظ: ' . $e->getMessage()
            );
        }
    }

    public function onway()
    {
        $trips = Trip::where([['out_date', '<', now()], ['arrive_date', '>', now()]])->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        $page = "onway";
        return view('admin.trip.index', compact('trips', 'main_notifications', 'page'));
    }

    public function delay()
    {
        $trips = Trip::where([['arrive_date', '<', Carbon::now()], ['arrived', false]])->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        $page = "delay";
        return view('admin.trip.index', compact('trips', 'main_notifications', 'page'));
    }

    public function arrived()
    {
        $trips = Trip::where('arrived', true)->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        $page = "arrived";
        return view('admin.trip.index', compact('trips', 'main_notifications', 'page'));
    }

    public function tripArrived($id)
    {
        DB::beginTransaction();
        try {
            $trip = Trip::find($id);
            Order::where('trip_id', $id)->update(['arrived' => true, 'arrived_at' => now()->format('Y-m-d')]);
            $trip->arrived = true;
            $trip->arrived_at = now()->format('Y-m-d');
            $trip->save();
            DB::commit();
            return redirect()->back()->with('success', 'تــم وصـــول الــرحــلــة بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
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

    public function search(Request $request)
    {
        if (!empty($request)) {
            $query = $request->all();
        }
        if (!request('code') && !request('country') && !request('out_date') && !request('arrive_date')) {
            return redirect()->route('customer.index')->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
        }
        $trips = Trip::query(); // Start a new query
        if (request('code') != null) {
            $trips->orWhere('code', 'like', '%' . request('code') . '%');
        }
        if (request('country') != null) {
            $trips->orWhere('country', 'like', '%' . request('country') . '%');
        }
        if (request('out_date') != null) {
            $trips->orWhere('out_date', 'like', '%' . request('out_date') . '%');
        }
        if (request('arrive_date') != null) {
            $trips->orWhere('arrive_date', 'like', '%' . request('arrive_date') . '%');
        }

        if (request('page') != null && request('page') == "onway") {
            $trips->where('out_date', '<', now());
            $trips->where('arrive_date', '>', now());
        } elseif (request('page') != null && request('page') == "arrived") {
            $trips->where('arrive_date', '<', now());
            $trips->where('arrived', true);
        }

        $trips = $trips->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        return view('admin.trip.index', compact('trips', 'query', 'main_notifications'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        request()->validate(
            [
                'country'  => "required|string",
                'out_date'  => "required|string",
                'arrive_date'  => "required|string",
                'code' => 'required|string|unique:trips',
                'doc' => 'nullable|mimes:pdf,jpeg,png,jpg',
            ],
            [
                'country.required' => 'يجب إدخال اسم البلد القادم منه الرحلة',
                'out_date.required' => 'يجب إدخال تاريخ مغادرة الرحلة',
                'arrive_date.required' => 'يجب إدخال التاريخ المتوقع لوصول الرحلة',
                'code.required' => 'يجب اضافة كود الرحلة',
                'code.unique' => 'كود الرحلة الذي قمت بإدخاله موجود',
                'doc.mimes' => 'يجب ان تكون صورة الاشـعار من احدي الصيغ التالية pdf,jpeg,png,jpg ',
            ]
        );

        DB::beginTransaction();
        try {
            $trip = new Trip;
            $trip->code = request('code');
            $trip->country = request('country');
            $trip->out_date = request('out_date');
            $trip->arrive_date = request('arrive_date');
            $trip->note = request('note');

            if (request()->hasFile('doc')) {
                $file = request()->file('doc'); // Get the files
                $fileName = time() . '.' . $file->extension();
                $file->storeAs('TRIP_DOCS', $fileName, 'public'); // Store the file
                $filePath = 'TRIP_DOCS/' . $fileName; // Generate the file path
                $trip->doc = $filePath;
            }

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "trip";
            $log->title = " رحلة جديدة (" . request('code') . ")";
            $log->log = " تم اضافة رحلة جديدة (" . request('code') . ")";
            $log->save();

            $trip->save();
            DB::commit();
            return redirect()->back()->with('success', 'تــمــت إضــافــة الــرحــلــة بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $trip = Trip::find($id);
        $orders = Order::where('trip_id', $id)->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        $status = Status::get();

        return view('admin.trip.show', compact('trip', 'orders', 'main_notifications', 'status'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $trip = Trip::find($id);
        $orders = Order::where('trip_id', null)
            ->orWhere('trip_id', $trip->id)->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        return view('admin.trip.edit', compact('trip', 'orders', 'main_notifications'));
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
                'country'  => "required|string",
                'out_date'  => "required|string",
                'arrive_date'  => "required|string",
                'code' => 'required|string',
                'doc' => 'nullable|mimes:pdf,jpeg,png,jpg',
            ],
            [
                'country.required' => 'يجب إدخال اسم البلد القادم منه الرحلة',
                'out_date.required' => 'يجب إدخال تاريخ مغادرة الرحلة',
                'arrive_date.required' => 'يجب إدخال التاريخ المتوقع لوصول الرحلة',
                'code.required' => 'يجب اضافة كود الرحلة',
                'doc.mimes' => 'يجب ان تكون صورة الاشـعار من احدي الصيغ التالية pdf,jpeg,png,jpg ',
            ]
        );

        DB::beginTransaction();
        try {
            $trip = Trip::find($id);

            $orderIds = explode(',', $request->input('checked_order_ids'));
            Order::where('trip_id', $trip->id)->update(['trip_id' => null]);

            if ($trip->arrived) {
                Order::where('trip_id', $trip->id)->update(['trip_id' => $trip->id, 'arrived' => true, 'arrived_at' => $trip->arrived_at]);
            } else {
                Order::whereIn('id', $orderIds)->update(['trip_id' => $trip->id]);
            }

            if (request('code') != $trip->code && Trip::where('code', request('code'))->exists()) {
                DB::rollback();
                return redirect()->back()->with('error', 'كود الرحلة الذي قمت بإدخاله موجود لدي رحلة اخري');
            }

            $trip->code = request('code');
            $trip->country = request('country');
            $trip->out_date = request('out_date');
            $trip->arrive_date = request('arrive_date');
            $trip->note = request('note');
            if (request()->hasFile('doc')) {
                if ($trip->doc) {
                    if (Storage::disk('public')->exists($trip->doc)) {
                        Storage::disk('public')->delete($trip->doc);
                    }
                }
                $file = request()->file('doc'); // Get the files
                $fileName = time() . '.' . $file->extension();
                $file->storeAs('TRIP_DOCS', $fileName, 'public'); // Store the file
                $filePath = 'TRIP_DOCS/' . $fileName; // Generate the file path
                $trip->doc = $filePath;
            }

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "trip";
            $log->title = " تعديل الرحلة (" . request('code') . ")";
            $log->log = " تم تعديل بيانات الرحلة (" . request('code') . ")";
            $log->save();

            $trip->update();
            DB::commit();
            return redirect()->route('trip.index')->with('success', 'تــم تـعـديـل بـيـانـات الـرحـلـة بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    public function download2($id)
    {
        $arr = Trip::find($id);
        if ($arr && $arr->doc) {
            $originalExtension = pathinfo($arr->doc, PATHINFO_EXTENSION);
            $newFileName = $arr->code . '-' . $arr->out_date . '.' . $originalExtension;
            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "trip";
            $log->title = " تحميل وثيقة الرحلة (" . $arr->code . ")";
            $log->log = " تم تحميل وثيقة الرحلة  (" . $arr->country . ") من المستخدم (" . auth()->user()->name . ") ";
            $log->save();
            return response()->download(public_path('storage/' . $arr->doc), $newFileName);
        } else {
            return redirect()->back()->with('error', 'عذرا لايوجد وثيقة لهذه الرحلة');
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
        DB::beginTransaction();
        try {
            $trip = Trip::find($id);
            if (!$trip) {
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }
            if ($trip->orders->count() > 0) {
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف لايمكن حذف الرحلة وذلك لوجد طلبات داخل هذه الرحلة');
            }
            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "trip";
            $log->title = " حذف الرحلة (" . $trip->code . ") DYL";
            $log->log = " تم حذف الرحلة بتاريخ (" . now()->format('Y-m-d') . ") القادمة من " . $trip->country . " ";
            $log->save();
            $trip->delete();
            DB::commit();
            return redirect()->route('trip.index')->with('success', 'تــم حــذف الــرحـلــة بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }
}
