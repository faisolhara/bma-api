<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class FacilityMaster extends Model
{
    protected $table       = 'bima.facility_master';
    public $timestamps     = false;

    protected $primaryKey  = 'facility_id';

    public function suggestHeaders()
    {
        return $this->hasMany(SuggestHeaders::class, 'facility_id');
        
    }

    public function getSuggestAction($start_date, $end_date){
        $totalCase  = 0;
        foreach ($this->suggestHeaders as $complaint) {
            if (!empty($start_date)) {
                if($complaint->creation_date <= $start_date){
                    continue;
                }

            }
            if (!empty($end_date)) {
                if($complaint->creation_date >= $end_date){
                    continue;
                }
            }
            $totalCase++;
        }
        return $totalCase;

    }

}
