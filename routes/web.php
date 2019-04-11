<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

ini_set('display_errors', 1);

$app->get('/', function () use ($app) {
    return "Halllo Guys";
});



$app->post('/login', 'AuthController@login');
$app->post('/login-employee', 'AuthController@login_employee');
$app->post('/login-room', 'AuthController@login_room');

$app->post('/check-reset-password', 'AuthController@checkResetPassword');
$app->post('/check-reset-token', 'AuthController@checkResetToken');
$app->post('/reset-password', 'AuthController@resetPassword');

$app->group(['middleware' => 'auth'], function () use ($app) {
	$app->post('/get-dashboard', 'AuthController@get_dashboard');
	$app->post('/get-complaintbyroom', 'AuthController@get_complaintbyroom');
	$app->post('/get-department', 'AuthController@get_department');
	$app->post('/get-detailcomplaint', 'AuthController@get_detailcomplaint');
	$app->post('/get-detailoutstanding', 'AuthController@get_detailoutstanding');
	$app->post('/get-detailunit', 'AuthController@get_detailunit');
	$app->post('/get-detailunitbyroom', 'AuthController@get_detailunitbyroom');
	$app->post('/get-employee-index', 'AuthController@get_employee_index');
	$app->post('/get-employee', 'AuthController@get_employee');
	$app->post('/get-employeedetailunit', 'AuthController@get_employee_detail_unit');
	$app->post('/get-employeebadge', 'AuthController@get_employeebadge');
	$app->post('/get-employeecomplaint', 'AuthController@get_employeecomplaint');
	$app->post('/get-employeenotification', 'AuthController@get_employeenotification');
	$app->post('/get-employeenotificationunsend', 'AuthController@get_employeenotificationunsend');
	$app->post('/get-countemployeenotification', 'AuthController@get_countemployeenotification');
	$app->post('/get-countemployeenotificationunread', 'AuthController@get_countemployeenotificationunread');

	$app->post('/get-managercomplaint', 'AuthController@get_managercomplaint');
	$app->post('/get-complaint-index', 'AuthController@get_complaint_index');
	$app->post('/get-complaint', 'AuthController@get_complaint');
	$app->post('/get-complaintstatus', 'AuthController@get_complaint_status');
	$app->post('/get-complaintemployee', 'AuthController@get_complaint_employee');
	$app->post('/get-complaintbydept', 'AuthController@get_complaintbydept');
	$app->post('/get-complaintsupervisor', 'AuthController@get_complaintsupervisor');
	$app->post('/get-photocomplaint', 'AuthController@get_photocomplaint');
	$app->post('/get-room-index', 'AuthController@get_room_index');
	$app->post('/get-room', 'AuthController@get_room');
	$app->post('/get-roombadge', 'AuthController@get_roombadge');
	$app->post('/get-roomnotification', 'AuthController@get_roomnotification');
	$app->post('/get-roomnotificationunsend', 'AuthController@get_roomnotificationunsend');
	$app->post('/get-countroomnotificationunread', 'AuthController@get_countroomnotificationunread');
	$app->post('/get-badgeemployeenotification', 'AuthController@get_badgeemployeenotification');
	
	$app->post('/get-spvcomplaint', 'AuthController@get_spvcomplaint');
	$app->post('/get-subunit', 'AuthController@get_subunit');
	$app->post('/get-suggest-index', 'AuthController@get_suggest_index');
	$app->post('/get-suggest', 'AuthController@get_suggest');
	$app->post('/get-tenant', 'AuthController@get_tenant');
	$app->post('/get-unit', 'AuthController@get_unit');
	$app->post('/get-facility', 'AuthController@get_facility');
	$app->post('/get-hist-trans', 'AuthController@get_hist_trans');
	$app->post('/get-report-subordinatscore-alltime', 'AuthController@get_report_subordinatscore_alltime');
	$app->post('/get-subordinatscore-alltime', 'AuthController@get_subordinatscore_alltime');

	$app->post('/get-player-employee', 'AuthController@get_player_employee');
	$app->post('/get-player-teknisi', 'AuthController@get_player_teknisi');
	$app->post('/get-player-room', 'AuthController@get_player_room');
	$app->post('/get-report-complaint-by-dept', 'AuthController@get_report_complaint_by_dept');
	$app->post('/get-report-complaint-by-subordinat', 'AuthController@get_report_complaint_by_subordinat');
	$app->post('/get-report-subordinatscore', 'AuthController@get_report_subordinatscore');
	$app->post('/get-report-technician', 'AuthController@get_report_technician');

	$app->post('/save-submitcomplaint', 'AuthController@submitComplaint');
	$app->post('/save-submitcomplaintuploads', 'AuthController@submitComplaintUploads');
	$app->post('/save-submitdepartmentmaster', 'AuthController@submitDepartmentMaster');
	$app->post('/save-submitdetailunit', 'AuthController@submitDetailUnit');
	$app->post('/save-submitemployeemaster', 'AuthController@submitEmployeeMaster');
	$app->post('/save-submitemployeenotificationheaders', 'AuthController@submitEmployeeNotificationHeaders');
	$app->post('/save-submitemployeeuploads', 'AuthController@submitEmployeeUploads');
	$app->post('/save-submitroommaster', 'AuthController@submitRoomMaster');
	$app->post('/save-submitroomnotificationheaders', 'AuthController@submitRoomNotificationHeaders');
	$app->post('/save-submitsubunitmaster', 'AuthController@submitSubunitMaster');
	$app->post('/save-submitsuggest', 'AuthController@submitSuggest');
	$app->post('/save-submittenantmaster', 'AuthController@submitTenantMaster');
	$app->post('/save-submitunitmaster', 'AuthController@submitUnitMaster');
	$app->post('/save-submitfacilitymaster', 'AuthController@submitFacilityMaster');
	$app->post('/save-submitemployeelanguage', 'AuthController@submitEmployeeLanguage');
	
	$app->post('/save-reademployeenotification', 'AuthController@submitReadEmployeeNotification');
	$app->post('/save-reademployeenotification', 'AuthController@submitReadEmployeeNotification');

	$app->post('/save-reademployeenotificationbyid', 'AuthController@submitReadEmployeeNotificationById');
	$app->post('/save-reademployeenotificationbyId', 'AuthController@submitReadEmployeeNotificationById');

	$app->post('/save-readroomnotification', 'AuthController@submitReadRoomNotification');
	$app->post('/save-readroomnotification', 'AuthController@submitReadRoomNotification');
	
	$app->post('/save-employee-reset-password', 'AuthController@employeeResetPassword');

	$app->post('/set-player-id-employee', 'AuthController@set_player_id_employee');
	$app->post('/delete-player-id-employee', 'AuthController@delete_player_id_employee');

	$app->post('/employee-change-password', 'AuthController@employee_change_password');
	$app->post('/employee-change-email', 'AuthController@employee_change_email');
	$app->post('/employee-change-phone', 'AuthController@employee_change_phone');
	$app->post('/room-change-password', 'AuthController@room_change_password');
	$app->post('/room-change-email', 'AuthController@room_change_email');
	$app->post('/room-change-phone', 'AuthController@room_change_phone');

	$app->post('/logout', 'AuthController@logout');
	$app->post('/get-upload', 'AuthController@get_upload');

	$app->post('/get-team-report-supervisor', 'AuthController@get_team_report_supervisor');
	$app->post('/get-team-report-manager', 'AuthController@get_team_report_manager');
	$app->post('/get-detail-team-report', 'AuthController@get_detailteamreport');
	
	$app->post('/get-facility-report-supervisor', 'AuthController@get_facility_report_supervisor');
	$app->post('/get-facility-report-manager', 'AuthController@get_facility_report_manager');

	$app->post('/get-employee-photo', 'AuthController@get_employee_photo');

	$app->post('/check-complaint-not-rate', 'AuthController@checkComplaintNotRate');

	$app->post('/sem', 'AuthController@opr_complaint_notification');
});
