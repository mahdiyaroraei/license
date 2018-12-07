<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class Messages extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    protected $table = "messages";

    protected $fillable = [
        'id', 'message' , 'topic_id' , 'user_id',
    ];


    public function toArray()
    {
        $data = parent::toArray();

        $data['user'] = $this->user;
        $data['topic'] = $this->topic;

        return $data;
    }

    public function user(){
        return $this->belongsTo("App\Users");
    }

    public function topic(){
        return $this->belongsTo("App\Topics");
    }
}
