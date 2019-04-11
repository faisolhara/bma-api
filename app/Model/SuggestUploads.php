<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SuggestUploads extends Model
{
    protected $table       = 'bima.suggest_uploads';
    public $timestamps     = false;

    protected $primaryKey  = 'upload_id';
}
