<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class TransRoomLoginHist extends Model
{
    protected $table       = 'bima.trans_roomlogin_hist';
    public $timestamps     = false;

    protected $primaryKey  = 'login_hist_id';

}
