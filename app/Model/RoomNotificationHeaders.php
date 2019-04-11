<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class RoomNotificationHeaders extends Model
{
    protected $table       = 'bima.room_notification_headers';
    public $timestamps     = false;
    protected $primaryKey  = 'notification_id';

    public function isRead(){
    	return $this->is_read == 'Y';
    }

    public function complaintHeader()
    {
        return $this->belongsTo(ComplaintHeaders::class, 'complaint_id');
    }
}
