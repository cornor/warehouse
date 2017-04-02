<?php

namespace App\Model;

use App\Channel\ChannelUser;
use Illuminate\Database\Eloquent\Model;
use App\Lib\Util;
use Illuminate\Support\Facades\Log;

class UserModel extends Model
{
    protected $primaryKey = 'user_id';

    public $timestamps = false;
    public $incrementing = false;
    protected $table = 'user';

}
