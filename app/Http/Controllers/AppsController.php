<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AppsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function add(Request $request){
        $name = $request->input('name');
        $faname = $request->input('faname');
        $price = $request->input('price');

        try{
            $result = DB::insert('insert into apps (name, price , faname) values (?, ? ,?)', [$name, $price , $faname]);
            return response()->json([json_encode($result)] , 201);
        }catch(Exception $e){
            return response()->json([json_encode($e)] , 500);
        }

    }

    public function checkupdate(Request $request){
        $name = $request->input('name');

        try{
            $result = DB::table('apps')->select('apps.version' , 'apps.link')->where('name' , $name)->get()[0];
            return response()->json($result , 200);
        }catch(Exception $e){
            return response()->json([json_encode($e)] , 500);
        }

    }

    //
}
