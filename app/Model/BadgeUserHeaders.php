<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class BadgeUserHeaders extends Model
{
    protected $table = 'bima.badge_user_headers';
    public $timestamps     = false;

    protected $primaryKey  = 'badge_id';
}
