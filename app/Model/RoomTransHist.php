<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class RoomTransHist extends Model
{
    protected $table       = 'bima.room_trans_hist';
    public $timestamps     = false;

    protected $primaryKey  = 'room_hist_id';

    public function tenantMasterTenant()
    {
        return $this->belongsTo(TenantMaster::class, 'previous_tenant_id');
    }

    public function tenantMasterLandlord()
    {
        return $this->belongsTo(TenantMaster::class, 'previous_landlord_id');
    }
}
