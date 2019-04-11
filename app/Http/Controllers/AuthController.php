<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Model\BadgeRoomHeaders;
use App\Model\BadgeUserHeaders;
use App\Model\ComplaintHeaders;
use App\Model\ComplaintLines;
use App\Model\ComplaintTransHist;
use App\Model\ComplaintUploads;
use App\Model\DepartmentMaster;
use App\Model\DetailUnit;
use App\Model\EmployeeMaster;
use App\Model\EmployeeNotificationHeaders;
use App\Model\EmployeeUpload;
use App\Model\PerformanceHeaders;
use App\Model\RoomMaster;
use App\Model\RoomNotificationHeaders;
use App\Model\RoomTransHist;
use App\Model\SubunitMaster;
use App\Model\SuggestHeaders;
use App\Model\SuggestUploads;
use App\Model\TenantMaster;
use App\Model\TransRoomLoginHist;
use App\Model\TransUserLoginHist;
use App\Model\UnitMaster;
use App\Model\FacilityMaster;
use Illuminate\Support\Facades\Mail;
use App\Mail\Mailtrap;
use App\Mail\MailSendToken;

date_default_timezone_set('Asia/Jakarta');

class AuthController extends Controller
{
    ///////////////////// FUNCTION LOGIN ////////////////////////////////////////////////////////////

    public function login(Request $request){
        if(is_numeric($request->get('username'))){
            $cekRoom = $this->login_room($request);
            if(!empty($cekRoom->original['data'])){
                return $cekRoom->original;
            }
        }

        $cekEmployee = $this->login_employee($request);
        if (!empty($cekEmployee->original['data'])){
            return $cekEmployee->original;
        }

        return response()->json([
            'data'           => [],
            'message'        => 'Your Username or Password is incorrect',
            'type'           => '',
        ], 200);
    }

    public function login_employee(Request $request)
    {
        $employeemaster = EmployeeMaster::where('username', '=', $request->get('username'))
                ->where('passwd', '=', $request->get('password'))
                ->where('is_active','=','Y')
                ->first();

        $this->opr_trans_userlogin_hist($request->get('username'),$employeemaster);

        if (empty($employeemaster)) {
            return response()->json([
                'data'      => [],
                'message'   => 'Your Username or Password is incorrect',
                'type'      => 'EMPLOYEE',
            ], 200);
        }

        $this->opr_player_id_employee($request->get('player_id'),$employeemaster->employee_id);

        if(!empty($employeemaster->employeeUpload)){
            $employeemaster->employeeUpload->bytea_upload = !empty($employeemaster->employeeUpload) ? pg_unescape_bytea(stream_get_contents($employeemaster->employeeUpload->bytea_upload)) : '';
        }else{
            $employeemaster->employee_upload = '';
        }
        $employeemaster->dept_name = !empty($employeemaster->departmentMaster) ? $employeemaster->departmentMaster->dept_name : ''; 

        return response()->json([
                'data'      => $employeemaster,
                'message'   => '',
                'type'      => 'EMPLOYEE',
            ], 200);
    }

    public function login_room(Request $request)
    {
        $roommaster = RoomMaster::where('room_id', '=', intval($request->get('username')))
                ->where('room_passwd', '=', $request->get('password'))
                ->where('is_active','=','Y')
                ->first();

        $this->opr_trans_roomlogin_hist($request->get('username'),$roommaster);
        if (empty($roommaster)) {
            return response()->json([
                'data'      => '',
                'message'   => 'Your Room or Password is incorrect',
                'type'      => 'ROOM',
            ], 200);
        }

        $this->opr_player_id_room($request->get('player_id'),$roommaster->room_id);

        $roommaster->tenantMasterTenant;
        return response()->json([
                'data'       => $roommaster,
                'message'    => '',
                'type'       => 'ROOM',
            ], 200);
    }

