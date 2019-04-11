<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class BadgeRoomHeaders extends Model
{
    protected $table = 'bima.badge_room_headers';
    public $timestamps     = false;

    protected $primaryKey  = 'badge_id';
}
