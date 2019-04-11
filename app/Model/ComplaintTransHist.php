<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ComplaintTransHist extends Model
{
    protected $table       = 'bima.complaint_trans_hist';
    public $timestamps     = false;

    protected $primaryKey  = 'complaint_hist_id';

    public function complaintHeader()
    {
        return $this->belongsTo(ComplaintHeaders::class, 'complaint_id');
    }
}
