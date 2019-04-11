<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class EmployeeNotificationHeaders extends Model
{
    protected $table       = 'bima.employee_notification_headers';
    public $timestamps     = false;
    protected $primaryKey  = 'notification_id';

    const COMPLAINT = 'COMPLAINT';
    const SUGGEST   = 'SUGGEST';
    const BROADCAST = 'BROADCAST';

    public function isRead(){
    	return $this->is_read == 'Y';
    }

    public function complaintHeader()
    {
        return $this->belongsTo(ComplaintHeaders::class, 'complaint_id');
    }
}