    public function logout(Request $request)
    {
        if($request->get('user_type') == 'ROOM'){
            $model = RoomMaster::find($request->get('user_id'));
        }else if($request->get('user_type') == 'EMPLOYEE'){
            $model = EmployeeMaster::find($request->get('user_id'));
        }

        $model->player_id = null;
        $model->save();

        return response()->json([
                'message'    => 'Berhasil logout',
            ], 200);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////



    //////////////////////////////// FUNCTION VIEW //////////////////////////////////////////////////////

    function get_complaint_index(Request $request) {
        $complaintHeaders = \DB::table('bima.complaint_headers')
                                ->select(
                                    'complaint_headers.complaint_id',
                                    'complaint_headers.complaint_desc',
                                    'complaint_headers.complaint_status',
                                    'complaint_headers.room_id',
                                    'complaint_headers.available_start_date',
                                    'complaint_headers.available_end_date',
                                    'complaint_headers.complaint_reference',
                                    \DB::raw("CONCAT(tenant_master.first_name, ' ', tenant_master.last_name) AS tenant_name"),
                                    \DB::raw("CONCAT(employee_master.first_name, ' ', employee_master.last_name) AS employee_name"),
                                    'room_master.room_name'
                                    )
                                ->join('bima.room_master', 'room_master.room_id', '=', 'complaint_headers.room_id')
                                ->join('bima.tenant_master', 'tenant_master.tenant_id', '=', 'room_master.tenant_id')
                                ->leftJoin('bima.employee_master', 'employee_master.employee_id', '=', 'complaint_headers.employee_id')
                                ->orderBy('complaint_headers.creation_date', 'desc');

        if(!empty($request->get('searchComplaint'))){
            $complaintHeaders->where('complaint_headers.complaint_desc','ILIKE','%'.$request->get('searchComplaint').'%')
                      ->orWhere('complaint_headers.complaint_reference','ILIKE','%'.$request->get('searchComplaint').'%')
                      ->orWhere('room_master.room_name','ILIKE','%'.$request->get('searchComplaint').'%')
                      ->orWhere(\DB::raw('CONCAT(tenant_master.first_name, tenant_master.last_name)'),'ILIKE','%'.$request->get('searchComplaint').'%')
                      ->orWhere(\DB::raw('CONCAT(employee_master.first_name, employee_master.last_name)'),'ILIKE','%'.$request->get('searchComplaint').'%');

            if(in_array(strtoupper($request->get('searchComplaint')), ComplaintHeaders::getStatus())){
                $complaintHeaders->orWhere('complaint_headers.complaint_status','=', strtoupper($request->get('searchComplaint')));
            }

            if(is_numeric($request->get('searchComplaint'))){
                $complaintHeaders->orWhere('complaint_headers.complaint_id','=', $request->get('searchComplaint'))
                      ->orWhere('room_master.room_id','=', $request->get('searchComplaint'));
            }
        }

        if(!empty($request->get('limit'))){
            $complaintHeaders->take($request->get('limit'));
        }

        $data = [];
        foreach ($complaintHeaders->get() as $complaintHeader) {
            $availableStartDate = !empty($complaintHeader->available_start_date) ? new \DateTime($complaintHeader->available_start_date) : null;
            $availableEndDate   = !empty($complaintHeader->available_end_date) ? new \DateTime($complaintHeader->available_end_date) : null;
            $complaintHeader->available_start_date = !empty($availableStartDate) ? $availableStartDate->format('d-m-Y H:i') : '';
            $complaintHeader->available_end_date = !empty($availableEndDate) ? $availableEndDate->format('d-m-Y H:i') : '';
            $data[] = $complaintHeader;
        }

        return response()->json([
                'data' => $data,
            ], 200);
    }

    function get_complaint_status(Request $request) {
        $complaint = ComplaintHeaders::find($request->get('complaint_id'));

        $data = $complaint !== null ? $complaint->complaint_status : 'Complaint tidak ditemukan';
        return response()->json([
                'data' => $data,
            ], 200);
    }

    function get_complaint(Request $request) {
        $complaintHeaders = ComplaintHeaders::orderBy('creation_date', 'desc');
        if(!empty($request->get('complaint_id'))){
            $complaintHeaders->where('complaint_id', '=', $request->get('complaint_id'));
        }

        if(!empty($request->get('complaint_desc'))){
            $complaintHeaders->where('complaint_desc', 'ILIKE', '%'.$request->get('complaint_desc').'%');
        }

        if(!empty($request->get('employee_id'))){
            $complaintHeaders->where('employee_id', '=', $request->get('employee_id'));
        }

        if(!empty($request->get('complaint_status'))){
            $complaintHeaders->where('complaint_status', '=', $request->get('complaint_status'));
        }

        if(!empty($request->get('limit'))){
            $complaintHeaders->take($request->get('limit'));
        }

        $data = [];
        foreach ($complaintHeaders->get() as $complaintHeader) {
            $complaintLines = [];
            foreach ($complaintHeader->complaintLines as $line) {
                $line->subunit_name = !empty($line->detailUnit->subunitMaster->subunit_name) ? $line->detailUnit->subunitMaster->subunit_name : '';
                $line->dept_name    = !empty($line->detailUnit->subunitMaster->departmentMaster) ? $line->detailUnit->subunitMaster->departmentMaster->dept_name : '';
                $line->unit_type    = !empty($line->detailUnit->unitMaster->unit_type) ? $line->detailUnit->unitMaster->unit_type : '';
                $complaintLines []  = $line;
            }

            $complaintUploads = [];
            foreach ($complaintHeader->complaintUploads as $upload) {
                $complaintUploads []['bytea_upload'] = !empty($upload->bytea_upload) ? pg_unescape_bytea(stream_get_contents($upload->bytea_upload)) : '';
            }

            $creationDate       = !empty($complaintHeader->creation_date) ? new \DateTime($complaintHeader->creation_date) : null;
            $availableStartDate = !empty($complaintHeader->available_start_date) ? new \DateTime($complaintHeader->available_start_date) : null;
            $availableEndDate   = !empty($complaintHeader->available_end_date) ? new \DateTime($complaintHeader->available_end_date) : null;
            $startDate          = !empty($complaintHeader->start_date) ? new \DateTime($complaintHeader->start_date) : null;
            $endDate            = !empty($complaintHeader->end_date) ? new \DateTime($complaintHeader->end_date) : null;

            $data[] = [
                'complaint_id'       => $complaintHeader->complaint_id,
                'complaint_desc'     => $complaintHeader->complaint_desc,
                'complaint_status'   => $complaintHeader->complaint_status,
                'employee_id'        => $complaintHeader->employee_id,
                'employee_player_id' => !empty($complaintHeader->employeeMaster) ? $complaintHeader->employeeMaster->player_id : '',
                'employee_name'      => !empty($complaintHeader->employeeMaster) ? $complaintHeader->employeeMaster->first_name.' '.$complaintHeader->employeeMaster->last_name : '',
                'technician_phone'   => !empty($complaintHeader->employeeMaster) ? $complaintHeader->employeeMaster->phone_number : '',
                'room_id'            => $complaintHeader->room_id,
                'room_name'          => !empty($complaintHeader->roomMaster) ? $complaintHeader->roomMaster->room_name : '',
                'room_player_id'     => !empty($complaintHeader->roomMaster) ? $complaintHeader->roomMaster->player_id : '',
                'tenant_name'        => !empty($complaintHeader->roomMaster->tenantMasterTenant) ? $complaintHeader->roomMaster->tenantMasterTenant->first_name.' '.$complaintHeader->roomMaster->tenantMasterTenant->last_name_name : '',
                'complaint_rate'     => $complaintHeader->complaint_rate,
                'complaint_note'     => $complaintHeader->complaint_note,
                'facility_id'        => $complaintHeader->facility_id,
                'facility_name'        => !empty($complaintHeader->facilityMaster) ? $complaintHeader->facilityMaster->facility_name : '',
                'available_contact_number'  => $complaintHeader->available_contact_number,
                'complaint_progress_result'      => $complaintHeader->complaint_progress_result,
                'complaint_reference'  => $complaintHeader->complaint_reference,
                'cancel_note'          => $complaintHeader->cancel_note,
                'additional_note'      => $complaintHeader->additional_note,
                'complaint_inspect_result'      => $complaintHeader->complaint_inspect_result,
                'complaint_cost'       => $complaintHeader->complaint_cost,
                'complaint_cost_detail'=> !empty($complaintHeader->complaint_cost_detail) ? pg_unescape_bytea(stream_get_contents($complaintHeader->complaint_cost_detail)) : '',
                'employee_photo'       => !empty($complaintHeader->employeeMaster->employeeUpload) ? pg_unescape_bytea(stream_get_contents($complaintHeader->employeeMaster->employeeUpload->bytea_upload)) : '',
                'creation_date'        => !empty($creationDate) ? $creationDate->format('d-m-Y H:i') : '',
                'available_start_date' => !empty($availableStartDate) ? $availableStartDate->format('d-m-Y H:i') : '',
                'available_end_date'   => !empty($availableEndDate) ? $availableEndDate->format('d-m-Y H:i') : '',
                'start_date'           => !empty($startDate) ? $startDate->format('d-m-Y H:i') : '',
                'end_date'             => !empty($endDate) ? $endDate->format('d-m-Y H:i') : '',
                'complaint_lines'      => $complaintLines,
                'complaint_uploads'    => $complaintUploads,
            ];
        }
        return response()->json([
                'data' => $data,
            ], 200);
    }

    function get_complaint_employee(Request $request) {
        $complaintHeaders = ComplaintHeaders::orderBy('creation_date', 'desc');
        if(!empty($request->get('complaint_id'))){
            $complaintHeaders->where('complaint_id', '=', $request->get('complaint_id'));
        }

        if(!empty($request->get('complaint_desc'))){
            $complaintHeaders->where('complaint_desc', 'ILIKE', '%'.$request->get('complaint_desc').'%');
        }

        if(!empty($request->get('employee_id'))){
            $complaintHeaders->where('employee_id', '=', $request->get('employee_id'));
        }

        if(!empty($request->get('complaint_status'))){
            $complaintHeaders->where('complaint_status', '=', $request->get('complaint_status'));
        }

        if(!empty($request->get('limit'))){
            $complaintHeaders->take($request->get('limit'));
        }

        $data = [];
        foreach ($complaintHeaders->get() as $complaintHeader) {
            $complaintLines = [];
            foreach ($complaintHeader->complaintLines as $line) {
                $line->subunit_name = !empty($line->detailUnit->subunitMaster->subunit_name) ? $line->detailUnit->subunitMaster->subunit_name : '';
                $line->dept_name    = !empty($line->detailUnit->subunitMaster->departmentMaster) ? $line->detailUnit->subunitMaster->departmentMaster->dept_name : '';
                $line->unit_type    = !empty($line->detailUnit->unitMaster->unit_type) ? $line->detailUnit->unitMaster->unit_type : '';
                $complaintLines []  = $line;
            }

            $complaintUploads = [];
            foreach ($complaintHeader->complaintUploads as $upload) {
                $complaintUploads []['bytea_upload'] = !empty($upload->bytea_upload) ? pg_unescape_bytea(stream_get_contents($upload->bytea_upload)) : '';
            }

            $creationDate       = !empty($complaintHeader->creation_date) ? new \DateTime($complaintHeader->creation_date) : null;
            $availableStartDate = !empty($complaintHeader->available_start_date) ? new \DateTime($complaintHeader->available_start_date) : null;
            $availableEndDate   = !empty($complaintHeader->available_end_date) ? new \DateTime($complaintHeader->available_end_date) : null;
            $startDate          = !empty($complaintHeader->start_date) ? new \DateTime($complaintHeader->start_date) : null;
            $endDate            = !empty($complaintHeader->end_date) ? new \DateTime($complaintHeader->end_date) : null;

            $data[] = [
                'complaint_id'               => $complaintHeader->complaint_id,
                'complaint_id'               => $complaintHeader->complaint_id,
                'complaint_desc'             => $complaintHeader->complaint_desc,
                'complaint_status'           => $complaintHeader->complaint_status,
                'employee_id'                => $complaintHeader->employee_id,
                'employee_name'              => !empty($complaintHeader->employeeMaster) ? $complaintHeader->employeeMaster->first_name.' '.$complaintHeader->employeeMaster->last_name : '',
                'employee_player_id'         => !empty($complaintHeader->employeeMaster) ? $complaintHeader->employeeMaster->player_id : '',
                'employee_photo'             => !empty($complaintHeader->employeeMaster->employeeUpload) ? pg_unescape_bytea(stream_get_contents($complaintHeader->employeeMaster->employeeUpload->bytea_upload)) : null,
                'technician_phone'           => !empty($complaintHeader->employeeMaster) ? $complaintHeader->employeeMaster->phone_number : '',
                'room_id'                    => $complaintHeader->room_id,
                'room_name'                  => !empty($complaintHeader->roomMaster) ? $complaintHeader->roomMaster->room_name : '',
                'room_player_id'             => !empty($complaintHeader->roomMaster) ? $complaintHeader->roomMaster->player_id : '',
                'player_id'                  => !empty($complaintHeader->roomMaster) ? $complaintHeader->roomMaster->player_id : '',
                'player_id_employee'         => !empty($complaintHeader->employeeMaster) ? $complaintHeader->employeeMaster->player_id : '',
                'tenant_name'                => !empty($complaintHeader->roomMaster->tenantMasterTenant) ? $complaintHeader->roomMaster->tenantMasterTenant->first_name.' '.$complaintHeader->roomMaster->tenantMasterTenant->last_name_name : '',
                'available_contact_number'  => $complaintHeader->available_contact_number,
                'complaint_progress_result'      => $complaintHeader->complaint_progress_result,
                'complaint_rate'             => $complaintHeader->complaint_rate,
                'complaint_inspect_result'   => $complaintHeader->complaint_inspect_result,
                'complaint_note'             => $complaintHeader->complaint_note,
                'facility_id'                => $complaintHeader->facility_id,
                'facility_name'              => !empty($complaintHeader->facilityMaster) ? $complaintHeader->facilityMaster->facility_name : '',
                'complaint_reference'        => $complaintHeader->complaint_reference,
                'cancel_note'                => $complaintHeader->cancel_note,
                'additional_note'            => $complaintHeader->additional_note,
                'complaint_cost'             => $complaintHeader->complaint_cost,
                'complaint_cost_detail'      => !empty($complaintHeader->complaint_cost_detail) ? pg_unescape_bytea(stream_get_contents($complaintHeader->complaint_cost_detail)) : '',
                'creation_date'              => !empty($creationDate) ? $creationDate->format('d-m-Y H:i') : '',
                'available_start_date'       => !empty($availableStartDate) ? $availableStartDate->format('d-m-Y H:i') : '',
                'available_end_date'         => !empty($availableEndDate) ? $availableEndDate->format('d-m-Y H:i') : '',
                'start_date'                 => !empty($startDate) ? $startDate->format('d-m-Y H:i') : '',
                'end_date'                   => !empty($endDate) ? $endDate->format('d-m-Y H:i') : '',
                'complaint_lines'            => $complaintLines,
                'complaint_uploads'          => $complaintUploads,
            ];
        }
        return response()->json([
                'data' => $data,
            ], 200);
    }

    function get_complaintbydept(Request $request) {
        $complaintbydepts = \DB::table('bima.complaint_headers')
                                ->select(
                                    'complaint_headers.complaint_id',
                                    'complaint_headers.complaint_desc',
                                    'complaint_headers.complaint_status',
                                    'complaint_headers.available_start_date',
                                    'complaint_headers.available_end_date',
                                    'complaint_headers.start_date',
                                    'complaint_headers.end_date',
                                    'complaint_headers.creation_date',
                                    'complaint_headers.last_update_date',
                                    'subunit_master.subunit_name',
                                    'unit_master.unit_type',
                                    'department_master.dept_id',
                                    'department_master.dept_name',
                                    'room_master.room_id',
                                    'room_master.room_name',
                                    'tenant_master.first_name',
                                    'tenant_master.last_name',
                                    \DB::raw("CONCAT(employee_master.first_name, ' ', employee_master.last_name) AS technician_name")
                                    )
                                ->join('bima.room_master', 'room_master.room_id', '=', 'complaint_headers.room_id')
                                ->join('bima.tenant_master', 'tenant_master.tenant_id', '=', 'room_master.tenant_id')
                                ->join('bima.complaint_lines', 'complaint_lines.complaint_id', '=', 'complaint_headers.complaint_id')
                                ->join('bima.detail_units', 'detail_units.detail_unit_id', '=', 'complaint_lines.detail_unit_id')
                                ->join('bima.subunit_master', 'subunit_master.subunit_id', '=', 'detail_units.subunit_id')
                                ->join('bima.unit_master', 'unit_master.unit_id', '=', 'detail_units.unit_id')
                                ->join('bima.department_master', 'department_master.dept_id', '=', 'subunit_master.dept_id')
                                ->leftJoin('bima.employee_master', 'employee_master.employee_id', '=', 'complaint_headers.employee_id')
                                ->distinct();

        if(!empty($request->get('status'))){
            $complaintbydepts->whereIn('complaint_headers.complaint_status', json_decode($request->get('status'), true));
        }

        if(!empty($request->get('not_in'))){
            $complaintbydepts->whereNotIn('complaint_headers.complaint_id', json_decode($request->get('not_in'), true));
        }

        if(!empty($request->get('dept_id'))){
            $complaintbydepts->where('department_master.dept_id', '=', $request->get('dept_id'));
        }

        if(!empty($request->get('room_id'))){
            $complaintbydepts->where('room_master.room_id', '=', $request->get('room_id'));
        }

        if(!empty($request->get('employee_id'))){
            $complaintbydepts->where('complaint_headers.employee_id', '=', $request->get('employee_id'));
        }

        if(!empty($request->get('limit'))){
            $complaintbydepts->take($request->get('limit'));
        }

        if(!empty($request->get('skip'))){
            $complaintbydepts->skip($request->get('skip'));
        }

        if(!empty($request->get('order_by_column')) && !empty($request->get('order_by_type'))){
            $complaintbydepts->orderBy($request->get('order_by_column'), $request->get('order_by_type'));
        }

        $data = [];
        foreach ($complaintbydepts->get() as $complaint) {
            $creationDate       = !empty($complaint->creation_date) ? new \DateTime($complaint->creation_date) : null;
            $lastUpdateDate     = !empty($complaint->last_update_date) ? new \DateTime($complaint->last_update_date) : null;
            $availableStartDate = !empty($complaint->available_start_date) ? new \DateTime($complaint->available_start_date) : null;
            $availableEndDate   = !empty($complaint->available_end_date) ? new \DateTime($complaint->available_end_date) : null;
            $startDate          = !empty($complaint->start_date) ? new \DateTime($complaint->start_date) : null;
            $endDate            = !empty($complaint->end_date) ? new \DateTime($complaint->end_date) : null;

            $complaint->creation_date       = !empty($creationDate) ? $creationDate->format('d-m-Y H:i') : '';
            $complaint->last_update_date    = !empty($lastUpdateDate) ? $lastUpdateDate->format('d-m-Y H:i') : '';
            $complaint->available_start_date = !empty($availableStartDate) ? $availableStartDate->format('d-m-Y H:i') : '';
            $complaint->available_end_date  = !empty($availableEndDate) ? $availableEndDate->format('d-m-Y H:i') : '';
            $complaint->start_date          = !empty($startDate) ? $startDate->format('d-m-Y H:i') : '';
            $complaint->end_date            = !empty($endDate) ? $endDate->format('d-m-Y H:i') : '';
            $data [] = $complaint;
        }

        return response()->json([
                'data' => $data,
            ], 200);
    }

    function get_complaintsupervisor(Request $request) {
        $complaintbydepts = \DB::table('bima.complaint_headers')
                                ->select(
                                    'complaint_headers.complaint_id',
                                    'complaint_headers.complaint_desc',
                                    'complaint_headers.complaint_status',
                                    'complaint_headers.available_start_date',
                                    'complaint_headers.available_end_date',
                                    'complaint_headers.start_date',
                                    'complaint_headers.end_date',
                                    'complaint_headers.creation_date',
                                    'complaint_headers.last_update_date',
                                    'subunit_master.subunit_name',
                                    'unit_master.unit_type',
                                    'department_master.dept_id',
                                    'department_master.dept_name',
                                    'room_master.room_id',
                                    'room_master.room_name',
                                    \DB::raw("CONCAT(employee_master.first_name, ' ', employee_master.last_name) AS technician_name")
                                    )
                                ->join('bima.room_master', 'room_master.room_id', '=', 'complaint_headers.room_id')
                                ->join('bima.tenant_master', 'tenant_master.tenant_id', '=', 'room_master.tenant_id')
                                ->join('bima.complaint_lines', 'complaint_lines.complaint_id', '=', 'complaint_headers.complaint_id')
                                ->join('bima.detail_units', 'detail_units.detail_unit_id', '=', 'complaint_lines.detail_unit_id')
                                ->join('bima.subunit_master', 'subunit_master.subunit_id', '=', 'detail_units.subunit_id')
                                ->join('bima.unit_master', 'unit_master.unit_id', '=', 'detail_units.unit_id')
                                ->join('bima.department_master', 'department_master.dept_id', '=', 'subunit_master.dept_id')
                                ->join('bima.employee_master', 'employee_master.employee_id', '=', 'complaint_headers.employee_id')
                                ->where('employee_master.supervised_id_employee', '=', $request->get('supervised_id_employee'))
                                ->distinct();

        if(!empty($request->get('status'))){
            $complaintbydepts->whereIn('complaint_headers.complaint_status', json_decode($request->get('status'), true));
        }

        if(!empty($request->get('not_in'))){
            $complaintbydepts->whereNotIn('complaint_headers.complaint_id', json_decode($request->get('not_in'), true));
        }

        if(!empty($request->get('dept_id'))){
            $complaintbydepts->where('department_master.dept_id', '=', $request->get('dept_id'));
        }

        if(!empty($request->get('room_id'))){
            $complaintbydepts->where('room_master.room_id', '=', $request->get('room_id'));
        }

        if(!empty($request->get('employee_id'))){
            $complaintbydepts->where('complaint_headers.employee_id', '=', $request->get('employee_id'));
        }

        if(!empty($request->get('limit'))){
            $complaintbydepts->take($request->get('limit'));
        }

        if(!empty($request->get('skip'))){
            $complaintbydepts->skip($request->get('skip'));
        }

        if(!empty($request->get('order_by_column')) && !empty($request->get('order_by_type'))){
            $complaintbydepts->orderBy($request->get('order_by_column'), $request->get('order_by_type'));
        }

        $data = [];
        foreach ($complaintbydepts->get() as $complaint) {
            $creationDate       = !empty($complaint->creation_date) ? new \DateTime($complaint->creation_date) : null;
            $lastUpdateDate     = !empty($complaint->last_update_date) ? new \DateTime($complaint->last_update_date) : null;
            $availableStartDate = !empty($complaint->available_start_date) ? new \DateTime($complaint->available_start_date) : null;
            $availableEndDate   = !empty($complaint->available_end_date) ? new \DateTime($complaint->available_end_date) : null;
            $startDate          = !empty($complaint->start_date) ? new \DateTime($complaint->start_date) : null;
            $endDate            = !empty($complaint->end_date) ? new \DateTime($complaint->end_date) : null;

            $complaint->creation_date       = !empty($creationDate) ? $creationDate->format('d-m-Y H:i') : '';
            $complaint->last_update_date    = !empty($lastUpdateDate) ? $lastUpdateDate->format('d-m-Y H:i') : '';
            $complaint->available_start_date = !empty($availableStartDate) ? $availableStartDate->format('d-m-Y H:i') : '';
            $complaint->available_end_date  = !empty($availableEndDate) ? $availableEndDate->format('d-m-Y H:i') : '';
            $complaint->start_date          = !empty($startDate) ? $startDate->format('d-m-Y H:i') : '';
            $complaint->end_date            = !empty($endDate) ? $endDate->format('d-m-Y H:i') : '';
            $data [] = $complaint;
        }

        return response()->json([
                'data' => $data,
            ], 200);
    }   

    function get_complaintbyroom(Request $request) {

        $room = \DB::table('bima.complaint_headers')
                    ->select(
                        'room_master.room_id',
                        'complaint_headers.complaint_id',
                        'complaint_headers.complaint_desc',
                        'complaint_headers.complaint_status',
                        'complaint_headers.complaint_rate',
                        'complaint_headers.complaint_note',
                        'employee_master.employee_id'
                        )
                    ->join('bima.room_master', 'room_master.room_id', '=', 'complaint_headers.room_id')
                    ->leftJoin('bima.employee_master', 'employee_master.employee_id', '=', 'complaint_headers.employee_id')
                    ->where('complaint_headers.room_id', '=', $request->get('room_id'))
                    ->where('complaint_headers.complaint_status', '=' ,$request->get('status'))
                    ->get();

        $data = [];
        foreach ($room as $key => $value) {
            $value->bytea_upload        = !empty($complaint->employeeMaster->employeeUpload->bytea_upload) ? $value->employeeMaster->employeeUpload->bytea_upload : '';
            $value->complaint_hist_desc = !empty($value->complaintTransHistCancel) ? $value->complaintTransHistCancel->complaint_hist_desc : '';
            $data[]                     = $value;
        }
        return response()->json([
                'data' => $data,
            ], 200);
    }

    function get_employee_photo(Request $request) {

        $employee = EmployeeMaster::find($request->get('employee_id'));
        $employee->photo = !empty($employee->employeeUpload) ? pg_unescape_bytea(stream_get_contents($employee->employeeUpload->bytea_upload)) : '';


        return response()->json([
                'data' => [
                    'employee_id'       => $employee->employee_id,
                    'first_name'        => $employee->first_name,
                    'last_name'         => $employee->last_name,
                    'photo'             => $employee->photo,
                    'employee_phone'    => $employee->phone_number,
                    'supervisor_phone'  => !empty($employee->supervised) ? $employee->supervised->phone_number : '',
                    ],
            ], 200);
    }

    function get_detailteamreport(Request $request) {
        $complaintbydepts = \DB::table('bima.complaint_headers')
                                ->select(
                                    'complaint_headers.complaint_id',
                                    'complaint_headers.complaint_desc',
                                    'complaint_headers.complaint_status',
                                    'complaint_headers.available_start_date',
                                    'complaint_headers.available_end_date',
                                    'complaint_headers.start_date',
                                    'complaint_headers.end_date',
                                    'complaint_headers.creation_date',
                                    'complaint_headers.last_update_date',
                                    'complaint_headers.complaint_rate',
                                    'complaint_headers.complaint_note',
                                    'department_master.dept_name',
                                    'unit_master.unit_type',
                                    'subunit_master.subunit_name',
                                    'room_master.room_id',
                                    'room_master.room_name',
                                    \DB::raw("CONCAT(employee_master.first_name, ' ', employee_master.last_name) AS technician_name")
                                    )
                                ->join('bima.room_master', 'room_master.room_id', '=', 'complaint_headers.room_id')
                                ->join('bima.complaint_lines', 'complaint_lines.complaint_id', '=', 'complaint_headers.complaint_id')
                                ->join('bima.detail_units', 'detail_units.detail_unit_id', '=', 'complaint_lines.detail_unit_id')
                                ->join('bima.subunit_master', 'subunit_master.subunit_id', '=', 'detail_units.subunit_id')
                                ->join('bima.unit_master', 'unit_master.unit_id', '=', 'detail_units.unit_id')
                                ->join('bima.department_master', 'department_master.dept_id', '=', 'subunit_master.dept_id')
                                ->leftJoin('bima.employee_master', 'employee_master.employee_id', '=', 'complaint_headers.employee_id')
                                ->distinct();

        if (!empty($request->get('start_date'))) {
            $dateFrom = new \DateTime($request->get('start_date'));
            $complaintbydepts->where('complaint_headers.start_date', '>=', $dateFrom->format('Y-m-d 00:00:00'));
        }

        if (!empty($request->get('end_date'))) {
            $dateTo = new \DateTime($request->get('end_date'));
            $complaintbydepts->where('complaint_headers.start_date', '<=', $dateTo->format('Y-m-d 23:59:59'));
        }

        if (!empty($request->get('start_date_creation'))) {
            $dateFrom = new \DateTime($request->get('start_date_creation'));
            $complaintbydepts->where('complaint_headers.creation_date', '>=', $dateFrom->format('Y-m-d 00:00:00'));
        }

        if (!empty($request->get('end_date_creation'))) {
            $dateTo = new \DateTime($request->get('end_date_creation'));
            $complaintbydepts->where('complaint_headers.creation_date', '<=', $dateTo->format('Y-m-d 23:59:59'));
        }

        if(!empty($request->get('status'))){
            $complaintbydepts->whereIn('complaint_headers.complaint_status', json_decode($request->get('status'), true));
        }

        if(!empty($request->get('not_in'))){
            $complaintbydepts->whereNotIn('complaint_headers.complaint_id', json_decode($request->get('not_in'), true));
        }

        if(!empty($request->get('employee_id'))){
            $complaintbydepts->where('complaint_headers.employee_id', '=', $request->get('employee_id'));
        }

        if(!empty($request->get('subunit_id'))){
            $complaintbydepts->where('subunit_master.subunit_id', '=', $request->get('subunit_id'));
        }

        if(!empty($request->get('limit'))){
            $complaintbydepts->take($request->get('limit'));
        }

        if(!empty($request->get('skip'))){
            $complaintbydepts->skip($request->get('skip'));
        }

        if(!empty($request->get('order_by_column')) && !empty($request->get('order_by_type'))){
            $complaintbydepts->orderBy($request->get('order_by_column'), $request->get('order_by_type'));
        }

        $data = [];
        foreach ($complaintbydepts->get() as $complaint) {
            $creationDate       = !empty($complaint->creation_date) ? new \DateTime($complaint->creation_date) : null;
            $startDate          = !empty($complaint->start_date) ? new \DateTime($complaint->start_date) : null;
            $endDate            = !empty($complaint->end_date) ? new \DateTime($complaint->end_date) : null;

            $complaint->day_duration      = 0; 
            $complaint->hour_duration     = 0; 
            $complaint->minute_duration   = 0; 
            
            if($startDate !== null && $endDate !== null ){
                $interval = $startDate->diff($endDate);
                $complaint->day_duration     = $interval->format('%a'); 
                $complaint->hour_duration    = $interval->format('%h'); 
                $complaint->minute_duration  = $interval->format('%i'); 
            }

            $complaint->creation_date       = !empty($creationDate) ? $creationDate->format('d-m-Y H:i') : '';
            $complaint->start_date          = !empty($startDate) ? $startDate->format('d-m-Y H:i') : '';
            $complaint->end_date            = !empty($endDate) ? $endDate->format('d-m-Y H:i') : '';


            $data [] = $complaint;
        }

        return response()->json([
                'data' => $data,
            ], 200);
    }


    public function get_dashboard(Request $request){
        $user = EmployeeMaster::find($request->get('employee_id'));

        $data  = [
            'totalCompleteToday'        => ComplaintHeaders::where('last_update_date', '=', new \DateTime())->count(),
            'totalProgress'             => ComplaintHeaders::where('complaint_status', ComplaintHeaders::PROGRESS)->count(),
            'totalOutstanding'          => ComplaintHeaders::whereIn('complaint_status', [ComplaintHeaders::OPEN])->count(),
            'totalUnreadNotification'   => $user->getTotalUnreadNotification(),
        ];

        return response()->json([
                'data' => $data,
            ], 200);
    }

    function get_department(Request $request) {

        $department = DepartmentMaster::orderBy('dept_name');

        if(!empty($request->get('searchDepartment'))) {
            $department->where(function ($department) use ($request) {
                $department->where('dept_name','ILIKE','%'.$request->get('searchDepartment').'%')
                      ->orWhere('dept_desc','ILIKE','%'.$request->get('searchDepartment').'%');
            });
        }

        if(!empty($request->get('dept_name'))) {
            $department->where('dept_name','ILIKE','%'.$request->get('dept_name').'%');
        }

        if(!empty($request->get('is_active'))) {
            $department->where('is_active','=',$request->get('is_active'));
        }

        return response()->json([
            'data' => $department->get(),
        ], 200);
    }

    function get_detailcomplaint(Request $request) {
        $complaint_lines = ComplaintLines::where('complaint_id','=',$request->get('complaint_id'))->get();
        $data = [];

        foreach ($complaint_lines as $key => $value) {
            $data[] = [
                'complaint_line_id' => $value->complaint_line_id,
                'unit_type'         => $value->detailUnit->unitMaster->unit_type,
                'unit_desc'         => $value->detailUnit->unitMaster->unit_desc,
                'subunit_name'      => $value->detailUnit->subunitMaster->subunit_name,
                'complaint_line_desc'=> $value->complaint_line_desc
            ];
        }

        return response()->json([
            'data' => $data,
        ], 200);
    }

    function get_team_report_summary(Request $request) {
        $data = \DB::table('bima.performance_headers')
                                ->select(
                                    'performance_headers.*',
                                    \DB::raw("CONCAT(employee_master.first_name, ' ', employee_master.last_name) AS technician_name")
                                    )
                                ->join('bima.employee_master', 'employee_master.employee_id', '=', 'performance_headers.employee_id')
                                ->join('bima.employee_master as supervisor', 'supervisor.employee_id', '=', 'employee_master.supervised_id_employee')
                                ->orderBy('employee_master.first_name', 'asc');

        if(!empty($request->get('supervised_id_employee'))){
            $data->where('supervisor.employee_id', '=', $request->get('supervised_id_employee'));
        }

        if(!empty($request->get('limit'))){
            $data->take($request->get('limit'));
        }

        if(!empty($request->get('skip'))){
            $data->skip($request->get('skip'));
        }

        return response()->json([
                'data' => $data->get(),
            ], 200);
    }

    function get_team_report_manager(Request $request) {
        $employees = EmployeeMaster::orderBy('first_name')
                    ->where('employee_master.user_type', EmployeeMaster::SPV_TEKNISI)
                    ->get();
        $data = [];
        foreach ($employees as $employee) {
            $result  = $this->get_team_report_supervisor($request, $employee->employee_id);
            $result  = json_decode($result->getContent());
            $sem = [
                'supervisor_name'   => $employee->first_name, 
                'technician_result' => $result->data, 
            ];
            $data[]   = $sem;
        }

        return response()->json([
                'data' => $data,
            ], 200);
    }

    function get_team_report_supervisor(Request $request, $supervisedId = null) {

        $employees = EmployeeMaster::orderBy('first_name')
                    ->select('employee_master.*')
                    ->leftJoin('bima.complaint_headers', 'complaint_headers.employee_id', '=', 'employee_master.employee_id')
                    ->where('employee_master.user_type', EmployeeMaster::TEKNISI)
                    ->orderBy('employee_master.first_name')
                    ->distinct();

        $start_date = null;
        $end_date   = null;

        if(!empty($request->get('supervised_id_employee'))){
            $employees->where('supervised_id_employee', '=', $request->get('supervised_id_employee'));
        }

        if(!empty($supervisedId)){
            $employees->where('supervised_id_employee', '=', $supervisedId);
        }

        if (!empty($request->get('start_date'))) {
            $start_date = new \DateTime($request->get('start_date'));
            $start_date = $start_date->format('Y-m-d 00:00:00');
        }

        if (!empty($request->get('end_date'))) {
            $end_date = new \DateTime($request->get('end_date'));
            $end_date = $end_date->format('Y-m-d 23:59:59');
        }

        if(!empty($request->get('limit'))){
            $employees->take($request->get('limit'));
        }

        if(!empty($request->get('skip'))){
            $employees->skip($request->get('skip'));
        }

        $data = [];
        foreach ($employees->get() as $employee) {
            if($employee->employee_id != 11) continue;
            $sem = [];
            $sem['employee_id']         = $employee->employee_id;
            $sem['technician_name']     = $employee->first_name.' '.$employee->last_name;
            $sem['complaint_action']    = $employee->getComplaintAction($start_date, $end_date);    
            $sem['complaint_completed'] = $employee->getComplaintCompleted($start_date, $end_date);    
            $sem['complaint_canceled']  = $employee->getComplaintCanceled($start_date, $end_date);

            $work_time_average          = $employee->getWorkTimeAverage($start_date, $end_date);
            $sem['work_time_hour']      = $work_time_average['work_time_hour'];
            $sem['work_time_minute']    = $work_time_average['work_time_minute'];

            $rating = $employee->getRatingAverage($start_date, $end_date);
            $sem['rating_average']      = number_format($rating['average'], 1);    
            $sem['rater']               = $rating['rater'];    
            $data [] = $sem; 
        }
        return response()->json([
                'data' => $data,
            ], 200);
    }

    function get_facility_report_supervisor(Request $request, $supervisedId = null) {

        $subunits = SubunitMaster::orderBy('subunit_name')
                    ->distinct();

        $start_date = null;
        $end_date   = null;

        if(!empty($request->get('dept_id'))){
            $subunits->where('dept_id', '=', $request->get('dept_id'));
        }

        if (!empty($request->get('start_date'))) {
            $start_date = new \DateTime($request->get('start_date'));
            $start_date = $start_date->format('Y-m-d 00:00:00');
        }

        if (!empty($request->get('end_date'))) {
            $end_date = new \DateTime($request->get('end_date'));
            $end_date = $end_date->format('Y-m-d 23:59:59');
        }

        if(!empty($request->get('limit'))){
            $subunits->take($request->get('limit'));
        }

        if(!empty($request->get('skip'))){
            $subunits->skip($request->get('skip'));
        }

        $data = [];
        foreach ($subunits->get() as $subunit) {
            $sem = [];
            $sem['subunit_id']        = $subunit->subunit_id;
            $sem['subunit_name']      = $subunit->subunit_name;

            $result                   = $subunit->getComplaintAction($start_date, $end_date);    
            $sem['complaint_action']  = $result['totalCase'];    
            $sem['rating_average']    = $result['rate'];    
            $sem['rater']             = $result['totalRater'];  
            $sem['work_time_hour']    = $result['work_time_hour'];
            $sem['work_time_minute']  = $result['work_time_minute'];

            $data [] = $sem; 
        }
        return response()->json([
                'data' => $data,
            ], 200);
    }

    function get_facility_report_manager(Request $request) {

        $departments = DepartmentMaster::orderBy('dept_name')
                    ->distinct();

        $start_date = null;
        $end_date   = null;

        if(!empty($request->get('dept_id'))){
            $departments->where('dept_id', '=', $request->get('dept_id'));
        }

        if (!empty($request->get('start_date'))) {
            $start_date = new \DateTime($request->get('start_date'));
            $start_date = $start_date->format('Y-m-d 00:00:00');
        }

        if (!empty($request->get('end_date'))) {
            $end_date = new \DateTime($request->get('end_date'));
            $end_date = $end_date->format('Y-m-d 23:59:59');
        }

        if(!empty($request->get('limit'))){
            $departments->take($request->get('limit'));
        }

        if(!empty($request->get('skip'))){
            $departments->skip($request->get('skip'));
        }

        $dataSubunit = [];
        foreach ($departments->get() as $department) {
            $sem = [];
            $sem['dept_id']   = $department->dept_id;
            $sem['dept_name'] = $department->dept_name;
            foreach ($department->subunitMaster as $subunit) {
                $semprul['subunit_id']        = $subunit->subunit_id;
                $semprul['subunit_name']      = $subunit->subunit_name;

                $result                   = $subunit->getComplaintAction($start_date, $end_date);    
                $semprul['complaint_action']  = $result['totalCase'];    
                $semprul['rating_average']    = $result['rate'];    
                $semprul['rater']             = $result['totalRater'];
                $semprul['work_time_hour']    = $result['work_time_hour'];
                $semprul['work_time_minute']  = $result['work_time_minute'];
                $sem['detail_subunit'][]      = $semprul;    
            }
            $dataSubunit [] = $sem; 
        }

        $facilities = FacilityMaster::orderBy('facility_name')
                    ->distinct();

        $start_date = null;
        $end_date   = null;

        if(!empty($request->get('facility_id'))){
            $facilities->where('facility_id', '=', $request->get('facility_id'));
        }

        if (!empty($request->get('start_date'))) {
            $start_date = new \DateTime($request->get('start_date'));
            $start_date = $start_date->format('Y-m-d 00:00:00');
        }

        if (!empty($request->get('end_date'))) {
            $end_date = new \DateTime($request->get('end_date'));
            $end_date = $end_date->format('Y-m-d 23:59:59');
        }

        if(!empty($request->get('limit'))){
            $facilities->take($request->get('limit'));
        }

        if(!empty($request->get('skip'))){
            $facilities->skip($request->get('skip'));
        }

        $dataFacility = [];
        foreach ($facilities->get() as $facility) {
            $sem = [];
            $sem['facility_id']        = $facility->facility_id;
            $sem['facility_name']      = $facility->facility_name;

            $sem['totalCase']          = $facility->getSuggestAction($start_date, $end_date);    
            $dataFacility [] = $sem; 
        }

        return response()->json([
                'dataSubunit' => $dataSubunit,
                'dataFacility' => $dataFacility,
            ], 200);
    }


    function get_detailoutstanding(Request $request) {
        $complaint_lines = ComplaintLines::where('complaint_id','=',$request->get('complaint_id'))->get();

        $data = [];

        foreach ($complaint_lines as $key => $value) {
            $data[] = [
                'complaint_line_id' => $value->complaint_line_id,
                'detail_unit_id'    => $value->detail_unit_id,
                'unit_type'         => $value->detailUnit->unitMaster->unit_type,
                'subunit_name'      => $value->detailUnit->subunitMaster->subunit_name,
                'complaint_line_desc'=> $value->complaint_line_desc
            ];
        }

        return response()->json([
            'data' => $data,
        ], 200);
    }

    function get_player_teknisi(Request $request) {
        $data = \DB::table('bima.employee_master')
                    ->select('employee_id', 'first_name', 'player_id', 'user_type')
                    ->join('bima.department_master', 'department_master.dept_id', '=', 'employee_master.dept_id')
                    ->where('employee_master.user_type', '=', 'TEKNISI')
                    ->whereNotNull('employee_master.player_id');

        if(!empty($request->get('dept_id'))){
            $data->where('department_master.dept_id', '=', $request->get('dept_id'));
        }

        if(!empty($request->get('employee_id'))){
            $data->where('employee_master.employee_id', '=', $request->get('employee_id'));
        }

        return response()->json([
            'data' => $data->get(),
        ], 200);
    }

    function get_player_employee(Request $request) {
        $data = \DB::table('bima.employee_master')
                    ->select('employee_id', 'first_name', 'player_id', 'user_type')
                    ->where('employee_master.user_type', '=', 'ADMIN')
                    ->whereNotNull('employee_master.player_id');

        if(!empty($request->get('employee_id'))){
            $data->where('employee_master.employee_id', '=', $request->get('employee_id'));
        }

        return response()->json([
            'data' => $data->get(),
        ], 200);
    }

    function get_player_room(Request $request) {
        $data = \DB::table('bima.room_master')
                    ->select('room_master.room_id', 'room_name', 'player_id')
                    ->join('bima.complaint_headers', 'complaint_headers.room_id', '=', 'complaint_headers.room_id')
                    ->whereNotNull('room_master.player_id')
                    ->distinct();

        if(!empty($request->get('complaint_id'))){
            $data->where('complaint_headers.complaint_id', '=', $request->get('complaint_id'));
        }

        return response()->json([
            'data' => $data->get(),
        ], 200);
    }

    function get_detailunit(Request $request) {
        $detail_unit = DetailUnit::orderBy('unit_id', 'desc');

        if(!empty($request->get('unit_id'))) {
            $detail_unit->where('unit_id', '=',$request->get('unit_id'));
        }

        if(!empty($request->get('limit'))) {
            $detail_unit->take($request->get('limit'));
        }

        $data = [];

        foreach ($detail_unit->get() as $key => $value) {
            if(!empty($request->get('subunit_name'))) {
                if(!stristr($value->subunitMaster->subunit_name, $request->get('subunit_name'))){
                    continue;
                }
            }

            if($value->subunitMaster->is_active == 'Y') {
                $data[] = [
                    'dept_id'       => $value->subunitMaster->dept_id,
                    'dept_name'     => $value->subunitMaster->departmentMaster->dept_name,
                    'subunit_name'  => $value->subunitMaster->subunit_name,
                    'subunit_desc'  => $value->subunitMaster->subunit_desc,
                    'unit_type'     => $value->unitMaster->unit_type
                ];
            }
        }

        return response()->json([
            'data' => $data,
        ], 200);
    }

    function get_detailunitbyroom(Request $request) {
        $detail_unit = \DB::table('bima.detail_units')
                        ->select(
                            'detail_units.detail_unit_id',
                            'department_master.dept_id',
                            'department_master.dept_name',
                            'subunit_master.subunit_name',
                            'subunit_master.subunit_desc',
                            'unit_master.unit_type'
                            )
                        ->join('bima.unit_master', 'unit_master.unit_id', '=', 'detail_units.unit_id')
                        ->join('bima.subunit_master', 'subunit_master.subunit_id', '=', 'detail_units.subunit_id')
                        ->join('bima.department_master', 'department_master.dept_id', '=', 'subunit_master.dept_id')
                        ->join('bima.room_master', 'room_master.unit_id', '=', 'unit_master.unit_id')
                        ->where('subunit_master.is_active', '=', 'Y')
                        ->distinct()
                        ->orderBy('subunit_master.subunit_name');

        if(!empty($request->get('limit'))) {
            $detail_unit->take($request->get('limit'));
        }

        if(!empty($request->get('subunit_name'))) {
            $detail_unit->where('subunit_name', 'ILIKE', '%'.$request->get('subunit_name').'%');
        }

        if(!empty($request->get('room_id'))) {
            $detail_unit->where('room_id', '=',$request->get('room_id'));
        }

        return response()->json([
            'data' => $detail_unit->get(),
        ], 200);
    }

    function get_employee(Request $request) {
        $employee = EmployeeMaster::orderBy('first_name');

        if(!empty($request->get('searchEmployee'))) {
            $employee->where(function ($employee) use ($request) {
                $employee->where('username','ILIKE','%'.$request->get('searchEmployee').'%')
                      ->orWhere('first_name','ILIKE','%'.$request->get('searchEmployee').'%')
                      ->orWhere('middle_name','ILIKE','%'.$request->get('searchEmployee').'%')
                      ->orWhere('last_name','ILIKE','%'.$request->get('searchEmployee').'%');
            });
        }

        if(!empty($request->get('supervised'))) {
            $employee->where('user_type', '=', 'SPV_TEKNISI');
        }

        if(!empty($request->get('employee_id'))) {
            $employee->where('employee_id', '=', $request->get('employee_id'));
        }

        if(!empty($request->get('first_name'))) {
            $employee->where('first_name','ILIKE','%'.$request->get('first_name').'%');
        }

        if(!empty($request->get('username'))) {
            $employee->where('username','ILIKE','%'.$request->get('username').'%');
        }

        if(!empty($request->get('limit'))) {
            $employee->take($request->get('limit'));
        }

        if(!empty($request->get('user_type'))) {
            if(in_array($request->get('user_type'), [EmployeeMaster::ADMIN, EmployeeMaster::TEKNISI, EmployeeMaster::SPV_TEKNISI, EmployeeMaster::MANAGER])){
                $employee->where('user_type','=', $request->get('user_type'));
            }
        }

        $data = [];

        foreach ($employee->get() as $key => $value) {
            $birthDate = !empty($value->birth_date) ? new \DateTime($value->birth_date) : null;
            $data[] = [
                'employee_id'   => $value->employee_id,
                'username'      => $value->username,
                'passwd'        => $value->passwd,
                'user_type'     => $value->user_type,
                'first_name'    => $value->first_name,
                'middle_name'   => $value->middle_name,
                'last_name'     => $value->last_name,
                'sex'           => $value->sex,
                'citizenship'   => $value->citizenship,
                'identity_card' => $value->identity_card,
                'identity_number'=>$value->identity_number,
                'npwp_number'   => $value->npwp_number,
                'birth_date'    => !empty($birthDate) ? $birthDate->format('d-m-Y') : '',
                'birth_place'   => $value->birth_place,
                'blood_type'    => $value->blood_type,
                'address'       => $value->address,
                'administrative_village'=> $value->administrative_village,
                'district'      => $value->district,
                'email'         => $value->email,
                'phone_number'  => $value->phone_number,
                'is_active'     => $value->is_active,
                'first_login'   => $value->first_login,
                'dept_id'       => $value->dept_id,
                'supervised_id_employee' => $value->supervised_id_employee,
                'supervised_name' => !empty($value->supervised) ? $value->supervised->first_name.' '.$value->supervised->last_name : '' ,
                'upload_id'     => (!empty($value->employeeUpload)) ? $value->employeeUpload->upload_id : '-1',
                'bytea_upload'  => (!empty($value->employeeUpload)) ? pg_unescape_bytea(stream_get_contents($value->employeeUpload->bytea_upload)) : ''
            ];
        }
        return response()->json([
            'data' => $data,
        ], 200);

    }

    function get_employee_index(Request $request) {
        $employee = \DB::table('bima.employee_master')
                        ->select(
                            'employee_master.employee_id',
                            'employee_master.username',
                            'employee_master.first_name',
                            'employee_master.middle_name',
                            'employee_master.last_name',
                            'employee_master.user_type',
                            \DB::raw("CONCAT(supervised.first_name, ' ', supervised.last_name) AS supervised_name"),
                            'department_master.dept_name'
                            )
                        ->leftJoin('bima.employee_master as supervised', 'supervised.employee_id', '=', 'employee_master.supervised_id_employee')
                        ->leftJoin('bima.department_master', 'department_master.dept_id', '=', 'employee_master.dept_id')
                        ->orderBy('employee_master.first_name')
                        ->distinct();

        if(!empty($request->get('searchEmployee'))) {
            $employee->where(function ($employee) use ($request) {
                $employee->where('employee_master.username','ILIKE','%'.$request->get('searchEmployee').'%')
                      ->orWhere('employee_master.first_name','ILIKE','%'.$request->get('searchEmployee').'%')
                      ->orWhere('employee_master.middle_name','ILIKE','%'.$request->get('searchEmployee').'%')
                      ->orWhere('employee_master.last_name','ILIKE','%'.$request->get('searchEmployee').'%')
                      ->orWhere(\DB::raw('CONCAT(supervised.first_name, supervised.last_name)'),'ILIKE','%'.$request->get('searchEmployee').'%')
                      ->orWhere('department_master.dept_name','ILIKE','%'.$request->get('searchEmployee').'%');
                if(in_array(strtoupper($request->get('searchEmployee')), EmployeeMaster::getUserType())){
                    $employee->orWhere('employee_master.user_type','=', strtoupper($request->get('searchEmployee')));
                }
            });
        }

        if(!empty($request->get('limit'))) {
            $employee->take($request->get('limit'));
        }
  
        return response()->json([
            'data' => $employee->get(),
        ], 200);

    }

    function get_employee_detail_unit(Request $request) {
        $employee = \DB::table('bima.employee_master')
                    ->select(
                        'employee_master.employee_id',
                        'employee_master.first_name',
                        'employee_master.middle_name',
                        'employee_master.last_name'
                        )
                    ->join('bima.department_master', 'department_master.dept_id', '=', 'employee_master.dept_id')
                    ->join('bima.subunit_master', 'subunit_master.dept_id', '=', 'department_master.dept_id')
                    ->join('bima.detail_units', 'detail_units.subunit_id', '=', 'subunit_master.subunit_id')
                    ->where('detail_units.detail_unit_id', '=', $request->get('detail_unit_id'))
                    ->orderBy('first_name')
                    ->distinct();

        if(!empty($request->get('searchEmployee'))) {
            $employee->where(function ($employee) use ($request) {
                $employee->where('employee_master.username','ILIKE','%'.$request->get('searchEmployee').'%')
                      ->orWhere('employee_master.first_name','ILIKE','%'.$request->get('searchEmployee').'%')
                      ->orWhere('employee_master.middle_name','ILIKE','%'.$request->get('searchEmployee').'%')
                      ->orWhere('employee_master.last_name','ILIKE','%'.$request->get('searchEmployee').'%');
            });
        }

        if(!empty($request->get('first_name'))) {
            $employee->where('first_name','ILIKE','%'.$request->get('first_name').'%');
        }

        if(!empty($request->get('limit'))) {
            $employee->take($request->get('limit'));
        }

        if(!empty($request->get('is_active'))) {
            $employee->where('employee_master.is_active','=', $request->get('is_active'));
        }

        if(!empty($request->get('user_type'))) {
            $employee->where('user_type','=', $request->get('user_type'));
        }

        return response()->json([
            'data' => $employee->get(),
        ], 200);

    }

    function get_employeebadge(Request $request) {
        $badgeuser = BadgeUserHeaders::where('employee_id','=',$request->get('employee_id'));

        return response()->json([
            'data' => $badgeuser->get(),
        ], 200);
    }

    function get_employeecomplaint(Request $request) {
        $employeecomplaint = ComplaintHeaders::where('employee_id','=',$request->get('employee_id'))->whereIn('complaint_status',$request->get('status'));

        return response()->json([
            'data' => $employeecomplaint->get(),
        ], 200);
    }

    function get_employeenotification(Request $request) {

        $employeenotification = \DB::table('bima.employee_notification_headers')
                                    ->select(
                                        'employee_notification_headers.*', 
                                        'complaint_headers.complaint_status', 
                                        'room_master.room_name', 
                                        \DB::raw("CONCAT(employee_master.first_name, ' ', employee_master.last_name) AS employee_name"),
                                        \DB::raw("CONCAT(technician.first_name, ' ', technician.last_name) AS technician_name")
                                        )
                                    ->join('bima.employee_master', 'employee_master.employee_id', '=', 'employee_notification_headers.employee_id')
                                    ->leftJoin('bima.complaint_headers', 'complaint_headers.complaint_id', '=', 'employee_notification_headers.complaint_id')
                                    ->leftJoin('bima.room_master', 'room_master.room_id', '=', 'complaint_headers.room_id')
                                    ->leftJoin('bima.employee_master as technician', 'technician.employee_id', '=', 'complaint_headers.employee_id')
                                    ->where('employee_notification_headers.employee_id','=',$request->get('employee_id'))
                                    ->orderBy('employee_notification_headers.creation_date', 'desc');

        if(!empty($request->get('notification_title'))){
            $employeenotification->where('employee_notification_headers.notification_title', 'ILIKE', '%'.$request->get('notification_title').'%');
        }

        if(!empty($request->get('notification_type'))){
            $employeenotification->where('employee_notification_headers.notification_type', '=', $request->get('notification_type'));
        }

        if(!empty($request->get('notification_desc'))){
            $employeenotification->where('employee_notification_headers.notification_desc', 'ILIKE', '%'.$request->get('notification_desc').'%');
        }

        if(!empty($request->get('dateFrom'))){
            $dateFrom = new \DateTime($request->get('dateFrom'));
            $employeenotification->where('employee_notification_headers.creation_date', '>', $dateFrom->format('Y-m-d 00:00:00'));
        }

        if(!empty($request->get('dateTo'))){
            $dateTo = new \DateTime($request->get('dateTo'));
            $employeenotification->where('employee_notification_headers.creation_date', '<', $dateTo->format('Y-m-d 23:59:59'));
        }

        if(!empty($request->get('is_sent'))) {
            $employeenotification->where('is_sent','=',$request->get('is_sent'));
        }

        if(!empty($request->get('is_read'))) {
            $employeenotification->where('is_read','=',$request->get('is_read'));
        }

        if(!empty($request->get('limit'))) {
            $employeenotification->take($request->get('limit'));
        }

        if(!empty($request->get('skip'))) {
            $employeenotification->skip($request->get('skip'));
        }

        $data = [];
        foreach ($employeenotification->get() as $value) {
            $creationDate         = new \DateTime($value->creation_date);
            $value->templastid    = $request->get('lastid', 0);
            $value->creation_date = $creationDate->format('d-m-Y H:i');
            $data[] = $value;
        }

        return response()->json([
            'data'  => $data,
            'count' => $employeenotification->count(),
        ], 200);
    }

    function get_countemployeenotification(Request $request) {

        $employeenotification = \DB::table('bima.employee_notification_headers')
                                    ->where('employee_notification_headers.employee_id','=',$request->get('employee_id'));
        return response()->json([
            'data' => $employeenotification->count(),
        ], 200);
    }

    function get_countemployeenotificationunread(Request $request) {

        $employeenotification = \DB::table('bima.employee_notification_headers')
                                    ->where('employee_notification_headers.employee_id','=',$request->get('employee_id'))
                                    ->where('is_read', '=', 'N');

        return response()->json([
            'data' => $employeenotification->count(),
        ], 200);
    }

    function get_employeenotificationunsend(Request $request) {
        \DB::beginTransaction();

        $output = '';
        $employeenotification = EmployeeNotificationHeaders::where('employee_notification_headers.employee_id','=',$request->get('employee_id'))
                                    ->where('is_sent', '=', 'N')->get();

      
        foreach ($employeenotification as $notif) {

            $notif->is_sent = 'Y';

             try{
                $notif->save();
                $output = 'true';
            } catch(\Exception $e) {
                $output = $e->getMessage();
            }
        }

        foreach ($employeenotification as $notif) {
            $creationDate = !empty($notif->creation_date) ? new \DateTime($notif->creation_date) : null;
            $notif->creation_date = !empty($creationDate) ? $creationDate->format('d-m-Y H:i') : '';
        }

        if(!strpos($output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $employeenotification,
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan set to send employee notification',
                'err'   => $output
            ], 200);
        }

    }

