<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class DepartmentMaster extends Model
{
    protected $table       = 'bima.department_master';
    public $timestamps     = false;

    protected $primaryKey  = 'dept_id';

    public function subunitMaster()
    {
        return $this->hasMany(SubunitMaster::class, 'dept_id');
    }
}
