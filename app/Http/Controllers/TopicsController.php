<?php

namespace App\Http\Controllers;

use App\Messages;
use App\Topics;
use App\Users;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class TopicsController extends BaseController
{
    public function listAllTopics($offset)
    {
        return response()->json(Topics::limit(10)->offset($offset * 10)->orderBy('id', 'DESC')->get())->setEncodingOptions(JSON_NUMERIC_CHECK);
    }

    public function addTopic(Request $request)
    {
        $subject = $request->input('subject');
        $content = $request->input('content');
        $userId = $request->input('user_id');
        $category = $request->input('category');


        Topics::create([
            "subject" => $subject,
            "content" => $content,
            "user_id" => $userId,
            "category" => $category
        ]);

        return response()->json(['success' => 1], 200);
    }

    public function addMessage(Request $request)
    {
        $message = $request->input('message');
        $topicId = $request->input('topic_id');
        $userId = $request->input('user_id');


        $message = Messages::create([
            "message" => $message,
            "topic_id" => $topicId,
            "user_id" => $userId
        ]);

        return response()->json($message, 200)->setEncodingOptions(JSON_NUMERIC_CHECK);
    }

    public function updateUserInformation(Request $request)
    {
        $userId = $request->input('user_id');
        $playerId = $request->input('player_id');
        $nikname = $request->input('nikname');

        Users::where('id' , $userId)->update([
            "player_id" => $playerId,
            "nikname" => $nikname
        ]);

        return response()->json(['success' => 1], 200);
    }

    public function listTopicMessages($topic_id, $offset)
    {
        return response()->json(Messages::where('topic_id', $topic_id)->limit(10)->offset($offset * 10)->orderBy('created_at', 'ASC')->get())->setEncodingOptions(JSON_NUMERIC_CHECK);
//        return response()->json(Messages::where('topic_id', $topic_id)->orderBy('id', 'ASC')->get())->setEncodingOptions(JSON_NUMERIC_CHECK);
    }
}
