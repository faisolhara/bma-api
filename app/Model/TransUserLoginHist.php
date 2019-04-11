<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class TransUserLoginHist extends Model
{
    protected $table       = 'bima.trans_userlogin_hist';
    public $timestamps     = false;

    protected $primaryKey  = 'login_hist_id';

    public function employeeMaster()
    {
        return $this->belongsTo(EmployeeMaster::class, 'username', 'username');
    }

}
