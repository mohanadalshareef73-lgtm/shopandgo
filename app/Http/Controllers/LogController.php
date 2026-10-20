<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\User;
use App\Models\Notif_orgs;
use Illuminate\Http\Request;

class LogController extends Controller
{

    function __construct()
    {
         // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
         $this->middleware('permission:log-list', ['only' => ['index','create']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $logs = Log::latest()->paginate(25);
        $orgs = User::where('id', '!=', 1)->latest()->get();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.log.index',compact('logs','orgs','main_notifications'));
    }



    public function create(Request $request)
    {
        $query = $request->all();

        if (!$request->filled('startDate') && !$request->filled('endDate') && !$request->filled('org_id') && !$request->filled('type')) {
            return redirect()->route('safe.index')->with('error', 'الرجاء تعبئة إحدى الخانات على الأقل');
        }

        $logs = Log::query();

        if ($request->filled('startDate') && !$request->filled('endDate')) {
            $logs->whereDate('created_at', '>=', $request->input('startDate'));
        } elseif ($request->filled('startDate') && $request->filled('endDate')) {
            $logs->whereBetween('created_at', [$request->input('startDate'), $request->input('endDate')]);
        } elseif (!$request->filled('startDate') && $request->filled('endDate')) {
            $logs->whereDate('created_at', '<=', $request->input('endDate'));
        }
        if ($request->filled('org_id')) {
            $logs->where('user_id', $request->input('org_id'));
        }
        if ($request->filled('type')) {
            $logs->where('type', $request->input('type'));
        }

        $logs = $logs->latest()->paginate(25);

        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
            $orgs = User::where('id', '!=', 1)->latest()->get();
            return view('admin.log.index',compact('query','logs','orgs','main_notifications'));
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
        //
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