    function get_managercomplaint(Request $request) {
        $managercomplaint = ComplaintHeaders::whereIn('complaint_status', $request->get('status'))->get();

        $data = [];

        foreach ($managercomplaint as $key => $value) {
            $data[] = [
                'complaint_id'      => $value->complaint_id,
                'complaint_desc'    => $value->complaint_desc,
                'complaint_status'  => $value->complaint_status,
                'room_id'           => $value->room_id,
                'room_name'         => $value->roomMaster->room_name,
                'room_desc'         => $value->roomMaster->room_desc,
                'tenant_id'         => $value->tenant_id,
                'first_name_tenant' => $value->roomMaster->tenantMasterTenant->first_name,
                'last_name_tenant'  => $value->roomMaster->tenantMasterTenant->last_name,
                'creation_date'     => $value->creation_date
            ];
        }

        return response()->json([
            'data' => $data,
        ], 200);
    }

    function get_photocomplaint(Request $request) {
        $complaintupload = ComplaintUploads::where('complaint_id','=',$request->get('complaint_id'));

        return response()->json([
            'data' => $complaintupload->get(),
        ], 200);
    }

    function get_room_index(Request $request) {
        $roommaster = \DB::table('bima.room_master')
                        ->select(
                                'room_master.*',
                                'unit_master.unit_type',
                                'tenant.phone_number as phone_number_tenant',
                                \DB::raw("CONCAT(tenant.first_name, ' ', tenant.last_name) AS tenant_name"),
                                \DB::raw("CONCAT(landlord.first_name, ' ', landlord.last_name) AS landlord_name")
                            )
                        ->leftJoin('bima.tenant_master as tenant', 'tenant.tenant_id', '=', 'room_master.tenant_id')
                        ->leftJoin('bima.tenant_master as landlord', 'landlord.tenant_id', '=', 'room_master.landlord_id')
                        ->leftJoin('bima.unit_master', 'unit_master.unit_id', '=', 'room_master.unit_id')
                        ->orderBy('room_id');

        if(!empty($request->get('searchRoom'))) {
            $roommaster->where(function ($roommaster) use ($request) {
                $roommaster->where('room_name','ILIKE','%'.$request->get('searchRoom').'%')
                            ->orWhere('unit_master.unit_type', '=', '%'.$request->get('searchRoom').'%')
                            ->orWhere(\DB::raw('CONCAT(tenant.first_name, tenant.last_name)'),'ILIKE','%'.$request->get('searchRoom').'%')
                            ->orWhere(\DB::raw('CONCAT(landlord.first_name, landlord.last_name)'),'ILIKE','%'.$request->get('searchRoom').'%');

                if(is_numeric($request->get('searchRoom'))){
                    $roommaster->orWhere('room_id','=', $request->get('searchRoom'));
                }
            });
        }

        if(!empty($request->get('room_id'))) {
            $roommaster->where('room_id','=', $request->get('room_id'));
        }

        if(!empty($request->get('room_name'))) {
            $roommaster->where('room_name','ILIKE', '%'.$request->get('room_name').'%');
        }

        if(!empty($request->get('is_active'))) {
            $roommaster->where('is_active','=',$request->get('is_active'));
        }

        if(!empty($request->get('limit'))) {
            $roommaster->take($request->get('limit'));
        }
      
        return response()->json([
            'data' => $roommaster->get(),
        ], 200);
    }

