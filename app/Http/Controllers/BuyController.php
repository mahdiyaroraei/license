<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redirect;
use SoapClient;
use App\Utility;

class BuyController extends Controller
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

    public function buy(Request $request)
    {
        $email = $request->input('email');
        $app = $request->input('app');

        $result = DB::select('select id from users where email = ?', [$email]);
        if (count($result) == 0) {
            $userId = DB::insert('insert into users (email) values (?)', [$email]);
        } else {
            $userId = $result[0]->id;
        }

        $appId = DB::table('apps')
            ->select('id')
            ->where('name', $app)
            ->get()[0]->id;

        $licenseId = DB::table('licenses')->insertGetId([
            'app_id' => $appId,
            'user_id' => $userId,
            'status' => 0
        ]);

        return response()->json(['license_id' => $licenseId], 200);
    }

    public function buy_v2(Request $request)
    {
        $email = $request->input('email');
        $app = $request->input('app');
        $discount = $request->input('discount');

        $result = DB::table('users')->select('*')->where('users.email', $email)->get();
        if (count($result) > 0) {
            $userId = $result[0]->id;
        } else {
            $userId = DB::table('users')->insertGetId([
                'email' => $email
            ]);
        }

        $appId = DB::table('apps')
            ->select('id')
            ->where('name', $app)
            ->get()[0]->id;

        $licenseId = DB::table('licenses')->insertGetId([
            'app_id' => $appId,
            'user_id' => $userId,
            'app_type' => 'subscribe',
            'status' => -1
        ]);

        if (strpos($discount, '@') > 0) {
            $user = DB::table('licenses')
                ->join('users', 'licenses.user_id', '=', 'users.id')
                ->join('apps', 'licenses.app_id', '=', 'apps.id')
                ->select('licenses.code', 'users.email')
                ->where('users.email', $discount)
                ->where('licenses.status', 1)
                ->orderBy('licenses.id', 'DESC')
                ->get();

            if (count($user) > 0) {
                if ($user[0]->code != null) {
                    $discountAmount = 1000;
                } else {
                    return response()->json(['license_id' => 0, 'msg' => 'کاربری با این ایمیل خریدی انجام نداده است'], 200);
                }
            } else {
                return response()->json(['license_id' => 0, 'msg' => 'کاربری با این ایمیل خریدی انجام نداده است'], 200);
            }
        } else {
            $discounts = DB::table('discount')
                ->select('discount')
                ->where('code', $discount)
                ->get();

            if (count($discounts) > 0) {
                $discountAmount = $discounts[0]->discount;
            } else if ($discount != null) {
                return response()->json(['license_id' => 0, 'msg' => 'کد تخفیف معتبر نیست.'], 200);
            }
        }

        return response()->json(['license_id' => $licenseId], 200);
    }

    public function paying($license_id, $discount, $subscribe = "")
    {
        $discountAmount = 0;

        // check discount in table or is valid user

        if (strpos($discount, '@') !== false) {
            $user = DB::table('licenses')
                ->join('users', 'licenses.user_id', '=', 'users.id')
                ->join('apps', 'licenses.app_id', '=', 'apps.id')
                ->select('licenses.code', 'users.email')
                ->where('users.email', $discount)
                ->where('licenses.status', 1)
                ->orderBy('licenses.id', 'DESC')
                ->get();

            if (count($user) > 0) {
                if ($user[0]->code != null) {
                    $discountAmount = 1000;
                }
            }
        } else {
            $discounts = DB::table('discount')
                ->select('discount')
                ->where('code', $discount)
                ->get();

            if (count($discounts) > 0) {
                $discountAmount = $discounts[0]->discount;
            }
        }

        $license = DB::table('licenses')
            ->join('users', 'licenses.user_id', '=', 'users.id')
            ->join('apps', 'licenses.app_id', '=', 'apps.id')
            ->select('licenses.id', 'apps.price', 'apps.fa_name', 'users.email')
            ->where('licenses.id', $license_id)
            ->get()[0];

//        Utility::sendPushToMe("license_id: ".$license->id." in ZarinGate ".$license->price);

        $MerchantID = '4ecb173c-2737-11e7-92b7-005056a205be';  //Required
        $Amount = $license->price - $discountAmount; //Amount will be based on Toman  - Required
        $Description = $license->fa_name;  // Required
        $Email = $license->email; // Optional
        $Mobile = '09123456789'; // Optional
        if ($discount != null) {
            $CallbackURL = 'http://periodtracker.ir/license/public/payed/' . $license->id . '/' . $discount;  // Required
        } else {
            $CallbackURL = 'http://periodtracker.ir/license/public/payed/' . $license->id;  // Required
        }

        if ($subscribe == '1-month') {
            $Amount = 8000; //Amount will be based on Toman  - Required
            $Description = 'اشتراک ۱ ماهه رویان';  // Required
            $CallbackURL = 'http://periodtracker.ir/license/public/payed/' . $license->id . '/1-month-sub';  // Required
        } else if ($subscribe == '3-month') {
            $Amount = 18000; //Amount will be based on Toman  - Required
            $Description = 'اشتراک ۳ ماهه رویان';  // Required
            $CallbackURL = 'http://periodtracker.ir/license/public/payed/' . $license->id . '/3-month-sub';  // Required
        } else if ($subscribe == '12-month') {
            $Amount = 68000; //Amount will be based on Toman  - Required
            $Description = 'اشتراک ۱۲ ماهه رویان';  // Required
            $CallbackURL = 'http://periodtracker.ir/license/public/payed/' . $license->id . '/12-month-sub';  // Required
        }

        // URL also can be ir.zarinpal.com or de.zarinpal.com
        $client = new \SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);

        $result = $client->PaymentRequest([
            'MerchantID' => $MerchantID,
            'Amount' => $Amount,
            'Description' => $Description,
            'Email' => $Email,
            'Mobile' => $Mobile,
            'CallbackURL' => $CallbackURL,
        ]);

        //Redirect to URL You can do it also by creating a form
        if ($result->Status == 100) {
            return Header('Location: https://www.zarinpal.com/pg/StartPay/' . $result->Authority . '/ZarinGate');
//             return header('Location: https://www.zarinpal.com/pg/StartPay/'.$result->Authority);
        } else {
            echo 'ERR: ' . $result->Status;
        }
    }

    public function pay($license_id, $discount, $subscribe = "")
    {
        $discountAmount = 0;

        // check discount in table or is valid user

        if (strpos($discount, '@') !== false) {
            $user = DB::table('licenses')
                ->join('users', 'licenses.user_id', '=', 'users.id')
                ->join('apps', 'licenses.app_id', '=', 'apps.id')
                ->select('licenses.code', 'users.email')
                ->where('users.email', $discount)
                ->where('licenses.status', 1)
                ->orderBy('licenses.id', 'DESC')
                ->get();

            if (count($user) > 0) {
                if ($user[0]->code != null) {
                    $discountAmount = 1000;
                }
            }
        } else {
            $discounts = DB::table('discount')
                ->select('discount')
                ->where('code', $discount)
                ->get();

            if (count($discounts) > 0) {
                $discountAmount = $discounts[0]->discount;
            }
        }

        $license = DB::table('licenses')
            ->join('users', 'licenses.user_id', '=', 'users.id')
            ->join('apps', 'licenses.app_id', '=', 'apps.id')
            ->select('licenses.id', 'apps.price', 'apps.fa_name', 'users.email')
            ->where('licenses.id', $license_id)
            ->get()[0];

//        Utility::sendPushToMe("license_id: ".$license->id." in ZarinGate ".$license->price);

        $MerchantID = '4ecb173c-2737-11e7-92b7-005056a205be';  //Required
        $Amount = $license->price - $discountAmount; //Amount will be based on Toman  - Required
        $Description = $license->fa_name;  // Required
        $Email = $license->email; // Optional
        $Mobile = '09123456789'; // Optional
        if ($discount != null) {
            $CallbackURL = 'http://periodtracker.ir/license/public/payed/' . $license->id . '/' . $discount;  // Required
        } else {
            $CallbackURL = 'http://periodtracker.ir/license/public/payed/' . $license->id;  // Required
        }

        if ($subscribe == '1-month') {
            $Amount = 8000; //Amount will be based on Toman  - Required
            $Description = 'اشتراک ۱ ماهه رویان';  // Required
            $CallbackURL = 'http://periodtracker.ir/license/public/payed/' . $license->id . '/1-month-sub';  // Required
        } else if ($subscribe == '3-month') {
            $Amount = 18000; //Amount will be based on Toman  - Required
            $Description = 'اشتراک ۳ ماهه رویان';  // Required
            $CallbackURL = 'http://periodtracker.ir/license/public/payed/' . $license->id . '/3-month-sub';  // Required
        } else if ($subscribe == '12-month') {
            $Amount = 68000; //Amount will be based on Toman  - Required
            $Description = 'اشتراک ۱۲ ماهه رویان';  // Required
            $CallbackURL = 'http://periodtracker.ir/license/public/payed/' . $license->id . '/12-month-sub';  // Required
        }

        // URL also can be ir.zarinpal.com or de.zarinpal.com
        $client = new \SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);

        $result = $client->PaymentRequest([
            'MerchantID' => $MerchantID,
            'Amount' => $Amount,
            'Description' => $Description,
            'Email' => $Email,
            'Mobile' => $Mobile,
            'CallbackURL' => $CallbackURL,
        ]);

        //Redirect to URL You can do it also by creating a form
        if ($result->Status == 100) {
            return Header('Location: https://www.zarinpal.com/pg/StartPay/' . $result->Authority . '/ZarinGate');
//             return header('Location: https://www.zarinpal.com/pg/StartPay/'.$result->Authority);
        } else {
            echo 'ERR: ' . $result->Status;
        }
    }

    public function payed($license_id, $discount = "", $subscribe = "")
    {
        $discountAmount = 0;

        $license = DB::table('licenses')
            ->join('users', 'licenses.user_id', '=', 'users.id')
            ->join('apps', 'licenses.app_id', '=', 'apps.id')
            ->select('apps.price', 'apps.fa_name', 'users.email', 'users.id AS user_id', 'licenses.code', 'apps.id AS app_id')
            ->where('licenses.id', $license_id)
            ->get()[0];
        // check discount in table or is valid user

        if (strpos($discount, '@') !== false) {
            $user = DB::table('licenses')
                ->join('users', 'licenses.user_id', '=', 'users.id')
                ->join('apps', 'licenses.app_id', '=', 'apps.id')
                ->select('licenses.code', 'users.email', 'users.id AS user_id')
                ->where('users.email', $discount)
                ->where('licenses.status', 1)
                ->orderBy('licenses.id', 'DESC')
                ->get();

            if (count($user) > 0) {
                if ($user[0]->code != null) {
                    $discountAmount = 1000;

                    // exist user invite new user damet garm :-*
                    DB::table('invitations')
                        ->insert([
                            "user_id" => $user[0]->user_id,
                            "invited_id" => $license->user_id
                        ]);
                }
            }
        } else {
            $discounts = DB::table('discount')
                ->select('discount')
                ->where('code', $discount)
                ->get();

            if (count($discounts) > 0) {
                $discountAmount = $discounts[0]->discount;
            }
        }

        $MerchantID = '4ecb173c-2737-11e7-92b7-005056a205be';
        $Amount = $license->price - $discountAmount; //Amount will be based on Toman  - Required
        $Authority = $_GET['Authority'];

        if ($subscribe == '1-month-sub') {
            $Amount = 8000; //Amount will be based on Toman  - Required
        } else if ($subscribe == '3-month-sub') {
            $Amount = 18000; //Amount will be based on Toman  - Required
        } else if ($subscribe == '12-month-sub') {
            $Amount = 68000; //Amount will be based on Toman  - Required
        }

        if ($_GET['Status'] == 'OK') {
            // URL also can be ir.zarinpal.com or de.zarinpal.com
            $client = new \SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);

            $result = $client->PaymentVerification([
                'MerchantID' => $MerchantID,
                'Authority' => $Authority,
                'Amount' => $Amount,
            ]);

            if ($result->Status == 100) {

                if ($license->code == null || $license->code == "") {
                    $utility = new Utility();
                    $Code = $utility->createRandomPassword();
                    DB::table('licenses')
                        ->where('id', $license_id)
                        ->update(['ref_id' => $result->RefID, 'code' => $Code]);
                } else {
                    $Code = $license->code;
                }

                $subscription = '';

                if ($subscribe == '1-month-sub') {
                    $dt2 = new \DateTime("+1 month");
                    $date = $dt2->format("Y-m-d");
                    DB::table('licenses')
                        ->where('id', $license_id)
                        ->update(['subscribe_expire' => $date]);
                    $subscription = '۱ ماهه';
                } else if ($subscribe == '3-month-sub') {
                    $dt2 = new \DateTime("+3 month");
                    $date = $dt2->format("Y-m-d");
                    DB::table('licenses')
                        ->where('id', $license_id)
                        ->update(['subscribe_expire' => $date]);
                    $subscription = '۳ ماهه';
                } else if ($subscribe == '12-month-sub') {
                    $dt2 = new \DateTime("+12 month");
                    $date = $dt2->format("Y-m-d");
                    DB::table('licenses')
                        ->where('id', $license_id)
                        ->update(['subscribe_expire' => $date]);
                    $subscription = '۱۲ ماهه';
                }

                Utility::sendPushToMe(" مبلغ: " . $Amount . " خریده شد ");

                $to = $license->email;
                $subject = ' فعال سازی برنامه ' . $subscription . ' رویان ';
                switch ($license->app_id) {
                    case 5:
                        $message = "scoreboard://?email=" . $license->email . "&code=" . $Code;
                        break;

                    case 6:
                        $message = "minoo://?email=" . $license->email . "&code=" . $Code;
                        break;

                    case 7:
                        $message = "minoo://?email=" . $license->email . "&code=" . $Code;
                        break;

                    case 8:
                        $message = "procoach://?email=" . $license->email . "&code=" . $Code;
                        break;

                    default:
                        $message = "royan://?email=" . $license->email . "&code=" . $Code;
                }
                $headers = 'From: mahdiyar.oraei@gmail.com' . "\r\n" .
                    'Reply-To: mahdiyar.oraei@gmail.com' . "\r\n" .
                    'X-Mailer: PHP/' . phpversion();

                $headers .= "Return-Path: mahdiyar.oraei@gmail.com\r\n";

                mail($to, $subject, $message, $headers);

                DB::table('sells')->insert([
                    'app_id' => $license->app_id,
                    'amount' => $Amount,
                    "discount" => $discount
                ]);

                if ($license->app_id == 8) {
                    Utility::sendPushToProCoach($Amount);
                }

                switch ($license->app_id) {
                    case 5:
                        return new RedirectResponse("scoreboard://?email=" . $license->email . "&code=" . $Code);

                    case 6:
                        return new RedirectResponse("minoo://?email=" . $license->email . "&code=" . $Code);

                    case 7:
                        return new RedirectResponse("minoo://?email=" . $license->email . "&code=" . $Code);

                    case 8:
                        return new RedirectResponse("procoach://?email=" . $license->email . "&code=" . $Code);

                    default:
                        return new RedirectResponse("royan://?email=" . $license->email . "&code=" . $Code);
                }
            } else {
                echo 'Transation failed. Status:' . $result->Status;
            }
        } else {
            echo 'Transaction canceled by user';
        }
    }

    public function sells($app_id)
    {
        $sells = DB::table('sells')->select(DB::raw("SUM(amount) as amount"))->where('app_id' , $app_id)->get()[0];

        return response()->json($sells, 200);
    }


    public function discountList()
    {
        $discounts = DB::table('discount')->select('*')->get();

        return response()->json($discounts, 200);
    }

    public function discount(Request $request)
    {
        $code = $request->input('code');
        $amount = $request->input('amount');


        DB::table('discount')->insert([
            "code" => $code,
            "discount" => $amount
        ]);

        return response()->json(['success' => 1], 200);
    }

    public function activation(Request $request)
    {
        $email = $request->input('email');
        $code = $request->input('code');
        $app = $request->input('app');

        $activation = DB::table('licenses')
            ->join('users', 'licenses.user_id', '=', 'users.id')
            ->join('apps', 'licenses.app_id', '=', 'apps.id')
            ->select('licenses.id', 'licenses.status')
            ->where('licenses.code', $code)
            ->where('users.email', $email)
            ->where('apps.name', $app)
            ->get();

        if (count($activation) > 0 && $activation[0]->status == 0) {
            DB::table('licenses')
                ->where('id', $activation[0]->id)
                ->update(['status' => 1]);
            return response()->json(['success' => 1], 200);
        } else {

            // free acounts

            if (count(DB::table('free_codes')->where('code', $code)->get()) > 0) {
                DB::table('free_codes')->where('code', $code)->delete();

                $result = DB::select('select id from users where email = ?', [$email]);
                if (count($result) == 0) {
                    $userId = DB::insert('insert into users (email) values (?)', [$email]);
                }

                $userId = DB::table('users')
                    ->select('users.id')
                    ->where('users.email', $email)
                    ->get()[0];

                $appId = DB::table('apps')
                    ->select('apps.id')
                    ->where('apps.name', $app)
                    ->get()[0];

                DB::table('licenses')->insert([
                    'app_id' => $appId->id,
                    'user_id' => $userId->id,
                    'code' => $code,
                    'status' => 1,
                    'ref_id' => -1
                ]);
                return response()->json(['success' => 1], 200);
            } else {
                return response()->json(['success' => 0], 200);
            }
        }
    }

    public function activation_v2(Request $request)
    {
        $email = $request->input('email');
        $code = $request->input('code');
        $app = $request->input('app');

        $activation = DB::table('licenses')
            ->join('users', 'licenses.user_id', '=', 'users.id')
            ->join('apps', 'licenses.app_id', '=', 'apps.id')
            ->select('*', 'licenses.id AS license_id')
            ->where('licenses.code', $code)
            ->where('users.email', $email)
            ->where('apps.name', $app)
            ->orderBy('license_id', 'DESC')
            ->get();

        if (count($activation) > 0) {
            if ($activation[0]->status == -1) {
                DB::table('licenses')
                    ->where('id', $activation[0]->license_id)
                    ->update(['status' => 1]);
                return response()->json(['license_id' => $activation[0]->license_id, 'user_id' => $activation[0]->user_id, 'email' => $email], 200, [], JSON_NUMERIC_CHECK);
            } else if ($activation[0]->status == 1) {

                if ($activation[0]->app_type != "subscribe") {
                    $dt2 = new \DateTime("+1 month");
                    $date = $dt2->format("Y-m-d");

                    // license disabled
                    DB::table('licenses')
                        ->where('id', $activation[0]->license_id)
                        ->update(['status' => 0]);

                    $licenseId = DB::table('licenses')->insertGetId([
                        'app_id' => $activation[0]->app_id,
                        'user_id' => $activation[0]->user_id,
                        'ref_id' => $activation[0]->ref_id,
                        'code' => $activation[0]->code,
                        'subscribe_expire' => $date,
                        'app_type' => 'subscribe',
                        'status' => 1
                    ]);

                    return response()->json(['license_id' => $licenseId, 'user_id' => $activation[0]->user_id, 'email' => $email], 200, [], JSON_NUMERIC_CHECK);

                }

                // license disabled
                DB::table('licenses')
                    ->where('id', $activation[0]->license_id)
                    ->update(['status' => 0]);

                $licenseId = DB::table('licenses')->insertGetId([
                    'app_id' => $activation[0]->app_id,
                    'user_id' => $activation[0]->user_id,
                    'ref_id' => $activation[0]->ref_id,
                    'code' => $activation[0]->code,
                    'subscribe_expire' => $activation[0]->subscribe_expire,
                    'app_type' => $activation[0]->app_type,
                    'one_signal_player_id' => $activation[0]->one_signal_player_id,
                    'status' => 1
                ]);

                return response()->json(['license_id' => $licenseId, 'user_id' => $activation[0]->user_id, 'email' => $email], 200, [], JSON_NUMERIC_CHECK);
            }
        } else {

            // free acounts


            // This segment for free subscribe code
            /*if ($code == "royan-1w") {
                $result = DB::select('select id from users where email = ?', [$email]);
                if (count($result) == 0) {
                    $userId = DB::insert('insert into users (email) values (?)', [$email]);
                }

                $userId = DB::table('users')
                    ->select('users.id')
                    ->where('users.email', $email)
                    ->get()[0];

                $appId = DB::table('apps')
                    ->select('apps.id')
                    ->where('apps.name', $app)
                    ->get()[0];

                $dt2 = new \DateTime("+1 week");
                $date = $dt2->format("Y-m-d");

                $licenseId = DB::table('licenses')->insertGetId([
                    'app_id' => $appId->id,
                    'user_id' => $userId->id,
                    'code' => $code,
                    'app_type' => 'subscribe',
                    'subscribe_expire' => $date,
                    'status' => 1,
                    'ref_id' => -1
                ]);

                return response()->json(['license_id' => $licenseId, 'user_id' => $userId->id, 'email' => $email], 200, [], JSON_NUMERIC_CHECK);
            }*/

            if (count(DB::table('free_codes')->where('code', $code)->get()) > 0) {
                DB::table('free_codes')->where('code', $code)->delete();

                $result = DB::select('select id from users where email = ?', [$email]);
                if (count($result) == 0) {
                    $userId = DB::insert('insert into users (email) values (?)', [$email]);
                }

                $userId = DB::table('users')
                    ->select('users.id')
                    ->where('users.email', $email)
                    ->get()[0];

                $appId = DB::table('apps')
                    ->select('apps.id')
                    ->where('apps.name', $app)
                    ->get()[0];

                $licenseId = DB::table('licenses')->insertGetId([
                    'app_id' => $appId->id,
                    'user_id' => $userId->id,
                    'code' => $code,
                    'app_type' => 'subscribe',
                    'status' => 1,
                    'ref_id' => -1
                ]);


//                Utility::sendPushToMe("license_id: ".$licenseId. "used code: ".$code);

                return response()->json(['license_id' => $licenseId, 'user_id' => $userId->id, 'email' => $email], 200, [], JSON_NUMERIC_CHECK);
            } else {
                return response()->json(['success' => 0], 200);
            }
        }
    }


    // V2
    public function login(Request $request)
    {
        $licenseId = $request->input('license_id');
        $userId = $request->input('user_id');
        $app = $request->input('app');

//        Utility::sendPushToMe("license_id: ".$licenseId." opened app");

        $activation = DB::table('licenses')
            ->join('users', 'licenses.user_id', '=', 'users.id')
            ->join('apps', 'licenses.app_id', '=', 'apps.id')
            ->select('licenses.id', 'licenses.status')
            ->where('licenses.id', $licenseId)
            ->where('users.id', $userId)
            ->where('apps.name', $app)
            ->orderBy('licenses.id', 'DESC')
            ->get();

        if (count($activation) > 0) {
            if ($activation[0]->status == 1) {
                return response()->json(['success' => 1], 200);
            } else {
                return response()->json(['success' => 0], 200);
            }
        } else {
            return response()->json(['success' => 0], 200);
        }
    }

    public function deactive(Request $request)
    {
        $email = $request->input('email');
        $app = $request->input('app');

        $activation = DB::table('licenses')
            ->join('users', 'licenses.user_id', '=', 'users.id')
            ->join('apps', 'licenses.app_id', '=', 'apps.id')
            ->select('licenses.id')
            ->where('users.email', $email)
            ->where('apps.name', $app)
            ->get();

        foreach ($activation as $activate) {
            DB::table('licenses')
                ->where('id', $activate->id)
                ->update(['status' => 0]);
        }

        return response()->json(['success' => 1], 200);
    }

    public function resendcode(Request $request)
    {
        $email = $request->input('email');
        $app = $request->input('app');

        try {
            $activation = DB::table('licenses')
                ->join('users', 'licenses.user_id', '=', 'users.id')
                ->join('apps', 'licenses.app_id', '=', 'apps.id')
                ->select('licenses.code', 'apps.price', 'apps.fa_name', 'users.email')
                ->where('users.email', $email)
                ->where('apps.name', $app)
                ->orderBy('licenses.id', 'DESC')
                ->get()[0];

            $to = $activation->email;
            $subject = 'کد فعال سازی ' . $activation->fa_name;
            $message = $activation->code;
            $headers = 'From: mahdiyar.oraei@gmail.com' . "\r\n" .
                'Reply-To: mahdiyar.oraei@gmail.com' . "\r\n" .
                'X-Mailer: PHP/' . phpversion();

            mail($to, $subject, $message, $headers);
            return response()->json(['success' => 1], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => 0], 200);
        }
    }

    public function generatecode()
    {
        $utility = new Utility();
        for ($i = 0; $i < 10; $i++) {
            $Code = $utility->createRandomPassword();
            DB::table('free_codes')->insert(['code' => $Code]);
        }
    }

    public function checklicense(Request $request)
    {
        $licenseId = $request->input('license_id');
        $userId = $request->input('user_id');

        $licenseUserId = DB::table('licenses')
            ->select('licenses.user_id')
            ->where('licenses.id', $licenseId)
            ->get()[0];

        if ($licenseUserId->user_id == $userId) {

            $status = DB::table('licenses')
                ->select('licenses.status')
                ->where('licenses.id', $licenseId)
                ->get()[0];

            return response()->json($status, 200);
        } else {
            return response()->json(['status' => -1], 200);
        }
    }

    public function hassubscribe($license_id, $user_id)
    {
        try {
            $subscribeExpire =
                DB::table('licenses')
                    ->join('users', 'licenses.user_id', '=', 'users.id')
                    ->select('subscribe_expire')
                    ->where('licenses.id', $license_id)
                    ->where('users.id', $user_id)
                    ->get();

            if (count($subscribeExpire) > 0) {
                $expireDate = \DateTime::createFromFormat("Y-m-d", $subscribeExpire[0]->subscribe_expire);
                if ($expireDate >= new \DateTime("now")) {
                    return response()->json(['subscribe' => 1, 'subscribe_expire_on' => $subscribeExpire[0]->subscribe_expire], 200);
                } else {
                    return response()->json(['subscribe' => 0, 'subscribe_expire_on' => $subscribeExpire[0]->subscribe_expire], 200);
                }
            } else {
                return response()->json(['subscribe' => 0], 200);
            }
        } catch (Exception $e) {
            return response()->json([json_encode($e)], 500);
        }

    }

    public function update_player_id($license_id, $player_id)
    {
        DB::table('licenses')
            ->where('id', $license_id)
            ->update(['one_signal_play‍er_id' => $player_id]);
    }

    public function install()
    {

        DB::table('apps')->whereId(1)->increment('update_count');

        return new RedirectResponse("itms-services://?action=download-manifest&url=https:appakdl.com/apps/pt/manifest.plist");
    }


    public function installAndroid()
    {

        DB::table('apps')->whereId(2)->increment('update_count');

        return new RedirectResponse("http://royanapp.ir/Royan.apk");
    }

    public function invitations($id)
    {


        return response()->json(DB::table('invitations')
            ->join('users', 'users.id', '=', 'invitations.invited_id')
            ->where('user_id', $id)
            ->select('users.email')
            ->get(), 200);
    }

    public function gift($id, $licenseId, $userId)
    {

        try {

            $license =
                DB::table('licenses')
                    ->join('users', 'licenses.user_id', '=', 'users.id')
                    ->select('*')
                    ->where('licenses.id', $licenseId)
                    ->where('users.id', $userId)
                    ->get();

            if (count($license) > 0) {

                if ($id == -1) {
                    $type = 0;
                } else {
                    $type = $id;
                }

                if (count(DB::table('invitations')
                        ->join('users', 'users.id', '=', 'invitations.invited_id')
                        ->where('user_id', $userId)
                        ->select('users.email')
                        ->get()) >= ($type + 1)) {

                    if ($id == -1) {
                        $code = DB::table('charge_irancell')->select('code')->get()[0]->code;
                        DB::table('charge_irancell')->where('code', $code)->delete();
                        DB::table('invitations')->where('user_id', $userId)->delete();
                        return response()->json(['code' => $code], 200);
                    } else if ($id == 0) {
                        $code = DB::table('charge')->select('code')->get()[0]->code;
                        DB::table('charge')->where('code', $code)->delete();
                        DB::table('invitations')->where('user_id', $userId)->delete();
                        return response()->json(['code' => $code], 200);
                    } else if ($id == 1) {
                        $code = DB::table('poolticket')->select('code')->get()[0]->code;
                        DB::table('charge')->where('code', $code)->delete();
                        DB::table('invitations')->where('user_id', $userId)->delete();
                        return response()->json(['code' => $code], 200);
                    } else if ($id == 2) {
                        $code = DB::table('primashop')->select('code')->get()[0]->code;
                        DB::table('charge')->where('code', $code)->delete();
                        DB::table('invitations')->where('user_id', $userId)->delete();
                        return response()->json(['code' => $code], 200);
                    }
                } else {
                    return response()->json(['subscribe' => 0], 200);
                }
            } else {
                return response()->json(['subscribe' => 0], 200);
            }

        } catch (Exception $e) {
            return response()->json([json_encode($e)], 500);
        }
    }

    function checkCountry()
    {
//        if (Utility::ip_info("Visitor", "Country Code") == "IR") {
//            // Disable free
//            return response()->json(['asd' => 0], 200);
//        } else {
//            // Enable free
        return response()->json(['asd' => 1], 200);
//        }
    }
}
