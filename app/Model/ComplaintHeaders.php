<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ComplaintHeaders extends Model
{
    protected $table       = 'bima.complaint_headers';
    public $timestamps     = false;

    const OPEN   		   = 'OPEN'; 
    const INSPECTING       = 'INSPECTING'; 
    const COSTING          = 'COSTING'; 
    const WAITING_APPROVAL = 'WAITING APPROVAL'; 
    const APPROVED         = 'APPROVED'; 
    const PROGRESS         = 'PROGRESS'; 
    const DONE             = 'DONE'; 
    const CANCEL   		   = 'CANCEL'; 

    protected $primaryKey  = 'complaint_id';

    public function complaintTransHist()
    {
        return $this->hasMany(ComplaintTransHist::class, 'complaint_id');
    }

    public function complaintTransHistCancel()
    {
        return $this->hasOne(ComplaintTransHist::class, 'complaint_id')->where('complaint_hist_status', '=', 'CANCEL');
    }

    public function employeeMaster()
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id');
    }

    public function complaintLines()
    {
        return $this->hasMany(ComplaintLines::class, 'complaint_id');
    }

    public function complaintUploads()
    {
        return $this->hasMany(ComplaintUploads::class, 'complaint_id');
    }

    public function roomNotificationHeaders()
    {
        return $this->hasMany(RoomNotificationHeaders::class, 'complaint_id');
    }

    public function roomMaster()
    {
        return $this->belongsTo(RoomMaster::class, 'room_id');
    }

    public function facilityMaster()
    {
        return $this->belongsTo(FacilityMaster::class, 'facility_id');
    }

    public static function getStatus(){
        return [
            self::OPEN,
            self::INSPECTING,
            self::COSTING,
            self::WAITING_APPROVAL,
            self::APPROVED,
            self::PROGRESS,
            self::DONE,
            self::CANCEL,
        ];
    }

    // public function isCompleteToday(){
    // 	return $this->last_update_date == new \DateTime();
    // }

    // public function isDone(){
    // 	return $this->complaint_status == self::DONE;
    // }

    // public function isProgress(){
    // 	return $this->complaint_status == self::PROGRESS;
    // }

    // public function isOutstanding(){
    // 	return $this->complaint_status == self::OPEN || $this->complaint_status == self::REJECT;
    // }
}
