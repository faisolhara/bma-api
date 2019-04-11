<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class EmployeeMaster extends Model
{
    protected $table = 'bima.employee_master';
    public $timestamps     = false;

    protected $primaryKey  = 'employee_id';

    const TEKNISI       = 'TEKNISI';
    const SPV_TEKNISI   = 'SPV_TEKNISI';
    const MANAGER       = 'MANAGER';
    const ADMIN         = 'ADMIN';

    public function complaintHeaders()
    {
        return $this->hasMany(ComplaintHeaders::class, 'employee_id');
    }

    public function notificationHeaders()
    {
        return $this->hasMany(EmployeeNotificationHeaders::class, 'employee_id');
    }

    public function employeeUpload()
    {
        return $this->hasOne(EmployeeUpload::class, 'employee_id');
    }

    public function badgeUserHeaders()
    {
        return $this->hasOne(BadgeUserHeaders::class, 'employee_id');
    }

    public function departmentMaster()
    {
        return $this->belongsTo(DepartmentMaster::class, 'dept_id');
    }

    public function performanceHeaders()
    {
        return $this->hasOne(PerformanceHeaders::class, 'employee_id');
    }

    public function transRoomLoginHist()
    {
        return $this->hasMany(TransRoomLoginHist::class, 'username', 'username');
    }

    public function supervised()
    {
        return $this->belongsTo(EmployeeMaster::class, 'supervised_id_employee');
    }

    public function getComplaintAction($start_date, $end_date){
        $total  = 0;
        foreach ($this->complaintHeaders()->get() as $complaint) {
            if(in_array($complaint->complaint_status, [ComplaintHeaders::PROGRESS, ComplaintHeaders::DONE, ComplaintHeaders::CANCEL]) ){
                if (!empty($start_date)) {
                    if($complaint->start_date <= $start_date){
                        continue;
                    }

                }
                if (!empty($end_date)) {
                    if($complaint->start_date >= $end_date){
                        continue;
                    }
                }
                $total++;
            }
        }
        return $total;
    }

    public function getComplaintCompleted($start_date, $end_date){
        $total  = 0;
        foreach ($this->complaintHeaders()->get() as $complaint) {
            if($complaint->complaint_status == ComplaintHeaders::DONE){
                if (!empty($start_date)) {
                    if($complaint->start_date <= $start_date){
                        continue;
                    }
                }
                if (!empty($end_date)) {
                    if($complaint->start_date >= $end_date){
                        continue;
                    }
                }
                $total++;
            }
        }
        return $total;
    }

    public function getComplaintCanceled($start_date, $end_date){
        $total  = 0;
        foreach ($this->complaintHeaders()->get() as $complaint) {
            if($complaint->complaint_status == ComplaintHeaders::CANCEL){
                if (!empty($start_date)) {
                    if($complaint->start_date <= $start_date){
                        continue;
                    }
                }
                if (!empty($end_date)) {
                    if($complaint->start_date >= $end_date){
                        continue;
                    }
                }
                $total++;
            }
        }
        return $total;
    }

    public function getRatingAverage($start_date, $end_date){
        $total  = 0;
        $score  = 0;
        foreach ($this->complaintHeaders()->get() as $complaint) {
            if($complaint->complaint_rate != 0 && $complaint->complaint_status == ComplaintHeaders::DONE){
                if (!empty($start_date)) {
                    if($complaint->start_date <= $start_date){
                        continue;
                    }
                }
                if (!empty($end_date)) {
                    if($complaint->start_date >= $end_date){
                        continue;
                    }
                }
                $total++;
                $score += $complaint->complaint_rate;
            }
        }
        if($total == 0){
            return ['average' => $score, 'rater' => $total];
        }
        return ['average' => $score/$total, 'rater' => $total];
    }

    public function getWorkTimeAverage($start_date, $end_date){
        $total  = 0;
        $score  = 0;
        foreach ($this->complaintHeaders()->where('complaint_status', ComplaintHeaders::DONE)->get() as $complaint) {
            if($complaint->complaint_status == ComplaintHeaders::DONE){
                if (!empty($start_date)) {
                    if($complaint->start_date <= $start_date){
                        continue;
                    }
                }
                if (!empty($end_date)) {
                    if($complaint->start_date >= $end_date){
                        continue;
                    }
                }

                $startWorkTime   = !empty($complaint->start_date) ? new \DateTime($complaint->start_date) : null;
                $endWorkTime     = !empty($complaint->end_date) ? new \DateTime($complaint->end_date) : null;

                if($startWorkTime == null || $endWorkTime == null) continue;
                
                $interval = $startWorkTime->diff($endWorkTime);
                $complaint->hour_duration    = $interval->format('%h') + ($interval->format('%a') * 24); 
                $complaint->minute_duration  = $interval->format('%i') + ($complaint->hour_duration * 60); 
                
                $total++;
                $score += $complaint->minute_duration;
            }
        }
        if($total != 0){
            $score = $score/$total;
        }
        
        $hour = floor($score / 60);
        $minute = $score % 60;

        return [
            'work_time_hour' => $hour,
            'work_time_minute' => $minute,
        ];
    }

    public function formatWithoutZeroes () {
        // Each argument may have only one % parameter
        // Result does not handle %R or %r -- but you can retrieve that information using $this->format('%R') and using your own logic
        $parts = array ();
        foreach (func_get_args() as $arg) {
            $pre = mb_substr($arg, 0, mb_strpos($arg, '%'));
            $param = mb_substr($arg, mb_strpos($arg, '%'), 2);
            $post = mb_substr($arg, mb_strpos($arg, $param)+mb_strlen($param));
            $num = intval(parent::format($param));

            $open = preg_quote($this->pluralCheck[0], '/');
            $close = preg_quote($this->pluralCheck[1], '/');
            $pattern = "/$open(.*)$close/";
            list ($pre, $post) = preg_replace($pattern, $num == 1 ? $this->singularReplacement : '$1', array ($pre, $post));

            if ($num != 0) {
                $parts[] = $pre.$num.$post;
            }
        }
    }

    public function getTotalComplaintComplete(){
    	$total  = 0;
        foreach ($this->complaintHeaders()->get() as $complaint) {
            if ($complaint->isCompleteToday()) {
                $total++;
            }
        }
        return $total;
    }

    public function getTotalProgress(){
        $total  = 0;
        foreach ($this->complaintHeaders()->get() as $complaint) {
            if ($complaint->isProgress()) {
                $total++;
            }
        }
        return $total;
    }

    public function getTotalUnreadNotification(){
        $total  = 0;
        foreach ($this->notificationHeaders()->get() as $notification) {
            if (!$notification->isRead()) {
                $total++;
            }
        }
        return $total;
    }

    public static function getUserType(){
        return[
            self::TEKNISI,
            self::SPV_TEKNISI,
            self::MANAGER,
            self::ADMIN,
        ];
    }
}
