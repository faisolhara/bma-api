<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SuggestHeaders extends Model
{
    protected $table       = 'bima.suggest_headers';
    public $timestamps     = false;

    protected $primaryKey  = 'suggest_id';

    public function roomMaster()
    {
        return $this->belongsTo(RoomMaster::class, 'room_id');
    }

    public function facilityMaster()
    {
        return $this->belongsTo(FacilityMaster::class, 'facility_id');
    }

    public function suggestUploads()
    {
        return $this->hasMany(SuggestUploads::class, 'suggest_id');
    }
}
