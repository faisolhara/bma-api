<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ComplaintLines extends Model
{
    protected $table       = 'bima.complaint_lines';
    public $timestamps     = false;

    protected $primaryKey  = 'complaint_line_id';

    public function detailUnit()
    {
        return $this->belongsTo(DetailUnit::class, 'detail_unit_id');
    }

    public function complaintHeader()
    {
        return $this->belongsTo(ComplaintHeaders::class, 'complaint_id');
    }
}