    function get_room(Request $request) {
        $roommaster = RoomMaster::orderBy('room_id');

        if(!empty($request->get('searchRoom'))) {
            $roommaster->where(function ($roommaster) use ($request) {
                if(is_numeric($request->get('searchRoom'))){
                    $roommaster->where('room_id','=', $request->get('searchRoom'));
                }
                $roommaster->orWhere('room_name','ILIKE','%'.$request->get('searchRoom').'%');
            });
        }

        if(!empty($request->get('room_id'))) {
            $roommaster->where('room_id','=', $request->get('room_id'));
        }

        if(!empty($request->get('room_name'))) {
            $roommaster->where('room_name','ILIKE', '%'.$request->get('room_name').'%');
        }

        if(!empty($request->get('is_active'))) {
            $roommaster->where('is_active','=',$request->get('is_active'));
        }

        if(!empty($request->get('limit'))) {
            $roommaster->take($request->get('limit'));
        }
        $data = [];

        foreach ($roommaster->get() as $key => $value) {
            $data[] = [
                'room_id'               => $value->room_id,
                'room_name'             => $value->room_name,
                'room_passwd'           => $value->room_passwd,
                'room_desc'             => $value->room_desc,
                'unit_id'               => $value->unit_id,
                'unit_type'             => !empty($value->unitMaster->unit_type) ? $value->unitMaster->unit_type : '',
                'tenant_id'             => $value->tenant_id,
                'first_name_tenant'     => $value->tenantMasterTenant->first_name,
                'last_name_tenant'      => $value->tenantMasterTenant->last_name,
                'phone_number_tenant'   => $value->tenantMasterTenant->phone_number,
                'email'                 => $value->tenantMasterTenant->email,
                'landlord_id'           => $value->landlord_id,
                'first_name_landlord'   => $value->tenantMasterLandlord->first_name,
                'last_name_landlord'    => $value->tenantMasterLandlord->last_name,
                'is_active'             => $value->is_active,
                'first_login'           => $value->first_login
            ];
        }

        return response()->json([
            'data' => $data,
        ], 200);
    }

    function get_roombadge(Request $request) {
        $badgeroomheaders = BadgeRoomHeaders::orderBy('room_id');

        if(!empty($request->get('room_id'))) {
            $badgeroomheaders->where('room_id','=',$request->get('room_id'));
        }

        return response()->json([
            'data' => $badgeroomheaders->get(),
        ], 200);
    }

    function get_roomnotification(Request $request) {
        $roomnotification = \DB::table('bima.room_notification_headers')   
                            ->select('room_notification_headers.*', 'complaint_headers.complaint_status', 'room_master.room_name', \DB::raw("CONCAT(employee_master.first_name, ' ', employee_master.last_name) AS employee_name"))
                            ->join('bima.room_master', 'room_master.room_id', '=', 'room_notification_headers.room_id')
                            ->join('bima.complaint_headers', 'complaint_headers.complaint_id', '=', 'room_notification_headers.complaint_id')
                            ->leftJoin('bima.employee_master', 'employee_master.employee_id', '=', 'complaint_headers.employee_id')
                            ->where('room_notification_headers.room_id','=',$request->get('room_id'))
                            ->orderBy('room_notification_headers.creation_date', 'desc');
        if(!empty($request->get('is_sent'))) {
            $roomnotification->where('is_sent','=',$request->get('is_sent'));
        }

        if(!empty($request->get('is_read'))) {
            $roomnotification->where('is_read','=',$request->get('is_read'));
        }

        if(!empty($request->get('limit'))) {
            $roomnotification->take($request->get('limit'));
        }

        if(!empty($request->get('skip'))) {
            $roomnotification->skip($request->get('skip'));
        }

        $data = [];
        foreach ($roomnotification->get() as $value) {
            $creationDate         = new \DateTime($value->creation_date);
            $value->creation_date = $creationDate->format('d-m-Y H:i');
            $data[] = $value;
        }

        return response()->json([
            'data' => $data,
        ], 200);
    }

    function get_badgeemployeenotification(Request $request) {
        $complaintOutstanding = \DB::table('bima.complaint_headers')   
                            ->join('bima.complaint_lines', 'complaint_lines.complaint_id', '=', 'complaint_headers.complaint_id')
                            ->join('bima.detail_units', 'detail_units.detail_unit_id', '=', 'complaint_lines.detail_unit_id')
                            ->join('bima.subunit_master', 'subunit_master.subunit_id', '=', 'detail_units.subunit_id')
                            ->where('complaint_headers.complaint_status','=', ComplaintHeaders::OPEN)
                            ->distinct();

        if(!empty($request->get('dept_id'))){
            $complaintOutstanding->where('subunit_master.dept_id','=', $request->get('dept_id'));
        }

        $complaintOnprogressByTechnician = \DB::table('bima.complaint_headers')
                            ->join('bima.employee_master', 'employee_master.employee_id', '=', 'complaint_headers.employee_id')
                            ->whereNotIn('complaint_status', [ComplaintHeaders::DONE, ComplaintHeaders::CANCEL, ComplaintHeaders::OPEN]);

        if(!empty($request->get('employee_id'))){
            $complaintOnprogressByTechnician->where('employee_master.employee_id','=', $request->get('employee_id'));
        }

        if(!empty($request->get('dept_id'))){
            $complaintOnprogressByTechnician->where('employee_master.dept_id','=', $request->get('dept_id'));
        }

        $complaintOnprogressBySubordinat = \DB::table('bima.complaint_headers')
                            ->join('bima.employee_master', 'employee_master.employee_id', '=', 'complaint_headers.employee_id')
                            ->whereNotIn('complaint_status', [ComplaintHeaders::DONE, ComplaintHeaders::CANCEL, ComplaintHeaders::OPEN]);

        if(!empty($request->get('employee_id'))){
            $complaintOnprogressBySubordinat->where('employee_master.supervised_id_employee','=', $request->get('employee_id'));
        }


        $notificationUnread = \DB::table('bima.employee_notification_headers')
                            ->where('employee_notification_headers.employee_id','=',$request->get('employee_id'))
                            ->where('is_read', '=', 'N');

        return response()->json([
            'complaint_outstanding'             => $complaintOutstanding->count(),
            'complaint_on_progress_technician'  => $complaintOnprogressByTechnician->count(),
            'complaint_on_progress_subordinat'  => $complaintOnprogressBySubordinat->count(),
            'notification_unread'               => $notificationUnread->count(),
        ], 200);
    }

    function get_countroomnotificationunread(Request $request) {

        $roomnotification = \DB::table('bima.room_notification_headers')
                                    ->where('room_notification_headers.room_id','=',$request->get('room_id'))
                                    ->where('is_read', '=', 'N');

        return response()->json([
            'data' => $roomnotification->count(),
        ], 200);
    }

