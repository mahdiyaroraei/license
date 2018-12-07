<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class Topics extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    protected $table = "topics";

    protected $fillable = [
        'id', 'subject' , 'content' , 'user_id', 'disallow_send_message' ,'category'
    ];


    public function toArray()
    {
        $data = parent::toArray();

        $data['user'] = $this->user;

        return $data;
    }

    public function user(){
        return $this->belongsTo("App\Users");
    }
}
