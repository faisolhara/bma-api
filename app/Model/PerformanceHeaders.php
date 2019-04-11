<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class PerformanceHeaders extends Model
{
    protected $table       = 'bima.performance_headers';
    public $timestamps     = false;

    protected $primaryKey  = 'performance_id';
}
