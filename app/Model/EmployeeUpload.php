<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class EmployeeUpload extends Model
{
    protected $table       = 'bima.employee_uploads';
    public $timestamps     = false;

    protected $primaryKey  = 'upload_id';
}
