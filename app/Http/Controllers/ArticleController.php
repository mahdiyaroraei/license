<?php

namespace App\Http\Controllers;

use App\Utility;
use Hamcrest\Util;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ArticleController extends Controller
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

    public function articles(Request $request)
    {
        $limit = $request->input('limit');
        $offset = $request->input('offset');

        try {
            $result = DB::table('article')
                ->select('*')
                ->offset($offset * $limit)
                ->limit($limit)
                ->orderBy('addedtime', 'DESC')
                ->get();

            foreach ($result as $article) {
                $article->comment_count = DB::table('comment')->select('*')->where('article_id' , $article->id)->get()->count();
            }

            echo $result;
        } catch (Exception $e) {
            return response()->json([json_encode($e)], 500);
        }

    }

    public function articleView($id)
    {
        try {
            $article = DB::table('article')->select('*')->where('id', $id)->get();

            if (count($article) > 0) {
                $article[0]->comment_count = DB::table('comment')->select('*')->where('article_id' , $article[0]->id)->get()->count();
            }

            echo $article;
        } catch (Exception $e) {
            return response()->json([json_encode($e)], 500);
        }
    }

    public function article($id, $license_id, $user_id)
    {

        try {

            $article = DB::table('article')->select('*')->where('id', $id)->get();
            if (count($article) > 0) {
                $article[0]->comment_count = DB::table('comment')->select('*')->where('article_id' , $article[0]->id)->get()->count();
            }

            if ($article[0]->access == 'subscribe' && false) {//TODO Subscribe
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
                        echo $article;
                    } else {
                        return response()->json(['subscribe' => 0], 200);
                    }
                } else {
                    return response()->json(['subscribe' => 0], 200);
                }
            } else {
                echo $article;
            }
        } catch (Exception $e) {
            return response()->json([json_encode($e)], 500);
        }

    }

    public function view($id)
    {
        try {

            DB::table('article')->whereId($id)->increment('view');
            return response()->json(['success' => 1], 200);
        } catch (Exception $e) {
            return response()->json([json_encode($e)], 500);
        }

    }

    public function clapping($id, $amount)
    {
        try {

            DB::table('article')->whereId($id)->increment('clap', $amount);
            return response()->json(['success' => 1], 200);
        } catch (Exception $e) {
            return response()->json([json_encode($e)], 500);
        }

    }

    ///////// Category //////////
    public function articlesFromCategory($cat_id ,Request $request)
    {
        $limit = $request->input('limit');
        $offset = $request->input('offset');

        try {
            $result = DB::table('article')
                ->select('*')
                ->where('category_id', $cat_id)
                ->offset($offset * $limit)
                ->limit($limit)
                ->orderBy('addedtime', 'DESC')
                ->get();

            foreach ($result as $article) {
                $article->comment_count = DB::table('comment')->select('*')->where('article_id' , $article->id)->get()->count();
            }

            echo $result;
        } catch (Exception $e) {
            return response()->json([json_encode($e)], 500);
        }
    }

    public function categories(){
        return \response()->json(DB::table('category')->select('*')->get() , 200);
    }



    ///////// Comments //////////
    public function comment(Request $request , $article_id, $license_id)
    {
        $userId = $request->input('user_id');
        $parentId = $request->input('parent_id');
        $content = $request->input('content');

        $subscribeExpire =
            DB::table('licenses')
                ->join('users', 'licenses.user_id', '=', 'users.id')
                ->select('subscribe_expire')
                ->where('licenses.id', $license_id)
                ->where('users.id', $userId)
                ->get();

        if ($parentId > 0) {
            $license = DB::table('licenses')->select('one_signal_player_id')->where('id' , DB::table('comment')->select('license_id')->where('id' , $parentId)->get()[0]->license_id)->get();
            if (count($license) > 0) {
                Utility::sendReplyCommentPushNotification($license[0]->one_signal_player_id , DB::table('article')->select('title')->where('id', $article_id)->get()[0]->title);
            }
        }

        if (count($subscribeExpire) > 0 || true) { // TODO Subscribe
            $expireDate = \DateTime::createFromFormat("Y-m-d", $subscribeExpire[0]->subscribe_expire);
            if ($expireDate >= new \DateTime("now") || true) {

                $commentId = DB::table('comment')->insertGetId([
                    "content" => $content,
                    "user_id" => $userId,
                    "parent_id" => $parentId,
                    "article_id" => $article_id,
                    "license_id" => $license_id,
                    "add_time" => new \DateTime("now" , new \DateTimeZone("Asia/Tehran"))
                ]);

                return \response()->json(["comment_id" => $commentId] , 200);
            } else {
                return response()->json(['subscribe' => 0], 200);
            }
        } else {
            return response()->json(['subscribe' => 0], 200);
        }

    }

    public function commentsOfArticle($article_id , Request $request){
        $limit = $request->input('limit');
        $offset = $request->input('offset');

        $comments = DB::table('comment')
            ->select('*')
            ->where('article_id' , $article_id)
            ->where('parent_id' , "=" , null)
            ->offset($offset * $limit)
            ->limit($limit)
            ->orderBy('add_time', 'DESC')
            ->get();

        foreach ($comments as $comment) {
            $comment->reply_comments = DB::table('comment')
                ->select('*')
                ->where('article_id' , $article_id)
                ->where('parent_id' , "=" , $comment->id)
                ->orderBy('add_time', 'DESC')
                ->get();

            $comment->email = DB::table('users')->select('email')->where('id' , $comment->user_id)->get()[0]->email;
        }

        return \response()
            ->json($comments , 200);
    }

}