    function get_roomnotificationunsend(Request $request) {
        \DB::beginTransaction();

        $output = '';
        $roomnotification = RoomNotificationHeaders::where('room_notification_headers.room_id','=',$request->get('room_id'))
                                    ->where('is_sent', '=', 'N')->get();

        foreach ($roomnotification as $notif) {
            $notif->is_sent = 'Y';

            try{
                $notif->save();
                $output = 'true';
            } catch(\Exception $e) {
                $output = $e->getMessage();
            }
        }

        foreach ($roomnotification as $notif) {
            $creationDate = !empty($notif->creation_date) ? new \DateTime($notif->creation_date) : null;
            $notif->creation_date = !empty($creationDate) ? $creationDate->format('d-m-Y H:i') : '';
        }

        if(!strpos($output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $roomnotification,
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan set to send room notification',
                'err'   => $output
            ], 200);
        }

    }

    function get_spvcomplaint(Request $request) {
        $employeemaster = EmployeeMaster::where('supervised_id_employee','=',$request->get('employee_id'))->get();

        $dataEmployee = [];
        $data = [];

        foreach ($employeemaster as $key => $value) {
            $dataEmployee[] = [
                'employee_id' => $value->employee_id
            ];
        }

        $complaintheaders = ComplaintHeaders::whereIn('employee_id',$dataEmployee)->whereIn('complaint_status',$request->get('status'))->get();

        foreach ($complaintheaders as $complaintheader) {
            foreach ($complaintheader->complaintLines as $complaintlines) {
                $data[] = [
                    'complaint_id'  => $complaintheaders->complaint_id,
                    'complaint_desc'=> $complaintheaders->complaint_desc,
                    'room_id'       => $complaintheaders->roomMaster->room_id,
                    'room_name'     => $complaintheaders->roomMaster->room_name,
                    'room_desc'     => $complaintheaders->roomMaster->room_desc,
                    'unit_type'     => !empty($complaintlines->detailUnit->unitMaster) ? $complaintlines->detailUnit->unitMaster->unit_type : '',
                    'first_name'    => $complaintheaders->roomMaster->tenantMasterTenant->first_name,
                    'last_name'     => $complaintheaders->roomMaster->tenantMasterTenant->last_name,
                    'creation_date' => $complaintheaders->creation_date
                ];
            }
        }

        return response()->json([
            'data' => $data,
        ], 200);
    }

    function get_subunit(Request $request) {
        $subunitmaster = SubunitMaster::orderBy('subunit_name')
                            ->select('subunit_master.*', 'department_master.dept_name')
                            ->join('bima.department_master', 'department_master.dept_id', '=', 'subunit_master.dept_id');

        if(!empty($request->get('searchSubunit'))) {
            $subunitmaster->where(function ($subunitmaster) use ($request) {
                $subunitmaster->where('subunit_name','ILIKE','%'.$request->get('searchSubunit').'%')
                      ->orWhere('subunit_desc','ILIKE','%'.$request->get('searchSubunit').'%');
            });
        }

        if(!empty($request->get('subunit_name'))) {
            $subunitmaster->where('subunit_name','ILIKE','%'.$request->get('subunit_name').'%');
        }

        if(!empty($request->get('searchSubunitName'))) {
            $subunitmaster->where('unit_type','ILIKE','%'.$request->get('searchSubunitName').'%');
        }

        if(!empty($request->get('limit'))) {
            $subunitmaster->take($request->get('limit'));
        }

        if(!empty($request->get('is_active'))) {
            $subunitmaster->where('subunit_master.is_active','=',$request->get('is_active'));
        }

        return response()->json([
            'data' => $subunitmaster->get(),
        ], 200);
    }

    function get_suggest_index(Request $request) {

        $suggestheaders = \DB::table('bima.suggest_headers')
                                ->select(
                                    'suggest_headers.*',
                                    'room_master.room_name',
                                    'room_master.room_desc',
                                    'facility_master.facility_name',
                                    'tenant_master.phone_number',
                                    \DB::raw("CONCAT(tenant_master.first_name, ' ', tenant_master.last_name) AS tenant_name")
                                    )
                                ->join('bima.room_master', 'room_master.room_id', '=', 'suggest_headers.room_id')
                                ->join('bima.tenant_master', 'tenant_master.tenant_id', '=', 'room_master.tenant_id')
                                ->join('bima.facility_master', 'facility_master.facility_id', '=', 'suggest_headers.facility_id')
                                ->orderBy('suggest_headers.creation_date', 'desc');

        if(!empty($request->get('searchSuggest'))){
            $suggestheaders->where('suggest_headers.suggest_name','ILIKE','%'.$request->get('searchSuggest').'%')
                      ->orWhere('suggest_headers.suggest_desc','ILIKE','%'.$request->get('searchSuggest').'%')
                      ->orWhere('room_master.room_name','ILIKE','%'.$request->get('searchSuggest').'%')
                      ->orWhere(\DB::raw('CONCAT(tenant_master.first_name, tenant_master.last_name)'),'ILIKE','%'.$request->get('searchSuggest').'%');

            if(is_numeric($request->get('searchSuggest'))){
                $suggestheaders->orWhere('suggest_headers.suggest_id','=', $request->get('searchSuggest'))
                      ->orWhere('room_master.room_id','=', $request->get('searchSuggest'));
            }
        }

        if(!empty($request->get('suggest_name'))) {
            $suggestheaders->where('room_master.suggest_name','=',$request->get('suggest_name'));
        }

        if (!empty($request->get('start_date'))) {
            $dateFrom = new \DateTime($request->get('start_date'));
            $suggestheaders->where('suggest_headers.creation_date', '>=', $dateFrom->format('Y-m-d 00:00:00'));
        }

        if (!empty($request->get('end_date'))) {
            $dateTo = new \DateTime($request->get('end_date'));
            $suggestheaders->where('suggest_headers.creation_date', '<=', $dateTo->format('Y-m-d 23:59:59'));
        }

        if(!empty($request->get('facility_id'))) {
            $suggestheaders->where('suggest_headers.facility_id', '=', $request->get('facility_id'));
        }

        if(!empty($request->get('limit'))) {
            $suggestheaders->take($request->get('limit'));
        }

        if(!empty($request->get('skip'))) {
            $suggestheaders->skip($request->get('skip'));
        }

        if(!empty($request->get('not_in'))){
            $suggestheaders->whereNotIn('suggestheaders.suggest_id', json_decode($request->get('not_in'), true));
        }

        $data = [];
        foreach ($suggestheaders->get() as $suggestHeader) {
            $creationDate = !empty($suggestHeader) ? new \DateTime($suggestHeader->creation_date) : null;
            $suggestHeader->creation_date = !empty($creationDate) ? $creationDate->format('d-m-Y H:i') : '';
            $data[] = $suggestHeader;
        }

        return response()->json([
            'data' => $data,
        ], 200);
    }


    function get_suggest(Request $request) {

        $suggestheaders = SuggestHeaders::orderBy('creation_date','desc');

        if(!empty($request->get('room_id'))) {
            $suggestheaders->where('room_id','=',$request->get('room_id'));
        }

        if(!empty($request->get('suggest_id'))) {
            $suggestheaders->where('suggest_id','=',$request->get('suggest_id'));
        }

        if(!empty($request->get('start_date'))) {
            $startDate = new \DateTime($request->get('start_date'));
            $suggestheaders->where('start_date','>=', $startDate->format('Y-m-d 00:00:00'));
        }

        if(!empty($request->get('end_date'))) {
            $startDate = new \DateTime($request->get('end_date'));
            $suggestheaders->where('end_date','<=', $startDate->format('Y-m-d 23:59:59'));
        }

        $data = [];
        foreach ($suggestheaders->get() as $key => $value) {
            $suggestUploads = [];
            foreach ($value->suggestUploads as $upload) {
                $suggestUploads []['bytea_upload'] = !empty($upload->bytea_upload) ? pg_unescape_bytea(stream_get_contents($upload->bytea_upload)) : '';
            }

            $data[] = [
                'suggest_id'        => $value->suggest_id,
                'room_id'           => $value->room_id,
                'room_name'         => $value->roomMaster->room_name,
                'tenant_name'       => $value->roomMaster->tenantMasterTenant->first_name.' '.$value->roomMaster->tenantMasterTenant->last_name,   
                'tenant_phone'      => $value->roomMaster->tenantMasterTenant->phone_number,   
                'suggest_name'      => $value->suggest_name,
                'facility_name'     => $value->facilityMaster->facility_name,
                'suggest_desc'      => $value->suggest_desc,
                'creation_date'     => Date('d-m-Y H:i',strtotime($value->creation_date)),
                'suggest_uploads'   => $suggestUploads,
            ];
        }

        return response()->json([
            'data' => $data,
        ], 200);
    }

    function get_tenant(Request $request) {
        $tenantmaster = TenantMaster::orderBy('first_name');

        if(!empty($request->get('searchTenant'))) {
            $tenantmaster->where(function ($tenantmaster) use ($request) {
                $tenantmaster->where('first_name','ILIKE','%'.$request->get('searchTenant').'%')
                      ->orWhere('middle_name','ILIKE','%'.$request->get('searchTenant').'%')
                      ->orWhere('last_name','ILIKE','%'.$request->get('searchTenant').'%');
                if(in_array(strtoupper($request->get('searchTenant')), [TenantMaster::TENANT, TenantMaster::LANDLORD])){
                    $tenantmaster->orWhere('tenant_type','=',strtoupper($request->get('searchTenant')));
                }
            });
        }

        if(!empty($request->get('first_name'))) {
            $tenantmaster->where('first_name','ILIKE', '%'.$request->get('first_name').'%');
        }

        if(!empty($request->get('tenant_type'))) {
            $tenantmaster->where('tenant_type','=',$request->get('tenant_type'));
        }

        if(!empty($request->get('is_active'))) {
            $tenantmaster->where('is_active','=',$request->get('is_active'));
        }

        if(!empty($request->get('limit'))) {
            $tenantmaster->take($request->get('limit'));
        }

        $data = [];
        foreach ($tenantmaster->get() as $value) {
            $birthDate = !empty($value->birth_date) ? new \DateTime($value->birth_date) : null;
            $value->birth_date = !empty($birthDate) ? $birthDate->format('d-m-Y') : '';
            $data[] = $value;
        }

        return response()->json([
            'data' =>$data,
        ], 200);
    }

    function get_unit(Request $request) {
        $unitmaster = UnitMaster::orderBy('unit_type');

        if(!empty($request->get('searchUnit'))) {
            $unitmaster->where(function ($unitmaster) use ($request) {
                $unitmaster->where('unit_type','ILIKE','%'.$request->get('searchUnit').'%')
                      ->orWhere('unit_desc','ILIKE','%'.$request->get('searchUnit').'%');
            });
        }

        if(!empty($request->get('unit_type'))) {
            $unitmaster->where('unit_type','ILIKE','%'.$request->get('unit_type').'%');
        }

        if(!empty($request->get('limit'))) {
            $unitmaster->take($request->get('limit'));
        }

        if(!empty($request->get('is_active'))) {
            $unitmaster->where('is_active', '=', $request->get('is_active'));
        }

        $data = [];
        foreach ($unitmaster->get() as $unitmaster) {
            $unitmaster->detail = $unitmaster->DetailUnit;
            $data[] = $unitmaster;
        }

        return response()->json([
            'data' => $data,
        ], 200);
    }

    function get_facility(Request $request) {

        $facility = FacilityMaster::orderBy('facility_name');

        if(!empty($request->get('searchFacility'))) {
            $facility->where(function ($facility) use ($request) {
                $facility->where('facility_name','ILIKE','%'.$request->get('searchFacility').'%')
                      ->orWhere('facility_desc','ILIKE','%'.$request->get('searchFacility').'%');
            });
        }

        if(!empty($request->get('facility_name'))) {
            $facility->where('facility_name','ILIKE','%'.$request->get('facility_name').'%');
        }

        if(!empty($request->get('is_active'))) {
            $facility->where('is_active','=',$request->get('is_active'));
        }

        return response()->json([
            'data' => $facility->get(),
        ], 200);
    }

    function get_report_complaint_by_dept(Request $request) {
        $rpt_complaint_by_dept = \DB::table('bima.complaint_headers as ch')
                        ->select('dm.dept_name','sm.subunit_name', \DB::raw('count(complaint_status) as complaint_count'))
                        ->join('bima.complaint_lines as cl', 'cl.complaint_id', '=', 'ch.complaint_id')
                        ->join('bima.detail_units as du', 'du.detail_unit_id', '=', 'cl.detail_unit_id')
                        ->join('bima.subunit_master as sm', 'sm.subunit_id', '=', 'du.subunit_id')
                        ->join('bima.department_master as dm', 'dm.dept_id', '=', 'sm.dept_id')
                        ->where(\DB::raw('ch.creation_date::date'), '>=', Date('Y-m-d', strtotime($request->get('start_date'))))
                        ->where(\DB::raw('ch.creation_date::date'), '<=', Date('Y-m-d', strtotime($request->get('end_date'))))
                        ->where('ch.complaint_status','=','DONE')
                        ->groupBy('dm.dept_name','sm.subunit_name');


        if(!empty($request->get('dept_id')))
            $rpt_complaint_by_dept->where('dm.dept_id', '=', $request->get('dept_id')); 

        return response()->json([
            'data' => $rpt_complaint_by_dept->get(),
        ], 200);
    }

    function get_report_complaint_by_subordinat(Request $request) {
        $data = [];

        if(!empty($request->get('employee_id')) && $request->get('user_type') != 'ADMIN') {
            $employeeSubordinat = EmployeeMaster::where('supervised_id_employee', '=', $request->get('employee_id'))->get();

            foreach ($employeeSubordinat as $key => $value) {
                $data[] = $value->employee_id;

                $employeeSubordinat1 = EmployeeMaster::where('supervised_id_employee', '=', $value->employee_id)->get();

                foreach ($employeeSubordinat1 as $key1 => $value1) {
                	 $data[] = $value1->employee_id;
                }
            }
        }

        $rpt_complaint_by_subordinat = \DB::table('bima.complaint_headers as ch')
                        ->select('ch.employee_id', 'em.first_name', 'em.last_name', 'ch.complaint_desc', 'ch.end_date as finish_date', \DB::raw('ch.end_date - ch.start_date as duration'), 'ch.complaint_rate', 'ch.complaint_note', 'rm.room_name', 'rm.room_desc', 'ch.creation_date')
                        ->join('bima.employee_master as em', 'em.employee_id', '=', 'ch.employee_id')
                        ->join('bima.room_master as rm', 'rm.room_id', '=', 'ch.room_id')
                        ->where(\DB::raw('ch.creation_date::date'), '>=', Date('Y-m-d', strtotime($request->get('start_date'))))
                        ->where(\DB::raw('ch.creation_date::date'), '<=', Date('Y-m-d', strtotime($request->get('end_date'))))
                        ->where('ch.complaint_status','=','DONE');

        if(!empty($request->get('employee_id')) && $request->get('user_type') != 'ADMIN')
            $rpt_complaint_by_subordinat->whereIn('ch.employee_id',$data);

        return response()->json([
            'data' => $rpt_complaint_by_subordinat->get(),
        ], 200);
    }

    function get_report_subordinatscore(Request $request) {
        $data = [];

        if(!empty($request->get('employee_id')) && $request->get('user_type') != 'ADMIN') {
            $employeeSubordinat = EmployeeMaster::where('supervised_id_employee', '=', $request->get('employee_id'))->get();

            foreach ($employeeSubordinat as $key => $value) {
                $data[] = $value->employee_id;

                $employeeSubordinat1 = EmployeeMaster::where('supervised_id_employee', '=', $value->employee_id)->get();

                foreach ($employeeSubordinat1 as $key1 => $value1) {
                	 $data[] = $value1->employee_id;
                }
            }
        }

        $rpt_subordinatscore = \DB::table('bima.complaint_headers as ch')
                        ->select('ch.employee_id', 'em.first_name', 'em.last_name', \DB::raw('count(ch.complaint_status) as completed'), \DB::raw('sum(ch.complaint_rate)::numeric / count(ch.complaint_status)::numeric as rate'), 'eu.bytea_upload')
                        ->join('bima.employee_master as em', 'em.employee_id', '=', 'ch.employee_id')
                        ->join('bima.room_master as rm', 'rm.room_id', '=', 'ch.room_id')
                        ->leftjoin('bima.employee_uploads as eu', 'eu.employee_id', '=', 'ch.employee_id')
                        ->where(\DB::raw('ch.creation_date::date'), '>=', Date('Y-m-d', strtotime($request->get('start_date'))))
                        ->where(\DB::raw('ch.creation_date::date'), '<=', Date('Y-m-d', strtotime($request->get('end_date'))))
                        ->where('ch.complaint_status','=','DONE')
                        ->groupBy('ch.employee_id','em.first_name', 'em.last_name','eu.bytea_upload');

        if(!empty($request->get('employee_id')) && $request->get('user_type') != 'ADMIN')
            $rpt_subordinatscore->whereIn('ch.employee_id',$data);

        $data = [];
        foreach ($rpt_subordinatscore->get() as $key => $value) {
            $data[] = [
                'first_name'           => $value->first_name,
                'last_name'            => $value->last_name,
                'completed'            => $value->completed,
                'rate'                 => $value->rate,
                'bytea_upload'         => (!empty($value->bytea_upload)) ? pg_unescape_bytea(stream_get_contents($value->bytea_upload)) : '',
            ];
        }

        return response()->json([
            'data' => $data,
        ], 200);
    }

    function get_report_subordinatscore_alltime(Request $request) {
        $data = [];

        if(!empty($request->get('employee_id')) && $request->get('user_type') != 'ADMIN') {
            $employeeSubordinat = EmployeeMaster::where('supervised_id_employee', '=', $request->get('employee_id'))->get();

            foreach ($employeeSubordinat as $key => $value) {
                $data[] = $value->employee_id;

                $employeeSubordinat1 = EmployeeMaster::where('supervised_id_employee', '=', $value->employee_id)->get();

                foreach ($employeeSubordinat1 as $key1 => $value1) {
                	 $data[] = $value1->employee_id;
                }
            }
        }

        $rpt_subordinatscore = \DB::table('bima.performance_headers as ph')
                        ->select('ph.employee_id', 'em.first_name', 'em.last_name', 'ph.success_count as completed', \DB::raw('ph.star_count/ ph.success_count as rate'), 'eu.bytea_upload')
                        ->join('bima.employee_master as em', 'em.employee_id', '=', 'ph.employee_id')
                        ->leftjoin('bima.employee_uploads as eu', 'eu.employee_id', '=', 'ph.employee_id');

        if(!empty($request->get('employee_id')) && $request->get('user_type') != 'ADMIN')
            $rpt_subordinatscore->whereIn('ph.employee_id',$data);


        //take(10) dapat dibuang bila data sudah sesuai. 
        //akan error bila karyawan terlalu banyak
        $data = [];
        foreach ($rpt_subordinatscore->take(10)->get() as $key => $value) {
            $data[] = [
                'first_name'           => $value->first_name,
                'last_name'            => $value->last_name,
                'completed'            => $value->completed,
                'rate'                 => $value->rate,
                'bytea_upload'         => (!empty($value->bytea_upload)) ? pg_unescape_bytea(stream_get_contents($value->bytea_upload)) : '',
            ];
        }

        return response()->json([
            'data' => $data,
        ], 200);
    }

    function get_subordinatscore_alltime(Request $request) {
        $data = [];

        if(!empty($request->get('employee_id'))) {
	        $rpt_subordinatscore = \DB::table('bima.performance_headers as ph')
	                        ->select('ph.employee_id', 'em.first_name', 'em.last_name', 'ph.success_count as completed', \DB::raw('coalesce(nullif(ph.star_count,0)/ nullif(ph.success_count,0),0) as rate'), 'eu.bytea_upload')
	                        ->join('bima.employee_master as em', 'em.employee_id', '=', 'ph.employee_id')
	                        ->leftjoin('bima.employee_uploads as eu', 'eu.employee_id', '=', 'ph.employee_id')
	                        ->where('ph.employee_id', '=', $request->get('employee_id'));

	        //take(10) dapat dibuang bila data sudah sesuai. 
	        //akan error bila karyawan terlalu banyak
	        $data = [];
	        foreach ($rpt_subordinatscore->take(10)->get() as $key => $value) {
	            $data[] = [
	                'first_name'           => $value->first_name,
	                'last_name'            => $value->last_name,
	                'completed'            => $value->completed,
	                'rate'                 => $value->rate,
	                'bytea_upload'         => (!empty($value->bytea_upload)) ? pg_unescape_bytea(stream_get_contents($value->bytea_upload)) : '',
	            ];
	        }
	    }

        return response()->json([
            'data' => $data,
        ], 200);
    }

    function get_report_technician(Request $request) {
        $rpt_technician = \DB::table('bima.complaint_headers as ch')
                        ->select('dm.dept_id', 'dm.dept_name', 'ch.employee_id', 'em.first_name', 'em.last_name', \DB::raw('count(complaint_status) as completed'), \DB::raw('sum(ch.complaint_rate)::numeric / count(ch.complaint_status)::numeric as rate'), 'eu.bytea_upload')
                        ->join('bima.employee_master as em', 'em.employee_id', '=', 'ch.employee_id')
                        ->join('bima.department_master as dm', 'dm.dept_id', '=', 'em.dept_id')
                        ->leftjoin('bima.employee_uploads as eu', 'eu.employee_id', '=', 'ch.employee_id')
                        ->where(\DB::raw('ch.creation_date::date'), '>=', Date('Y-m-d', strtotime($request->get('start_date'))))
                        ->where(\DB::raw('ch.creation_date::date'), '<=', Date('Y-m-d', strtotime($request->get('end_date'))))
                        ->where('ch.complaint_status','=','DONE')
                        ->groupBy('dm.dept_id', 'dm.dept_name', 'ch.employee_id', 'em.first_name', 'em.last_name','eu.bytea_upload');


        if(!empty($request->get('dept_id')))
            $rpt_technician->where('dm.dept_id', '=', $request->get('dept_id')); 


        $data = [];
        foreach ($rpt_technician->get() as $key => $value) {
            $data[] = [
                'dept_name'            => $value->dept_name,
                'first_name'           => $value->first_name,
                'last_name'            => $value->last_name,
                'completed'            => $value->completed,
                'rate'                 => $value->rate,
                'bytea_upload'         => (!empty($value->bytea_upload)) ? pg_unescape_bytea(stream_get_contents($value->bytea_upload)) : '',
            ];
        }

        return response()->json([
            'data' => $data,
        ], 200);
    }

    function get_hist_trans(Request $request) {
        
        if(!empty($request->get('lastid')))
            $gethisttrans = ComplaintTransHist::where('complaint_hist_id','>',$request->get('lastid'));
        else
            $gethisttrans = ComplaintTransHist::orderBy('complaint_hist_id', 'desc')->take(10);


        foreach ($gethisttrans->get() as $key => $value) {
            $data[] = [
                'complaint_hist_id' => $value->complaint_hist_id,
                'room_name'         => $value->complaintHeader->roomMaster->room_name,
                'first_name_tenant' => $value->complaintHeader->roomMaster->tenantMasterTenant->first_name,   
                'last_name_tenant'  => $value->complaintHeader->roomMaster->tenantMasterTenant->last_name,
                'complaint_hist_status'  => $value->complaint_hist_status,
                'complaint_role'    => $value->complaint_role,
                'first_employee_name'    => (!empty($value->complaintHeader->employeeMaster->first_name)) ? $value->complaintHeader->employeeMaster->first_name : '',
                'last_employee_name'    => (!empty($value->complaintHeader->employeeMaster->last_name)) ? $value->complaintHeader->employeeMaster->last_name : '',
                'available_start_date'  => (!empty($value->complaintHeader->available_start_date)) ? Date('d-M-Y H:i:s',strtotime($value->complaintHeader->available_start_date)) : '',
                'available_end_date'     => (!empty($value->complaintHeader->available_end_date)) ? Date('d-M-Y H:i:s',strtotime($value->complaintHeader->available_end_date)) : '',
                'complaint_rate'        => (!empty($value->complaintHeader->complaint_rate)) ? $value->complaintHeader->complaint_rate : '',
                'complaint_note'        => (!empty($value->complaintHeader->complaint_note)) ? $value->complaintHeader->complaint_note : ' - ',
                'complaint_cost'        => (!empty($value->complaintHeader->complaint_cost)) ? number_format($value->complaintHeader->complaint_cost,2) : '',
                'cancel_note'        => (!empty($value->complaintHeader->cancel_note)) ? $value->complaintHeader->cancel_note : ' - ',
                'creation_date'     => Date('d-M-Y H:i:s',strtotime($value->creation_date))
            ];
        }

        if(!empty($data)) {
            foreach ($data as $key => $row) {
                $attack[$key]  = $row['complaint_hist_id'];
            }

            // Sort the data with attack ascending
            array_multisort($attack, SORT_ASC, $data);
        } else {
            $data = array();
        }

        return response()->json([
            'data' => $data,
        ], 200);
    }


    ///////////////////////////////////////////////////////////////////////////////////////////




    //////////////////////// FUNCTION UTAMA //////////////////////////////////////////////////

    function submitComplaint(Request $request) {
        \DB::beginTransaction();
        $header_input   = json_decode($request->get('header_input'), true);
        $line_input     = json_decode($request->get('line_input'), true);
        $upload_input   = json_decode($request->get('upload_input'), true);

        $header_output = $this->opr_complaint_headers($header_input, $request->get('is_save'));
        if(!empty($header_output['err'])){
            return response()->json([
                    'msg'   => 'Error header',
                    'err'   => $header_output['err'],
                ], 200);
        }
        if(!strpos($header_output,'ERROR')) {
            $line_output   = $this->opr_complaint_lines($line_input,$header_output, $header_input['updated_by']);

            $upload_output = $this->opr_complaint_uploads($upload_input, $header_output, $header_input['updated_by']);
            $other_output  = '';

            if(!$request->get('is_save')){
                $other_output  = $this->opr_complaint_trans_hist(
                    $header_output,
                    $header_input['updated_by'],
                    $header_input['complaint_status'],
                    (!empty($header_input['complaint_role'])) ? $header_input['complaint_role'] : 'TENANT',
                    (!empty($header_input['cancel_note'])) ? $header_input['cancel_note'] : null);
            }

            $notif_output = '';

            $notif_output = $this->opr_complaint_notification($header_input, $header_output, $line_input, $request->get('is_save'));

            if(!strpos($line_output,'ERROR') && !strpos($other_output,'ERROR') && !strpos($upload_output,'ERROR') && !strpos($notif_output,'ERROR')) {
                \DB::commit();

                return response()->json([
                    'data' => $header_output,
                    'msg'   => 'Berhasil'
                ], 200);
            }
            else if(!strpos($line_output,'ERROR')) {
                \DB::rollback();
                return response()->json([
                    'msg'   => 'gagal simpan complaint line',
                    'err'   => $line_output
                ], 200);
            }
            else if(!strpos($other_output,'ERROR')) {
                \DB::rollback();
                return response()->json([
                    'msg'   => 'gagal simpan complaint history',
                    'err'   => $other_output
                ], 200);
            }
            else if(!strpos($upload_output,'ERROR')) {
                \DB::rollback();
                return response()->json([
                    'msg'   => 'gagal simpan complaint upload',
                    'err'   => $upload_output
                ], 200);
            }else if(!strpos($notif_output,'ERROR')) {
                \DB::rollback();
                return response()->json([
                    'msg'   => 'gagal simpan complaint notif',
                    'err'   => $notif_output
                ], 200);
            }
        } else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan complaint header',
                'err'   => $header_output
            ], 200);
        }
    }

    function get_dept_id($detail_unit_id) {
        $department_master = \DB::table('bima.department_master')
                                ->select('department_master.dept_id')
                                ->join('bima.subunit_master', 'subunit_master.dept_id', '=', 'department_master.dept_id')
                                ->join('bima.detail_units', 'detail_units.subunit_id', '=', 'subunit_master.subunit_id')
                                ->where('detail_units.detail_unit_id', '=', $detail_unit_id)
                                ->first();
        return $department_master->dept_id;
    }

    // function submitBadgeRoom(Request $request) {
    //     \DB::beginTransaction();

    //     $header_output = $this->opr_badge_room_headers($request->get('header_input'));

    //     if(!strpos($header_output,'ERROR')) {
    //         \DB::commit();
    //         return response()->json([
    //             'data' => $header_output,
    //             'msg'   => 'Berhasil'
    //         ], 200);
    //     }
    //     else {
    //         \DB::rollback();
    //         return response()->json([
    //             'msg'   => 'gagal simpan badge room',
    //             'err'   => $header_output
    //         ], 200);
    //     }
    // }

    // function submitUserRoom(Request $request) {
    //     \DB::beginTransaction();

    //     $header_output = $this->opr_badge_user_headers($request->get('header_input'));

    //     if(!strpos($header_output,'ERROR')) {
    //         \DB::commit();
    //         return response()->json([
    //             'data' => $header_output,
    //             'msg'   => 'Berhasil'
    //         ], 200);
    //     }
    //     else {
    //         \DB::rollback();
    //         return response()->json([
    //             'msg'   => 'gagal simpan badge user',
    //             'err'   => $header_output
    //         ], 200);
    //     }
    // }

    function submitDepartmentMaster(Request $request) {
        \DB::beginTransaction();

        $header_output = $this->opr_department_master($request->get('header_input'));

        if(!strpos($header_output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $header_output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan department master',
                'err'   => $header_output
            ], 200);
        }
    }

    function submitDetailUnit(Request $request) {
        \DB::beginTransaction();

        $header_output = $this->opr_detail_units($request->get('header_input'));

        if(!strpos($header_output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $header_output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan detail unit',
                'err'   => $header_output
            ], 200);
        }
    }

    function submitEmployeeMaster(Request $request) {
        \DB::beginTransaction();

        $header_output = $this->opr_employee_master($request->get('header_input'));


        if(!strpos($header_output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $header_output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan employee master',
                'err'   => $header_output
            ], 200);
        }
    }

    function submitEmployeeLanguage(Request $request) {
        \DB::beginTransaction();

        $output = $this->opr_employee_language($request);


        if(!strpos($output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan employee language',
                'err'   => $output
            ], 200);
        }
    }

    function submitEmployeeNotificationHeaders(Request $request) {
        \DB::beginTransaction();

        $header_output = $this->opr_employee_notification_headers($request);

        if(!strpos($header_output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $header_output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan employee notification header',
                'err'   => $header_output
            ], 200);
        }
    }

    function submitReadEmployeeNotification(Request $request) {
        \DB::beginTransaction();
        $output = '';

        $employeeNotification = EmployeeNotificationHeaders::where('employee_notification_headers.complaint_id', '=', $request->get('complaint_id'))
                                    ->where('employee_notification_headers.employee_id', '=', $request->get('employee_id'))
                                    ->get();

        foreach ($employeeNotification as $notif) {
            $notif->is_read = 'Y';

             try{
                $notif->save();
                $output = 'true';
            } catch(\Exception $e) {
                $output = $e->getMessage();
            }
        }


        if(!strpos($output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan read employee notification',
                'err'   => $output
            ], 200);
        }
    }

    function submitReadEmployeeNotificationById(Request $request) {
        \DB::beginTransaction();
        $output = '';

        $employeeNotification = EmployeeNotificationHeaders::find($request->get('notification_id'));

        $employeeNotification->is_read = 'Y';

         try{
            $employeeNotification->save();
            $output = 'true';
        } catch(\Exception $e) {
            $output = $e->getMessage();
        }

        if(!strpos($output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan read employee notification',
                'err'   => $output
            ], 200);
        }
    }

    function submitEmployeeUploads(Request $request) {
        \DB::beginTransaction();
        $header_input   = json_decode($request->get('header_input'), true);
        $header_output = $this->opr_employee_uploads($header_input);

        if(!strpos($header_output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $header_output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan employee upload',
                'err'   => $header_output
            ], 200);
        }
    }

    function submitRoomMaster(Request $request) {
        \DB::beginTransaction();

        $header_output = $this->opr_room_master($request->get('header_input'));

        if(!strpos($header_output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $header_output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan room master',
                'err'   => $header_output
            ], 200);
        }
    }

    function submitRoomNotificationHeaders(Request $request) {
        \DB::beginTransaction();

        $header_output = $this->opr_room_notification_headers($request);

        if(!strpos($header_output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $header_output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan room notification header',
                'err'   => $header_output
            ], 200);
        }
    }

    function submitReadRoomNotification(Request $request) {
        \DB::beginTransaction();
        $output = '';

        $roomNotification = RoomNotificationHeaders::where('room_notification_headers.complaint_id', '=', $request->get('complaint_id'))->get();

        foreach ($roomNotification as $notif) {
            $notif->is_read = 'Y';

             try{
                $notif->save();
                $output = 'true';
            } catch(\Exception $e) {
                $output = $e->getMessage();
            }
        }


        if(!strpos($output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan read employee notification',
                'err'   => $output
            ], 200);
        }
    }

    function submitReadRoomNotificationById(Request $request) {
        \DB::beginTransaction();
        $output = '';

        $roomNotification = RoomNotificationHeaders::find($request->get('notification_id'));

        $roomNotification->is_read = 'Y';

         try{
            $roomNotification->save();
            $output = 'true';
        } catch(\Exception $e) {
            $output = $e->getMessage();
        }


        if(!strpos($output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'Gagal simpan read employee notification',
                'err'   => $output
            ], 200);
        }
    }

    function submitSubunitMaster(Request $request) {
        \DB::beginTransaction();
        $header_output = $this->opr_subunit_master($request->get('header_input'));

        if(!strpos($header_output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $header_output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan subunit master',
                'err'   => $header_output
            ], 200);
        }
    }

    function submitSuggest(Request $request) {
        \DB::beginTransaction();

        $header_input   = json_decode($request->get('header_input'), true);
        $upload_input   = json_decode($request->get('upload_input'), true);

        // dd($header_input);
        $header_output = $this->opr_suggest_headers($header_input);


        if(!strpos($header_output,'ERROR')) {
            $upload_output   = $this->opr_suggest_uploads($upload_input, $header_output, $header_input['updated_by']);
            if(!strpos($upload_output,'ERROR')) {
                $notification_output = $this->opr_suggest_notification($header_input, $header_output);
                if(!strpos($notification_output,'ERROR')) {
                    \DB::commit();
                    return response()->json([
                        'data' => $header_output,
                        'msg'   => 'Berhasil'
                    ], 200);

                }else{
                    \DB::rollback();
                    return response()->json([
                        'msg'   => 'gagal simpan suggest notification',
                        'err'   => $upload_output
                    ], 200);
                }
            }else{
                \DB::rollback();
                return response()->json([
                    'msg'   => 'gagal simpan suggest upload',
                    'err'   => $upload_output
                ], 200);
                
            }
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan suggest header',
                'err'   => $header_output
            ], 200);
        }
    }

    function submitTenantMaster(Request $request) {
        \DB::beginTransaction();

        $header_output = $this->opr_tenant_master($request->get('header_input'));

        if(!strpos($header_output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $header_output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan tenant master',
                'err'   => $header_output
            ], 200);
        }
    }

    function submitUnitMaster(Request $request) {
        \DB::beginTransaction();

        $header_output = $this->opr_unit_master($request->get('header_input'));

        $line_output   = $this->opr_detail_units($request->get('line_input'), $header_output, $request->get('header_input')['updated_by']);

        if(!strpos($header_output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $header_output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan unit master',
                'err'   => $header_output
            ], 200);
        }
    }

    function submitFacilityMaster(Request $request) {
        \DB::beginTransaction();

        $header_output = $this->opr_facility_master($request->get('header_input'));

        if(!strpos($header_output,'ERROR')) {
            \DB::commit();
            return response()->json([
                'data' => $header_output,
                'msg'   => 'Berhasil'
            ], 200);
        }
        else {
            \DB::rollback();
            return response()->json([
                'msg'   => 'gagal simpan facility master',
                'err'   => $header_output
            ], 200);
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////


    //////////////////////// FUNCTION INSER UPDATE PER TABLE /////////////////////////////////

    function opr_badge_room_headers($id,$room_id,$notification_count) {
        if($id == -1) {
            $badgeroomheaders = new BadgeRoomHeaders();
            $badgeroomheaders->room_id = $header_input['room_id'];
        } else {
            $badgeroomheaders = BadgeRoomHeaders::find($id);
            $badgeroomheaders->last_update_date = Date('Y-m-d H:i:s');
            $badgeroomheaders->notification_count = $badgeroomheaders->notification_count + $notification_count;
        }

        try{
            $badgeroomheaders->save();
            return $badgeroomheaders->badge_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_badge_user_headers($id,$employee_id,$outstanding_count,$notification_count,$tasklist_count) {
        if($id == -1) {
            $badgeuserheaders = new BadgeUserHeaders();
            $badgeuserheaders->employee_id = $employee_id;
        } else {
            $badgeuserheaders = BadgeUserHeaders::find($id);
            $badgeuserheaders->last_update_date = Date('Y-m-d H:i:s');
            $badgeuserheaders->outstanding_count = $badgeuserheaders->outstanding_count + $outstanding_count;
            $badgeuserheaders->notification_count = $badgeuserheaders->notification_count + $notification_count;
            $badgeuserheaders->tasklist_count = $badgeuserheaders->tasklist_count + $tasklist_count;
        }

        try{
            $badgeuserheaders->save();
            return $badgeuserheaders->badge_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }


    function opr_complaint_headers($header_input, $is_save) {
        $id = $header_input['complaint_id'];
        $now = new \DateTime();

        if($id == -1) {
            $complaintheaders = new ComplaintHeaders();
            $complaintheaders->created_by  = $header_input['updated_by'];
        } else {
            $complaintheaders = ComplaintHeaders::find($id);
            $complaintheaders->last_update_date = Date('Y-m-d H:i:s');
            $complaintheaders->last_updated_by  = $header_input['updated_by'];

            if($complaintheaders->complaint_status != $header_input['current_status']){
                return [
                    'err'    => 'Komplain sudah diupate user lain',
                    'data'   => $complaintheaders->complaint_id,
                ];
            }

            if(!empty($header_input['complaint_status']) && $header_input['complaint_status'] == ComplaintHeaders::INSPECTING && $header_input['complaint_status'] != $complaintheaders->complaint_status){
                if($complaintheaders->employee_id != null){
                    return [
                        'err'    => 'Komplain sudah diambil oleh teknisi lain!',
                        'data'   => $complaintheaders->complaint_id,
                    ];
                }
                $complaintheaders->inspecting_date = $now;
                $complaintheaders->employee_id     = $header_input['employee_id'];
            }else if(!empty($header_input['complaint_status']) && $header_input['complaint_status'] == ComplaintHeaders::WAITING_APPROVAL && $header_input['complaint_status'] != $complaintheaders->complaint_status){
                $complaintheaders->waiting_approval_date = $now;
            }else if(!empty($header_input['complaint_status']) && $header_input['complaint_status'] == ComplaintHeaders::APPROVED && $header_input['complaint_status'] != $complaintheaders->complaint_status){
                $complaintheaders->approved_date = $now;
            }else if(!empty($header_input['complaint_status']) && $header_input['complaint_status'] == ComplaintHeaders::CANCEL && $header_input['complaint_status'] != $complaintheaders->complaint_status){
                $complaintheaders->cancel_date = $now;
            }else if(!empty($header_input['complaint_status']) && $header_input['complaint_status'] == ComplaintHeaders::OPEN && $header_input['complaint_status'] != $complaintheaders->complaint_status){
                $complaintheaders->employee_id = null;
            }else if(!empty($header_input['complaint_status']) && $header_input['complaint_status'] == ComplaintHeaders::PROGRESS && $header_input['complaint_status'] != $complaintheaders->complaint_status){
                $complaintheaders->start_date       = $now;
            }else if(!empty($header_input['complaint_status']) && $header_input['complaint_status'] == ComplaintHeaders::DONE && $header_input['complaint_status'] != $complaintheaders->complaint_status){
                $complaintheaders->end_date       = $now;
            }else if(!empty($header_input['complaint_status']) && $header_input['complaint_status'] == ComplaintHeaders::DONE){
                $complaintheaders->complaint_rate = $header_input['complaint_rate'];
                $complaintheaders->complaint_note = $header_input['complaint_note'];
            }
        }

        foreach ($header_input as $key => $value) {
            if($key != 'complaint_id' && $key != 'complaint_role' && $key != 'updated_by' && $key != 'complaint_cost_detail' && $key != 'complaint_rate' && $key != 'complaint_done' && $key != 'employee_id' && $key != 'current_status'){
                $complaintheaders->$key = $value;
            }
            if($key == 'available_start_date' || $key == 'available_end_date'){
                $date = !empty($value) ? new \DateTime($value) : null;
                $complaintheaders->$key = !empty($date) ? $date->format('Y-m-d H:i:s') : null;
            }
            if($key == 'complaint_cost_detail'){
                $foto       = base64_decode($value);
                $foto       = pg_escape_bytea($value);
                $complaintheaders->$key = $foto;
            }
        }

        if(!empty($header_input['employee_id'])){
            if($header_input['complaint_status'] == 'PROGRESS' && !$is_save) {
                $this->opr_performance_headers($header_input['employee_id'],$header_input['employee_id'],1,0,0,0);
            } else if($header_input['complaint_status'] == 'CANCEL' && !$is_save) {
                $this->opr_performance_headers($header_input['employee_id'],$header_input['employee_id'],0,0,1,0);
            } else if($header_input['complaint_status'] == 'DONE' && !$is_save) {
                $this->opr_performance_headers($header_input['employee_id'],$header_input['employee_id'],0,1,0,0);
            } else if($header_input['complaint_status'] == 'DONE' && !empty($header_input['rate']) && $is_save) {
                $this->opr_performance_headers($header_input['employee_id'],$header_input['employee_id'],0,0,0,$header_input['complaint_rate']);
            }
        }

        try{
            $complaintheaders->save();
            return $complaintheaders->complaint_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_complaint_lines($line_input = [], $complaint_id, $updated_by) {
        if($line_input === null){
            $line_input = [];
        }

        foreach ($line_input as $key) {
            $id = $key['complaint_line_id'];

            if($id == -1) {
                $complaintlines = new ComplaintLines();
                $complaintlines->created_by = $updated_by;
            } else {
                $complaintlines = ComplaintLines::find($id);
                $complaintlines->last_update_date = Date('Y-m-d H:i:s');
                $complaintlines->last_updated_by = $updated_by;
            }

            foreach ($key as $key1 => $value) {
                if($key1 != 'complaint_line_id')
                    $complaintlines->$key1 = $value;
                    $complaintlines->complaint_id = $complaint_id;
            }

            try{
                $complaintlines->save();
            } catch(\Exception $e) {
                return $e->getMessage();
            }
        }

        return 'Suc';
    }

    function opr_complaint_trans_hist($complaint_id, $created_by, $complaint_hist_status, $complaint_role, $complaint_hist_desc) {

        $complainttranshist = new ComplaintTransHist();
        $complainttranshist->complaint_id = $complaint_id;
        $complainttranshist->created_by = $created_by;
        $complainttranshist->complaint_hist_status = $complaint_hist_status;
        $complainttranshist->complaint_role = $complaint_role;
        $complainttranshist->complaint_hist_desc = $complaint_hist_desc;

        try{
            $complainttranshist->save();
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }


    function opr_complaint_notification($header_input, $complaint_id, $line_input, $is_save) {
        if($is_save != 'true' || $header_input['complaint_id'] == -1){
            if($header_input['complaint_status'] == ComplaintHeaders::OPEN && !empty($header_input['room_id'])){
                $dept_id = $this->get_dept_id($line_input[0]['detail_unit_id']);
                $users = EmployeeMaster::where('user_type', '=', 'TEKNISI')
                                        ->where('dept_id', '=', $dept_id)
                                        ->get();

                foreach ($users as $user) {
                    $room = $this->get_room_model($header_input['room_id']);

                    $notif = new EmployeeNotificationHeaders();
                    $notif->notification_title = $header_input['complaint_id'] == -1 ? 'Komplain Baru' : 'Reject Komplain';
                    $notif->notification_desc  = $header_input['complaint_id'] == -1 ? 'Komplain baru telah dibuat oleh kamar '.$room->room_name : 'Komplain kamar '.$room->room_name .' telah direject, anda dapat mengambil komplain ini.';
                    $notif->notification_type  = EmployeeNotificationHeaders::COMPLAINT;
                    $notif->complaint_id       = $complaint_id;
                    $notif->employee_id        = $user->employee_id;
                    $notif->created_by         = $header_input['updated_by'];
                    try{
                        $notif->save();
                    } catch(\Exception $e) {
                        return $e->getMessage();
                    }
                }
            
            }else if($header_input['complaint_status'] == ComplaintHeaders::INSPECTING && !empty($header_input['room_id'] && !empty($header_input['employee_id']))){
                $teknisi = EmployeeMaster::find($header_input['employee_id']);
                $notif = new RoomNotificationHeaders();
                $notif->notification_title = 'Inspeksi Komplain';
                $notif->notification_desc  = 'Komplain anda akan ditangani oleh '.$teknisi->first_name.' '.$teknisi->last_name.'.';
                $notif->notification_type  = EmployeeNotificationHeaders::COMPLAINT;
                $notif->complaint_id       = $complaint_id;
                $notif->room_id            = $header_input['room_id'];
                $notif->created_by         = $header_input['updated_by'];
                try{
                    $notif->save();
                } catch(\Exception $e) {
                    return $e->getMessage();
                }
            }else if($header_input['complaint_status'] == ComplaintHeaders::COSTING && !empty($header_input['room_id'] && !empty($header_input['employee_id']))){
                $notif = new RoomNotificationHeaders();
                $notif->notification_title = 'Biaya Komplain';
                $notif->notification_desc  = 'Komplain anda dalam proses perhitungan biaya.';
                $notif->notification_type  = EmployeeNotificationHeaders::COMPLAINT;
                $notif->complaint_id       = $complaint_id;
                $notif->room_id            = $header_input['room_id'];
                $notif->created_by         = $header_input['updated_by'];
                try{
                    $notif->save();
                } catch(\Exception $e) {
                    return $e->getMessage();
                }

                $users = EmployeeMaster::where('user_type', '=', 'ADMIN')
                                        ->get();

                $room = $this->get_room_model($header_input['room_id']);
                
                foreach ($users as $user) {
                    $notif = new EmployeeNotificationHeaders();
                    $notif->notification_title = 'Komplaint Costing';
                    $notif->notification_desc  = 'Komplain kamar '.$room->room_name.' membutuhkan perhitungan biaya';
                    $notif->notification_type  = EmployeeNotificationHeaders::COMPLAINT;
                    $notif->complaint_id       = $complaint_id;
                    $notif->employee_id        = $user->employee_id;
                    $notif->created_by         = $header_input['updated_by'];
                    try{
                        $notif->save();
                    } catch(\Exception $e) {
                        return $e->getMessage();
                    }
                }

            }else if($header_input['complaint_status'] == ComplaintHeaders::WAITING_APPROVAL && !empty($header_input['room_id'] && !empty($header_input['employee_id']))){
                $room  = $this->get_room_model($header_input['room_id']);

                $notif = new EmployeeNotificationHeaders();
                $notif->notification_title = 'Menunggu Persetujuan';
                $notif->notification_desc  = 'Komplain kamar '.$room->room_name.' menunggu proses persetujuan penghuni.';
                $notif->notification_type  = EmployeeNotificationHeaders::COMPLAINT;
                $notif->complaint_id       = $complaint_id;
                $notif->employee_id        = $header_input['employee_id'];
                $notif->created_by         = $header_input['updated_by'];
                try{
                    $notif->save();
                } catch(\Exception $e) {
                    return $e->getMessage();
                }

                $notif = new RoomNotificationHeaders();
                $notif->notification_title = 'Meminta Persetujuan';
                $notif->notification_desc  = 'Perhitungan biaya sudah selesai, silahkan setujui untuk melanjutkan proses pengerjaan.';
                $notif->notification_type  = EmployeeNotificationHeaders::COMPLAINT;
                $notif->complaint_id       = $complaint_id;
                $notif->room_id            = $header_input['room_id'];
                $notif->created_by         = $header_input['updated_by'];
                try{
                    $notif->save();
                } catch(\Exception $e) {
                    return $e->getMessage();
                }
            }else if($header_input['complaint_status'] == ComplaintHeaders::APPROVED && !empty($header_input['room_id'] && !empty($header_input['employee_id']))){
                $room  = $this->get_room_model($header_input['room_id']);

                $notif                     = new EmployeeNotificationHeaders();
                $notif->notification_title = 'Biaya Disetujui';
                $notif->notification_desc  = 'Biaya komplain kamar '.$room->room_name.' telah disetujui, anda dapat melakukan pengerjaan.';
                $notif->notification_type  = EmployeeNotificationHeaders::COMPLAINT;
                $notif->complaint_id       = $complaint_id;
                $notif->employee_id        = $header_input['employee_id'];
                $notif->created_by         = $header_input['updated_by'];
                try{
                    $notif->save();
                } catch(\Exception $e) {
                    return $e->getMessage();
                }
            }else if($header_input['complaint_status'] == ComplaintHeaders::PROGRESS && !empty($header_input['room_id'] && !empty($header_input['employee_id']))){
                $notif                     = new RoomNotificationHeaders();
                $notif->notification_title = 'Komplain Dikerjakan';
                $notif->notification_desc  = 'Komplain anda sedang dikerjakan.';
                $notif->notification_type  = EmployeeNotificationHeaders::COMPLAINT;
                $notif->complaint_id       = $complaint_id;
                $notif->room_id            = $header_input['room_id'];
                $notif->created_by         = $header_input['updated_by'];
                try{
                    $notif->save();
                } catch(\Exception $e) {
                    return $e->getMessage();
                }
            }else if($header_input['complaint_status'] == ComplaintHeaders::DONE && !empty($header_input['room_id'] && !empty($header_input['employee_id']))){
                $notif                     = new RoomNotificationHeaders();
                $notif->notification_title = 'Komplain Selesai';
                $notif->notification_desc  = 'Komplain anda telah selesai dikerjakan, silahkan melakukan penilaian terhadap teknisi. Terima kasih.';
                $notif->notification_type  = EmployeeNotificationHeaders::COMPLAINT;
                $notif->complaint_id       = $complaint_id;
                $notif->room_id            = $header_input['room_id'];
                $notif->created_by         = $header_input['updated_by'];
                try{
                    $notif->save();
                } catch(\Exception $e) {
                    return $e->getMessage();
                }
            }else if($header_input['complaint_status'] == ComplaintHeaders::CANCEL && !empty($header_input['room_id'] && !empty($header_input['employee_id']))){
                $room  = $this->get_room_model($header_input['room_id']);

                $notif                     = new EmployeeNotificationHeaders();
                $notif->notification_title = 'Komplain Dibatalkan';
                $notif->notification_desc  = 'Komplain kamar '.$room->room_name.' telah dibatalkan oleh penghuni.';
                $notif->notification_type  = EmployeeNotificationHeaders::COMPLAINT;
                $notif->complaint_id       = $complaint_id;
                $notif->employee_id        = $header_input['employee_id'];
                $notif->created_by         = $header_input['updated_by'];
                try{
                    $notif->save();
                } catch(\Exception $e) {
                    return $e->getMessage();
                }
            }
            
        }elseif($header_input['complaint_status'] == ComplaintHeaders::DONE && $is_save == 'true' && !empty($header_input['room_id'])){
            $room  = $this->get_room_model($header_input['room_id']);
            $notif                     = new EmployeeNotificationHeaders();
            $notif->notification_title = 'Penilaian Komplain';
            $notif->notification_desc  = 'Komplain kamar '.$room->room_name.' telah dinilai oleh penghuni.';
            $notif->notification_type  = EmployeeNotificationHeaders::COMPLAINT;
            $notif->complaint_id       = $complaint_id;
            $notif->employee_id        = $header_input['employee_id'];
            $notif->created_by         = $header_input['updated_by'];
            try{
                $notif->save();
            } catch(\Exception $e) {
                return $e->getMessage();
            }
        }
        
        return 'Suc';
    }

    public function get_room_model($roomId){
        $room = RoomMaster::find($roomId);
        return $room;
    }

    public function get_employee_model($employeeId){
        $employee = EmployeeMaster::find($employeeId);
        return $employee;
    }



    function opr_complaint_uploads($header_inputs, $complain_id, $updated_by) {
        if($header_inputs === null){
            $header_inputs = [];
        }
        foreach ($header_inputs as $header_input) {
            $id = $header_input['upload_id'];
            if($id == -1) {
                $complaintupload = new ComplaintUploads();
                $complaintupload->complaint_id = $complain_id;
                $complaintupload->created_by = $updated_by;
            } else {
                $complaintupload = ComplaintUploads::find($id);
                $complaintupload->last_update_date = Date('Y-m-d H:i:s');
                $complaintupload->last_updated_by  = $updated_by;
            }

            foreach ($header_input as $key => $value) {
                if($key != 'upload_id')
                    $complaintupload->$key = $value;
                if($key == 'bytea_upload'){
                    $foto = base64_decode($value);
                    $foto = pg_escape_bytea($value);
                    $complaintupload->$key = $foto;
                }
            }
            try{
                $complaintupload->save();
            } catch(\Exception $e) {
                return $e->getMessage();
            }
        }
        return 'Suc';
        
    }

    function opr_suggest_uploads($upload_input, $suggest_id, $updated_by) {
        if($upload_input === null){
            $upload_input = [];
        }
        foreach ($upload_input as $header_input) {
            $id = $header_input['upload_id'];
            if($id == -1) {
                $suggestupload = new SuggestUploads();
                $suggestupload->suggest_id = $suggest_id;
                $suggestupload->created_by = $updated_by;
            } else {
                $suggestupload = SuggestUploads::find($id);
                $suggestupload->last_update_date = Date('Y-m-d H:i:s');
                $suggestupload->last_updated_by  = $updated_by;
            }

            foreach ($header_input as $key => $value) {
                if($key != 'upload_id')
                    $suggestupload->$key = $value;
                if($key == 'bytea_upload'){
                    $foto = base64_decode($value);
                    $foto = pg_escape_bytea($value);
                    $suggestupload->$key = $foto;
                }
            }
            try{
                $suggestupload->save();
            } catch(\Exception $e) {
                return $e->getMessage();
            }
        }
        return 'Suc';
        
    }

    function opr_department_master($header_input) {
        $id = $header_input['dept_id'];

        if($id == -1) {
            $departmentmaster = new DepartmentMaster();
            $departmentmaster->created_by = $header_input['updated_by'];
        } else {
            $departmentmaster = DepartmentMaster::find($id);
            $departmentmaster->last_update_date = Date('Y-m-d H:i:s');
            $departmentmaster->last_updated_by = $header_input['updated_by'];
        }

        foreach ($header_input as $key => $value) {
            if($key != 'dept_id' && $key != 'updated_by' && $key != '_token')
                $departmentmaster->$key = $value;
        }

        $is_active = empty($header_input['is_active']) ? 'N' : 'Y';
        $departmentmaster->is_active  = $is_active;

        try{
            $departmentmaster->save();
            return $departmentmaster->dept_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_detail_units($header_input, $unit_id, $updated_by) {
        $unit = UnitMaster::find($unit_id);
        $unit->DetailUnit()->delete();
        foreach ($header_input as $value) {
            $detailUnit             = new DetailUnit();

            $detailUnit->unit_id    = $unit_id;
            $detailUnit->subunit_id = $value;
            $detailUnit->created_by = $updated_by;
            $detailUnit->save();
        }

        try{
                $detailunit->save();
                return $unit->unit_id;
            } catch(\Exception $e) {
                return $e->getMessage();
            }
    }

    function opr_employee_language($request) {
        
            $employeemaster = EmployeeMaster::find($request['employee_id']);
            $employeemaster->last_update_date = Date('Y-m-d H:i:s');
            $employeemaster->last_updated_by  = $request['employee_id'];
            $employeemaster->language         = $request['language'];

        try{
            $employeemaster->save();
            return $employeemaster->employee_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_employee_master($header_input) {

        $id = $header_input['employee_id'];

        if($id == -1) {
            $employeemaster = new EmployeeMaster();
            $employeemaster->created_by  = $header_input['updated_by'];
            $employeemaster->api_token   = $this->generateRandomString();
        } else {
            $employeemaster = EmployeeMaster::find($id);
            $employeemaster->last_update_date = Date('Y-m-d H:i:s');
            $employeemaster->last_updated_by  = $header_input['updated_by'];
        }

        foreach ($header_input as $key => $value) {
            if($key != 'employee_id' && $key != 'updated_by' && $key != '_token' && $key != 'upload_id' && $key != 'supervised_employee_name'){
                $employeemaster->$key = $value;
            }
            if($key == 'birth_date'){
                $date = new \DateTime($value);
                $employeemaster->$key = $date->format('Y-m-d');
            }
        }

        $is_active = empty($header_input['is_active']) ? 'N' : 'Y';
        $employeemaster->is_active  = $is_active;
        $employeemaster->passwd     = 123456;

        try{
            $employeemaster->save();

            if($id == -1) {
                $this->opr_performance_headers(-1,$employeemaster->employee_id,0,0,0,0);
                $this->opr_badge_user_headers(-1,$employeemaster->employee_id,0,0,0);
            }
            return $employeemaster->employee_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_employee_notification_headers(Request $request) {
        $id = $request->get('notification_id');

        if($id == -1) {
            $employeenotificationheaders = new EmployeeNotificationHeaders();
        } else {
            $employeenotificationheaders = EmployeeNotificationHeaders::find($id);
            $employeenotificationheaders->last_updated_by  = $request->get('updated_by');
            $employeenotificationheaders->last_update_date = Date('Y-m-d H:i:s');
        }
        foreach ($request->all() as $key => $value) {
            if($key != 'notification_id' && $key != 'api_token' && $key != 'updated_by')
                $employeenotificationheaders->$key = $value;
        }

        try{
            $employeenotificationheaders->save();
            return !empty($employeenotificationheaders->complaintHeader) ? $employeenotificationheaders->complaintHeader->complaint_status : 'Notif Umum';
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_employee_uploads($header_input) {
        $id = $header_input['upload_id'];

        if($id == -1) {
            $employeeupload = new EmployeeUpload();
            $employeeupload->employee_id = $header_input['employee_id'];
        } else {
            $employeeupload = EmployeeUpload::find($id);
            $employeeupload->last_update_date = Date('Y-m-d H:i:s');
            $employeeupload->last_updated_by  = $header_input['updated_by'];
        }

        foreach ($header_input as $key => $value) {
            if($key != 'upload_id' && $key != 'employee_id' && $key != 'updated_by')
                $employeeupload->$key = $value;

            if($key == 'bytea_upload'){
                $foto = base64_decode($value);
                $foto = pg_escape_bytea($value);
                $employeeupload->$key = $foto;
            }
        }

        try{
            $employeeupload->save();
            return 'Suc';
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_performance_headers($id,$employee_id,$action_count,$success_count,$cancel_count,$star_count) {
        if($id == -1) {
            $performanceheaders = new PerformanceHeaders();
            $performanceheaders->employee_id = $employee_id;
        } else {
            $performanceheaders = PerformanceHeaders::where('employee_id', '=', $employee_id)->first();
            if($performanceheaders === null){
                $performanceheaders = new PerformanceHeaders();
                $performanceheaders->employee_id = $employee_id;
            }
            $performanceheaders->last_update_date = Date('Y-m-d H:i:s');
            $performanceheaders->action_count = $performanceheaders->action_count + $action_count;
            $performanceheaders->success_count = $performanceheaders->success_count + $success_count;
            $performanceheaders->cancel_count = $performanceheaders->cancel_count + $cancel_count;
            $performanceheaders->star_count = $performanceheaders->star_count + $star_count;
        }

        try{
            $performanceheaders->save();
            return 'Suc';
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_room_master($header_input) {
         $id = $header_input['room_id'];

        if($id == -1) {
            $roommaster = new RoomMaster();
            $roommaster->created_by = $header_input['updated_by'];
            $roommaster->api_token  = $this->generateRandomString();
        } else {
            $roommaster = RoomMaster::find($id);
            $roommaster->last_update_date = Date('Y-m-d H:i:s');
            $roommaster->last_updated_by  = $header_input['updated_by'];

            //bila ada tenant atau landlord yang berubah maka disimpan di history
            if($header_input['tenant_id'] != $roommaster->tenant_id || $header_input['landlord_id'] != $roommaster->landlord_id) {
                $this->opr_room_trans_hist($roommaster->room_id,$roommaster->tenant_id,$roommaster->landlord_id,$header_input['updated_by']);
            }
        }

        foreach ($header_input as $key => $value) {
            if($key != 'room_id' && $key != 'updated_by')
                $roommaster->$key = $value;
        }

        $is_active = empty($header_input['is_active']) ? 'N' : 'Y';
        $roommaster->is_active  = $is_active;

        try{
            $roommaster->save();

            if($id == -1) {
                $this->opr_badge_room_headers(-1,$roommaster->room_id,0);
            }

            return $roommaster->room_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_room_notification_headers(Request $request) {
        $id = $request->get('notification_id');

        if($id == -1) {
            $roomnotificationheaders = new RoomNotificationHeaders();
        } else {
            $roomnotificationheaders = RoomNotificationHeaders::find($id);
            $roomnotificationheaders->last_updated_by = $request->get('updated_by');
            $roomnotificationheaders->last_update_date = Date('Y-m-d H:i:s');
        }

        foreach ($request->all() as $key => $value) {
            if($key != 'notification_id' && $key != 'api_token' && $key != 'updated_by')
                $roomnotificationheaders->$key = $value;
        }

        try{
            $roomnotificationheaders->save();
            return !empty($roomnotificationheaders->complaintHeader) ? $roomnotificationheaders->complaintHeader->complaint_status : '';
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_room_trans_hist($room_id, $previous_tenant_id,$previous_landlord_id,$created_by) {
        $roomtranshist = new RoomTransHist();

        $roomtranshist->room_id = $room_id;
        $roomtranshist->previous_tenant_id = $previous_tenant_id;
        $roomtranshist->previous_landlord_id = $previous_landlord_id;
        $roomtranshist->created_by = $created_by;

        try{
            $roomtranshist->save();
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_subunit_master($header_input) {
        $id = $header_input['subunit_id'];

        if($id == -1) {
            $subunitmaster = new SubunitMaster();
            $subunitmaster->created_by  = $header_input['updated_by'];
        } else {
            $subunitmaster = SubunitMaster::find($id);
            $subunitmaster->last_update_date = Date('Y-m-d H:i:s');
            $subunitmaster->last_updated_by   = $header_input['updated_by'];
        }

        foreach ($header_input as $key => $value) {
            if($key != 'subunit_id' && $key != '_token' && $key != 'updated_by')
                $subunitmaster->$key = $value;
        }

        $is_active = empty($header_input['is_active']) ? 'N' : 'Y';
        $subunitmaster->is_active  = $is_active;

        try{
            $subunitmaster->save();
            return $subunitmaster->subunit_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_suggest_headers($header_input) {
        $id = $header_input['suggest_id'];

        if($id == -1) {
            $suggestheaders = new SuggestHeaders();
            $suggestheaders->created_by = $header_input['updated_by'];
        } else {
            $suggestheaders = SuggestHeaders::find($id);
            $suggestheaders->last_updated_by  = $header_input['updated_by'];
            $suggestheaders->last_update_date = Date('Y-m-d H:i:s');
        }

        foreach ($header_input as $key => $value) {
            if($key != 'suggest_id' && $key != 'updated_by')
                $suggestheaders->$key = $value;
        }

        try{
            $suggestheaders->save();
        return $suggestheaders->suggest_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_suggest_notification($header_input, $suggest_id) {

        $room      = RoomMaster::find($header_input['room_id']);
        $employees = EmployeeMaster::where('user_type', '=', EmployeeMaster::MANAGER)
                                ->where('is_active', '=', 'Y')
                                ->get();

        foreach ($employees as $employee) {
            $model = new EmployeeNotificationHeaders();
            $model->notification_desc  = 'Kritik dan saran baru oleh kamar '.$room->room_name.' no '.$room->room_id;
            $model->notification_title = 'Kritik dan Saran Baru';
            $model->notification_type  = EmployeeNotificationHeaders::SUGGEST;
            $model->suggest_id         = $suggest_id;
            $model->employee_id        = $employee->employee_id;
            $model->created_by         = $room->room_id;

            try{
                $model->save();
                return $model->notification_id;
            } catch(\Exception $e) {
                return $e->getMessage();
            }
        }

    }

    function opr_tenant_master($header_input) {
        $id = $header_input['tenant_id'];

        if($id == -1) {
            $tenantmaster = new TenantMaster();
            $tenantmaster->created_by  = $header_input['updated_by'];
        } else {
            $tenantmaster = TenantMaster::find($id);
            $tenantmaster->last_update_date = Date('Y-m-d H:i:s');
            $tenantmaster->last_updated_by  = $header_input['updated_by'];
        }

        foreach ($header_input as $key => $value) {
            if($key != 'tenant_id' && $key != 'updated_by' && $key != '_token'){
                $tenantmaster->$key = $value;
            }
            if($key == 'birth_date'){
                $date = new \DateTime($value);
                $tenantmaster->$key = $date->format('Y-m-d');
            }
        }

        $is_active = empty($header_input['is_active']) ? 'N' : 'Y';
        $tenantmaster->is_active  = $is_active;

        try{
            $tenantmaster->save();
            return $tenantmaster->tenant_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_trans_roomlogin_hist($room_id,$login_hist_status) {
        $transroomloginhist = new TransRoomLoginHist();
        $transroomloginhist->room_id = $room_id;
        $transroomloginhist->login_hist_status = empty($login_hist_status) ? 'FAILED' : 'SUCCESS';
        $transroomloginhist->save();
    }

    function opr_trans_userlogin_hist($username,$login_hist_status) {
        $transuserloginhist = new TransUserLoginHist();
        $transuserloginhist->username = $username;
        $transuserloginhist->login_hist_status = empty($login_hist_status) ? 'FAILED' : 'SUCCESS';
        $transuserloginhist->save();
    }

    function set_player_id_employee(Request $request) {
        $employeeMaster             = EmployeeMaster::find($request->get('employee_id'));
        $employeeMaster->player_id  = $request->get('player_id');
        
        try{
            $employeeMaster->save();
            return $employeeMaster->employee_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function delete_player_id_employee(Request $request) {
        $employeeMaster             = EmployeeMaster::find($request->get('employee_id'));
        $employeeMaster->player_id  = null;
        
        try{
            $employeeMaster->save();
            return $employeeMaster->employee_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function employee_change_password(Request $request) {
        $employeeMaster             = EmployeeMaster::find($request->get('employee_id'));
        if($employeeMaster->passwd != $request->get('old_password')){
            return response()->json([
                'err' => 'Error ganti password',
                'msg'  => 'Password lama tidak cocok',
            ], 200);
        }
        $employeeMaster->passwd     = $request->get('new_password');

        $employeeMaster->last_update_date = new \DateTime();
        $employeeMaster->last_updated_by  = $request->get('employee_id');
        
        try{
            $employeeMaster->save();
            return response()->json([
                'data' => $employeeMaster->employee_id,
                'msg'  => 'Berhasil'
            ], 200);
        } catch(\Exception $e) {
            return response()->json([
                'err' => 'Error ganti password',
                'msg'  => $e->getMessage(),
            ], 200);
        }
    }

    function employee_change_email(Request $request) {
        $employeeMaster             = EmployeeMaster::find($request->get('employee_id'));
        if($employeeMaster->passwd != $request->get('password')){
            return response()->json([
                'err' => 'Error ganti email',
                'msg'  => 'Password anda salah',
            ], 200);
        }
        $employeeMaster->email     = $request->get('email');

        $employeeMaster->last_update_date = new \DateTime();
        $employeeMaster->last_updated_by  = $request->get('employee_id');
        
        try{
            $employeeMaster->save();
            return response()->json([
                'data' => $employeeMaster->employee_id,
                'msg'  => 'Berhasil'
            ], 200);
        } catch(\Exception $e) {
            return response()->json([
                'err' => 'Error ganti email',
                'msg'  => $e->getMessage(),
            ], 200);
        }
    }

    function employee_change_phone(Request $request) {
        $employeeMaster             = EmployeeMaster::find($request->get('employee_id'));
        if($employeeMaster->passwd != $request->get('password')){
            return response()->json([
                'err' => 'Error ganti phone',
                'msg'  => 'Password anda salah',
            ], 200);
        }
        $employeeMaster->phone_number     = $request->get('phone_number');

        $employeeMaster->last_update_date = new \DateTime();
        $employeeMaster->last_updated_by  = $request->get('employee_id');
        
        try{
            $employeeMaster->save();
            return response()->json([
                'data' => $employeeMaster->employee_id,
                'msg'  => 'Berhasil'
            ], 200);
        } catch(\Exception $e) {
            return response()->json([
                'err' => 'Error ganti phone',
                'msg'  => $e->getMessage(),
            ], 200);
        }
    }

    function room_change_password(Request $request) {
        $roomMaster                  = RoomMaster::find($request->get('room_id'));
        if($roomMaster->room_passwd != $request->get('old_password')){
            return response()->json([
                'err' => 'Error ganti password',
                'msg'  => 'Password lama tidak cocok',
            ], 200);
        }
        $roomMaster->room_passwd      = $request->get('new_password');

        $roomMaster->last_update_date = new \DateTime();
        $roomMaster->last_updated_by  = $request->get('room_id');
        
        try{
            $roomMaster->save();
            return response()->json([
                'data' => $roomMaster->room_id,
                'msg'  => 'Berhasil'
            ], 200);
        } catch(\Exception $e) {
            return response()->json([
                'err' => 'Error ganti password',
                'msg'  => $e->getMessage(),
            ], 200);
        }
    }

    function room_change_email(Request $request) {
        $roomMaster                  = RoomMaster::find($request->get('room_id'));
        if($roomMaster->room_passwd != $request->get('password')){
            return response()->json([
                'err' => 'Error ganti email',
                'msg'  => 'Password anda salah',
            ], 200);
        }
        $modelTenant = $roomMaster->tenantMasterTenant;
        $modelTenant->email       = $request->get('email');

        $modelTenant->last_update_date = new \DateTime();
        $modelTenant->last_updated_by  = $request->get('room_id');
        
        try{
            $modelTenant->save();
            return response()->json([
                'data' => $roomMaster->room_id,
                'msg'  => 'Berhasil'
            ], 200);
        } catch(\Exception $e) {
            return response()->json([
                'err' => 'Error ganti email',
                'msg'  => $e->getMessage(),
            ], 200);
        }
    }

    function room_change_phone(Request $request) {
        $roomMaster                  = RoomMaster::find($request->get('room_id'));
        if($roomMaster->room_passwd != $request->get('password')){
            return response()->json([
                'err' => 'Error ganti phone',
                'msg'  => 'Password anda salah',
            ], 200);
        }
        $modelTenant = $roomMaster->tenantMasterTenant;
        $modelTenant->phone_number       = $request->get('phone_number');

        $modelTenant->last_update_date = new \DateTime();
        $modelTenant->last_updated_by  = $request->get('room_id');
        
        try{
            $modelTenant->save();
            return response()->json([
                'data' => $roomMaster->room_id,
                'msg'  => 'Berhasil'
            ], 200);
        } catch(\Exception $e) {
            return response()->json([
                'err' => 'Error ganti phone',
                'msg'  => $e->getMessage(),
            ], 200);
        }
    }

    function opr_player_id_employee($player_id, $employee_id) {
        $employeeMaster             = EmployeeMaster::find($employee_id);
        $employeeMaster->player_id  = $player_id;
        $employeeMaster->save();
    }

    function opr_player_id_room($player_id, $room_id) {
        $roomMaster             = RoomMaster::find($room_id);
        $roomMaster->player_id  = $player_id;
        $roomMaster->save();
    }

    function opr_unit_master($header_input) {
        $id = $header_input['unit_id'];

        if($id == -1) {
            $unitmaster = new UnitMaster();
            $unitmaster->created_by  = $header_input['updated_by'];
            
        } else {
            $unitmaster = UnitMaster::find($id);
            $unitmaster->last_update_date = Date('Y-m-d H:i:s');
            $unitmaster->last_updated_by  = $header_input['updated_by'];
        }

        foreach ($header_input as $key => $value) {
            if($key != 'unit_id' && $key != 'updated_by' && $key != '_token'){
                $unitmaster->$key = $value;
            }
        }

        $is_active = empty($header_input['is_active']) ? 'N' : 'Y';
        $unitmaster->is_active  = $is_active;

        try{
            $unitmaster->save();
            return $unitmaster->unit_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function opr_facility_master($header_input) {
        $id = $header_input['facility_id'];

        if($id == -1) {
            $facilitymaster = new FacilityMaster();
            $facilitymaster->created_by  = $header_input['updated_by'];
            
        } else {
            $facilitymaster = FacilityMaster::find($id);
            $facilitymaster->last_update_date = Date('Y-m-d H:i:s');
            $facilitymaster->last_updated_by  = $header_input['updated_by'];
        }

        foreach ($header_input as $key => $value) {
            if($key != 'facility_id' && $key != 'updated_by' && $key != '_token'){
                $facilitymaster->$key = $value;
            }
        }

        $is_active = empty($header_input['is_active']) ? 'N' : 'Y';
        $facilitymaster->is_active  = $is_active;

        try{
            $facilitymaster->save();
            return $facilitymaster->facility_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function employeeResetPassword(Request $request) {
        $employeemaster         = EmployeeMaster::find($request->get('employee_id'));
        $employeemaster->passwd = $request->get('passwd');
        try{
            $employeemaster->save();
            return $employeemaster->employee_id;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    function checkResetPassword(Request $request){
        $time = new \DateTime();
        $time->add(new \DateInterval('PT30M'));
        $resetToken = $this->generateRandomNumber(6);

        if(is_numeric($request->get('username'))){
            $room = RoomMaster::where('room_id', '=', $request->get('username'))->first();
            if($room !== null){
                if(empty($room->tenantMasterTenant->email)){
                    return response()->json([
                        'msg'  => 'Email nomor kamar tersebut tidak ada',
                    ], 200);
                }
                $room->reset_token         = $resetToken;
                $room->expired_reset_token = $time;
                $room->save();

                $to = $room->tenantMasterTenant->first_name.' '.$room->tenantMasterTenant->last_name.' kamar '.$room->room_name.' nomor '.$room->room_id;
                Mail::to($room->tenantMasterTenant->email)
                    ->send(new MailSendToken($to, $resetToken, $time->format('d M Y H:i')));

                return response()->json([
                    'data' => [
                        'room_name' => $room->room_name,
                        'room_id'   => $room->room_id,
                        'email'     => $room->tenantMasterTenant->email,
                        'user_type' => 'ROOM',
                    ],
                    'msg'  => 'Berhasil'
                ], 200);
            }
        }
        $employee = EmployeeMaster::where('username', '=', $request->get('username'))->first();
        if($employee !== null){
            if(empty($employee->email)){
                return response()->json([
                    'msg'  => 'Email employee tidak ada',
                ], 200);
            }
            $employee->reset_token         = $resetToken;
            $employee->expired_reset_token = $time;
            $employee->save();

            $to = $employee->first_name.' '.$employee->last_name.' ('.$employee->user_type.')';

            Mail::to($employee->email)
                ->send(new MailSendToken($to, $resetToken, $time->format('d M Y H:i')));

            return response()->json([
                'data' => [
                    'employee_name' => $employee->first_name.' '.$employee->last_name,
                    'employee_id'   => $employee->employee_id,
                    'email'         => $employee->email,
                    'user_type'     => 'EMPLOYEE',
                ],
                'msg'  => 'Berhasil',
            ], 200);
        }
        return response()->json([
            'msg'  => 'Employee atau kamar dengan username tersebut tidak terdaftar'
        ], 200);

    }

    function checkResetToken(Request $request){
        $now = new \DateTime();
        if(is_numeric($request->get('username'))){
            $room = RoomMaster::where('room_id', '=', $request->get('username'))->first();
            if($room !== null){
                if($room->reset_token == $request->get('reset_token') && $room->expired_reset_token >= $now->format('Y-m-d H:i:s')){
                    return response()->json([
                        'data'  => [
                            'valid'       => true,
                            'room_id'     => $room->room_id,
                            'room_name'   => $room->room_name,
                            'tenant_name' => $room->tenantMasterTenant->first_name.' '.$room->tenantMasterTenant->last_name,
                        ],
                        'msg'  => 'Berhasil',
                    ], 200);
                }else{
                    return response()->json([
                        'msg'  => 'Kode verifikasi tidak cocok atau kadaluarsa!',
                    ], 200);
                }
            }
        }

        $employee = EmployeeMaster::where('username', '=', $request->get('username'))->first();
        if($employee !== null){
            if($employee->reset_token == $request->get('reset_token') && $employee->expired_reset_token >= $now->format('Y-m-d H:i:s')){
                return response()->json([
                    'data'  => [
                        'valid'           => true,
                        'employee_id'     => $employee->employee_id,
                        'employee_name'   => $employee->first_name.' '.$employee->last_name,
                    ],
                    'msg'  => 'Berhasil',
                ], 200);
            }else{
                return response()->json([
                    'msg'  => 'Kode verifikasi tidak cocok atau kadaluarsa!',
                ], 200);
            }
        }
        return response()->json([
            'msg'  => 'Employee atau kamar dengan username tersebut tidak terdaftar'
        ], 200);

    }

    function resetPassword(Request $request){
        $now = new \DateTime();
        if(is_numeric($request->get('username'))){
            $room = RoomMaster::where('room_id', '=', $request->get('username'))->first();
            if($room !== null){
                if($room->reset_token == $request->get('reset_token') && $room->expired_reset_token >= $now->format('Y-m-d H:i:s')){
                    $room->room_passwd      = $request->get('new_password');
                    $room->last_update_date = $now;
                    $room->save();

                    return response()->json([
                        'msg'  => 'Berhasil',
                    ], 200);
                }else{
                    return response()->json([
                        'msg'  => 'Kode verifikasi tidak cocok atau kadaluarsa!',
                    ], 200);
                }
                    
            }
        }

        $employee = EmployeeMaster::where('username', '=', $request->get('username'))->first();
        if($employee !== null){
            if($employee->reset_token == $request->get('reset_token') && $employee->expired_reset_token >= $now->format('Y-m-d H:i:s')){
                $employee->passwd           = $request->get('new_password');
                $employee->last_update_date = $now;
                $employee->save();
                
                return response()->json([
                    'msg'  => 'Berhasil',
                ], 200);
            }else{
                return response()->json([
                    'msg'  => 'Kode verifikasi tidak cocok atau kadaluarsa!',
                ], 200);
            }
        }

        return response()->json([
            'msg'  => 'Employee atau kamar dengan username tersebut tidak terdaftar'
        ], 200);

    }

    function checkComplaintNotRate(Request $request){
        $complaint = ComplaintHeaders::where('room_id', '=', $request->get('room_id'))
                                ->where('complaint_status', '=', ComplaintHeaders::DONE)
                                ->whereNull('complaint_rate')
                                ->first();

        if($complaint !== null){
            return response()->json([
                'data'  => $complaint->complaint_id,
                'msg'   => 'Not Rate',
            ], 200);
        }else{
            return response()->json([
                'msg'  => 'Rated',
            ], 200);
        }
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    protected function generateRandomString($length = 30) {
        $now          = new \DateTime();
        $randomString = md5($now->format('dmyhis'));

        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    protected function generateRandomNumber($length = 30) {
        $randomNumber = '';
        $characters = '0123456789';
        $charactersLength = strlen($characters);

        for ($i = 0; $i < $length; $i++) {
            $randomNumber .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomNumber;
    }

    function get_upload(Request $request) {
        $upload               = ComplaintUploads::orderBy('upload_id', 'desc')->first();
        $upload->bytea_upload = pg_unescape_bytea(stream_get_contents($upload->bytea_upload));

        return response()->json([
            'data' => $upload,
        ], 200);
    }
}
