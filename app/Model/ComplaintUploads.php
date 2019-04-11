<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ComplaintUploads extends Model
{
    protected $table       = 'bima.complaint_uploads';
    public $timestamps     = false;

    protected $primaryKey  = 'upload_id';
}
