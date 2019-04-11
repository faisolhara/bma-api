<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class UnitMaster extends Model
{
    protected $table       = 'bima.unit_master';
    public $timestamps     = false;

    protected $primaryKey  = 'unit_id';

    public function DetailUnit()
    {
        return $this->hasMany(DetailUnit::class, 'unit_id');
    }

}
