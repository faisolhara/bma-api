<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class RoomMaster extends Model
{
    protected $table       = 'bima.room_master';
    public $timestamps     = false;

    protected $primaryKey  = 'room_id';

    public function complaintHeaders()
    {
        return $this->hasMany(ComplaintHeaders::class, 'room_id');
    }

    public function employeeMaster()
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id');
    }

    public function employeeUpload()
    {
        return $this->belongsTo(EmployeeUpload::class, 'employee_id');
    }

    public function badgeRoomHeaders()
    {
        return $this->hasOne(BadgeRoomHeaders::class, 'room_id');
    }

    public function roomNotificationHeaders()
    {
        return $this->hasMany(RoomNotificationHeaders::class, 'room_id');
    }

    public function roomTransHist()
    {
        return $this->hasMany(RoomTransHist::class, 'room_id');
    }

    public function suggestHeader()
    {
        return $this->hasMany(SuggestHeaders::class, 'room_id');
    }

    public function tenantMasterTenant()
    {
        return $this->belongsTo(TenantMaster::class, 'tenant_id');
    }

    public function tenantMasterLandlord()
    {
        return $this->belongsTo(TenantMaster::class, 'landlord_id');
    }

    public function transRoomLoginHist()
    {
        return $this->hasMany(TransRoomLoginHist::class, 'room_id');
    }

    public function unitMaster()
    {
        return $this->belongsTo(UnitMaster::class, 'unit_id');
    }
}
