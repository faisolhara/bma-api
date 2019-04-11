<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SubunitMaster extends Model
{
    protected $table       = 'bima.subunit_master';
    public $timestamps     = false;

    protected $primaryKey  = 'subunit_id';

    public function detailUnit()
    {
        return $this->hasMany(DetailUnit::class, 'subunit_id');
    }

    public function departmentMaster()
    {
        return $this->belongsTo(DepartmentMaster::class, 'dept_id');
    }

    public function getComplaintAction($start_date, $end_date){
        $totalCase  = 0;
        $totalRate  = 0;
        $totalRater = 0;
        $score      = 0;
        $total_done = 0;
        foreach ($this->detailUnit as $detail) {
            foreach ($detail->complaintLines as $line) {
                if (!empty($start_date)) {
                    if($line->complaintHeader->creation_date <= $start_date){
                        continue;
                    }

                }
                if (!empty($end_date)) {
                    if($line->complaintHeader->creation_date >= $end_date){
                        continue;
                    }
                }
                if($line->complaintHeader->complaint_rate > 0){
                    $totalRate += $line->complaintHeader->complaint_rate;
                    $totalRater++; 
                }
                if($line->complaintHeader->complaint_status == ComplaintHeaders::DONE){
                    $startWorkTime   = !empty($line->complaintHeader->start_date) ? new \DateTime($line->complaintHeader->start_date) : null;
                    $endWorkTime     = !empty($line->complaintHeader->end_date) ? new \DateTime($line->complaintHeader->end_date) : null;

                    if($startWorkTime == null || $endWorkTime == null) continue;
                    
                    $interval = $startWorkTime->diff($endWorkTime);
                    $hour_duration    = $interval->format('%h') + ($interval->format('%a') * 24); 
                    $minute_duration  = $interval->format('%i') + ($hour_duration * 60); 
                    
                    $score      += $minute_duration;
                    $total_done++;
                }
                $totalCase++;
            }
        }

        $rate   = $totalRater != 0 ? number_format($totalRate/$totalRater,1) : 0;

        if($total_done != 0){
            $score = $score/$total_done;
        }  

        $hour   = floor($score / 60);
        $minute = $score % 60;


        return [
            'totalCase'         => $totalCase,
            'rate'              => $rate,
            'totalRater'        => $totalRater,
            'work_time_hour'    => $hour,
            'work_time_minute'  => $minute,
        ];

    }
}
