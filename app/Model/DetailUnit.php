<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class DetailUnit extends Model
{
    protected $table       = 'bima.detail_units';
    public $timestamps     = false;

    protected $primaryKey  = 'detail_unit_id';

    public function unitMaster()
    {
        return $this->belongsTo(UnitMaster::class, 'unit_id');
    }

    public function subunitMaster()
    {
        return $this->belongsTo(SubunitMaster::class, 'subunit_id');
    }

    public function complaintLines()
    {
        return $this->hasMany(ComplaintLines::class, 'detail_unit_id');
        
    }
}
