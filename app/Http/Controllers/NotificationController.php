<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Org;
use App\Models\Type;
use App\Models\User;
use App\Models\Report;
use App\Models\Customer;
use App\Models\Notif_orgs;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Events\SendNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\NotificationStoreRequest;

class NotificationController extends Controller
{

    function __construct()
    {
         // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
         $this->middleware('permission:notification-list', ['only' => ['index','show','download']]);
         $this->middleware('permission:notification-create', ['only' => ['create','store']]);
         $this->middleware('permission:notification-edit', ['only' => ['edit','update']]);
         $this->middleware('permission:notification-delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        $notifications = Notification::where('sender', 'user')->latest()->paginate(25);
        $orgs = User::where('id', '!=', 1)->get();
        Notif_orgs::where('user_id', auth()->user()->id)->update(['read'=>true]);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.notification.index',compact('notifications','orgs','main_notifications'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'disc' => 'required',
            'img' => 'nullable|mimes:pdf,jpeg,png,jpg',
        ],
        [
            'title.required' => 'يجب ادخال عنوان الاشعار',
            'disc.required' => 'يجب ادخال شرح بسيط عن الاشعار',
            'img.mimes' => 'يجب ان تكون صورة الاشعار من احدي الصيغ التالية pdf,jpeg,png,jpg ',
        ]);

        DB::beginTransaction();
        try {
            $arr = new Notification;
            $arr->title = request('title');
            $arr->disc = request('disc');
            $arr->date = request('date');
            $arr->sender = "user";
            $finalArray = [];

            if (request()->hasFile('img')) {
                $file = request()->file('img'); // Get the files
                $fileName = time() . '.' . $file->extension();
                $file->storeAs('NOTIFICATIONS', $fileName, 'public'); // Store the file
                $filePath = 'NOTIFICATIONS/' . $fileName; // Generate the file path
                $arr->img = $filePath;
            }

            if(request('type') == "all"){
                $arr->typeOf = "all";
            }elseif(request('type') == "some"){
                $arr->typeOf = "some";
            }else{
                $arr->typeOf = "some";
            }
            $arr->save();

            if(request('type') == "all"){
                $usersIds = User::where('id', '!=', 1)->pluck('id');
            }elseif(request('type') == "some"){
                $usersIds = User::whereIn('id', request('orgs'))->pluck('id');
            }else{
                $usersIds = User::where('id', '!=', 1)->pluck('id');
            }

            $finalArray = $usersIds->map(function($userId) use ($arr) {
                return [
                    'notification_id' => $arr->id,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();
            if (!empty($finalArray)) {
                Notif_orgs::insert($finalArray); // Insert the file references
            }

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "notification";
            $log->title = " اشعار جديد (".request('title').")";
            $log->log = " تم اضافة اشعار جديد (".request('title').")";
            $log->save();

            DB::commit();
            return redirect()->back()->with('success','تمت إضافة اشعار جديد بنجاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error','للاسف حدث خطأ ما الرجاء اعادة المحاولة');
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
        $notification = Notification::find($id);
        $orgs = User::where('id', '!=', 1)->get();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.notification.edit',compact('notification','orgs','main_notifications'));
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
        $request->validate([
            'title' => 'required',
            'disc' => 'required',
            'img' => 'nullable|mimes:pdf,jpeg,png,jpg',
        ],
        [
            'title.required' => 'يجب ادخال عنوان الاشعار',
            'disc.required' => 'يجب ادخال شرح بسيط عن الاشعار',
            'img.mimes' => 'يجب ان تكون صورة الاشعار من احدي الصيغ التالية pdf,jpeg,png,jpg ',
        ]);

        DB::beginTransaction();
        try {
            $arr = Notification::find($id);

            $arr->title = request('title');
            $arr->disc = request('disc');
            $arr->date = request('date');

            $finalArray = [];

            if (request()->hasFile('img')) {
                if($arr->img){
                    if (Storage::disk('public')->exists($arr->img)) {
                        Storage::disk('public')->delete($arr->img);
                    }
                }
                $file = request()->file('img'); // Get the files
                $fileName = time() . '.' . $file->extension();
                $file->storeAs('NOTIFICATIONS', $fileName, 'public'); // Store the file
                $filePath = 'NOTIFICATIONS/' . $fileName; // Generate the file path
                $arr->img = $filePath;
            }

            if(request('type') == "all"){
                $arr->typeOf = "all";
            }elseif(request('type') == "some"){
                $arr->typeOf = "some";
            }else{
                $arr->typeOf = "some";
            }

            $arr->update();

            Notif_orgs::where('notification_id', $arr->id)->delete();
            if(request('type') == "all"){
                $usersIds = User::where('id', '!=', 1)->pluck('id');
            }elseif(request('type') == "some"){
                $usersIds = User::whereIn('id', request('orgs'))->pluck('id');
            }else{
                $usersIds = User::where('id', '!=', 1)->pluck('id');
            }

            if(Notif_orgs::where('id', $arr->id)->whereIn('user_id', $usersIds)->exists()){
                DB::rollback();
                return redirect()->back()->with('error','تم ارسال الاشعار مسبقآ الرجاء التحقق من الاشعارات');
            }

            $finalArray = $usersIds->map(function($userId) use ($arr) {
                return [
                    'notification_id' => $arr->id,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();
            if (!empty($finalArray)) {
                Notif_orgs::insert($finalArray); // Insert the file references
            }


            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "notification";
            $log->title = " تعديل الاشعار (".request('title').")";
            $log->log = " تم تعديل بيانات الاشعار (".request('title').")";
            $log->save();

            DB::commit();
            return redirect()->route('notification.index')->with('success','تمت تعديل بيانات الاشعار بنجاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error','للاسف حدث خطأ ما الرجاء اعادة المحاولة');
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
            $notif = Notification::find($id);
            $title = $notif->title;
            $notif->delete();
            Notif_orgs::where('notification_id', $id)->delete();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "notification";
            $log->title = " حذف الاشعار (".$title.")";
            $log->log = " تم حذف بيانات الاشعار (".$title.")";
            $log->save();

            DB::commit();
            return redirect()->route('notification.index')->with('success','تمت حذف الاشعار بنجاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error','للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }
}
