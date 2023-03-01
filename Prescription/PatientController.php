<?php

namespace App\Http\Controllers\Prescription;

use App\Http\Resources\Erx\HistoryPaginateResource;
use App\Http\Resources\Erx\HistoryResource;
use App\Http\Resources\Erx\SearchResource;
use App\Http\Resources\PatientResource;
use App\Models\Erx\AgeGroup;
use App\Models\Erx\ErxItem;
use App\Models\Identity\Admission;
use App\Models\Identity\Provider;
use App\Models\Identity\User;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ThrottleFailedApi;
use App\Models\Identity\UserProvider;
use App\Models\Prescription\ErrorLog;
use App\Models\Prescription\Item;
use App\Models\Prescription\Prescription;
use App\Services\DitasService;
use App\Services\EstehghaghService;
use App\Services\Gateways\Ditas\AuthBase;
use App\Services\Gateways\Ditas\Salamat;
use App\Services\Prescription\AdmissionService;
use App\Services\Prescription\PatientService;
use App\Services\Prescription\PrescriptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use phpDocumentor\Reflection\Types\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * /**
 * Class Prescription.
 *
 * @package namespace App\Models\Prescription;
 * @OA\Schema ( required={"id"}, schema="PrescriptionPatient")
 * App\Models\Prescription\Prescription
 */
class PatientController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/prescription/patient",
     *     tags={"prescription-patient"},
     *     summary="List of Patients",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Prescription"),
     *         ),
     *     ),
     *
     * )
     *
     * @param Request $request
     * @param PatientService $patientService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, PatientService $patientService)
    {

        /* @var User $user */
//        $user = auth('api')->user();
        $user = User::first();

        //permission in Service

        $patientsNULL = $patientService->get($user);

        if ($patientsNULL === false) {
            return $this->sendError("An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $success = [
            'patients' => $patientService->hideAttributes($patientsNULL, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }


    /**
     * Display a listing of the deleted resources.
     *
     * @OA\Get(
     *     path="/api/prescription/patient/deleted",
     *     tags={"prescription-patient-d"},
     *     summary="List of deleted patients",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Prescription"),
     *         ),
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param PatientService $patientService
     * @return JsonResponse [string] message
     */
    public function deleted(Request $request, PatientService $patientService)
    {
        /* @var User $user */
        $user = auth('api')->user();

        //permission in Services

        $patientsNULL = $patientService->get($user, null, true);
        if ($patientsNULL === false) {
            return $this->sendError("An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $success = [
            'patients' => $patientService->hideAttributes($patientsNULL, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/api/prescription/patient",
     *     tags={"prescription-patient"},
     *     summary="store a new patient",
     *     @OA\Parameter(name="domain_id", example="90b16053-5033-4e4f-b150-895aa4bee247", @OA\Schema(type="string", format="uuid"), in="query"),
     *     @OA\Parameter(name="title", example="this is a title", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="description", example="your descriotion", @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="secure_link_enabled", example=false, @OA\Schema(type="boolean"), in="query"),
     *     @OA\Parameter(name="secure_link_key", @OA\Schema(type="string",  format="password"), in="query"),
     *     @OA\Parameter(name="secure_link_with_ip", example=false, @OA\Schema(type="boolean"), in="query"),
     *     @OA\Parameter(name="secure_expire_time", example="24", @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="ads_enabled", example=false, @OA\Schema(type="boolean"), in="query"),
     *     @OA\Parameter(name="present_type",description="1 means Auto and 0 means Manual" , example="1", @OA\Schema(type="string" ), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Prescription"),
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param PatientNewRequest $request
     * @param PatientService $patientService
     * @return JsonResponse [string] message
     */
    public function store(PatientNewRequest $request, PatientService $patientService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        //permi
        //todo


        $array = $request->only(array_keys($request->rules()));
        $array['secure_expire_time'] = filter_var($request->secure_expire_time ?? 24, FILTER_VALIDATE_INT);

        $patient = $patientService->create($user, $array, $msg);
        if ($patient === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $success = [
            'patient' => $patientService->hideAttributes($patient, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/prescription/patient/{patient}",
     *     tags={"prescription-patient"},
     *     summary="update a patient",
     *     @OA\Parameter(name="patient", example="90b16666-2f97-4ffd-8f07-3ba8a221d798", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Parameter(name="title", example="this is a title", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="description", example="your descriotion", @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="secure_link_enabled", example=false, @OA\Schema(type="boolean"), in="query"),
     *     @OA\Parameter(name="secure_link_key", @OA\Schema(type="string",  format="password"), in="query"),
     *     @OA\Parameter(name="secure_link_with_ip", example=false, @OA\Schema(type="boolean"), in="query"),
     *     @OA\Parameter(name="secure_expire_time", example="24", @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="ads_enabled", example=false, @OA\Schema(type="boolean"), in="query"),
     *     @OA\Parameter(name="present_type",description="1 means Auto and 0 means Manual" , example="1", @OA\Schema(type="string" ), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Prescription"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Patient not found"
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param PatientUpdateRequest $request
     * @param Patient $patient
     * @param PatientService $patientService
     * @return JsonResponse [string] message
     */
    public function update(PatientUpdateRequest $request, Patient $patient, PatientService $patientService)
    {
        //Done
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        // "ReadOnly: domain cannot be changed" ?

        //permission
        if (!$user->is_admin && $user->id != $patient->domain->owner_id) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }
        $array = $request->only(array_keys($request->rules()));
        $array['secure_expire_time'] = filter_var($request->secure_expire_time ?? 24, FILTER_VALIDATE_INT);

        $patientNULL = $patientService->update($patient, $array, $msg);
        if ($patientNULL === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [
            'patient' => $patientService->hideAttributes($patientNULL, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/prescription/patient/{id}",
     *     tags={"prescription-patient"},
     *     summary="Show/Find patient by ID",
     *     description="Returns a single patient",
     *     @OA\Parameter(name="id", description="ID of patient to return", example="90b16666-2f97-4ffd-8f07-3ba8a221d7b8", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Prescription"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Patient not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param PatientService $patientService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, PatientService $patientService)
    {
        $patient_id = $id;

        /* @var User $user */
        $user = auth('api')->user();

        //permission in service


        $patient = $patientService->first($user, $patient_id);
        if (!$patient) {
            return $this->sendError('Patient not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [
            'patient' => $patientService->hideAttributes($patient, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/prescription/patient/{patient}",
     *     tags={"prescription-patient-d"},
     *     summary="delete a patient",
     *     @OA\Parameter(name="patient", example="90b16666-2f97-4ffd-8f07-3ba8a221d798", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Prescription"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Patient not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Patient $patient
     * @param PatientService $patientService
     * @return JsonResponse [string] message
     */
    public function destroy(Patient $patient, PatientService $patientService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        //permi
        if (!$user->is_admin && $user->id != $patient->owner_id) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $response = $patientService->delete($user, $patient, $msg);
        if (!$response) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/prescription/required/complete/info",
     *     tags={"prescription-patient"},
     *     summary="Patient need to provide complete information",
     *     description="Returns (true or false) if  a field is not filled in returns true. Else, returns false",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Prescription"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid Patient"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Patient not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param PatientService $patientService
     * @return JsonResponse [string] message
     */
    public function RequiredCompleteInfo(Request $request, PatientService $patientService)
    {
        /* @var User $user */
        $user = auth('api')->user();
        $patient = $user->patient;
        //permission in service

        if ($patient == null) {
            return $this->sendError($msg ?? "Patient Not found", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $info = $patientService->requiredCompleteInfo($patient);
        $requiredCompleteInfo = (bool)$info;

        $success = [
            'complete_info' => $requiredCompleteInfo,
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * call patient by national code
     *
     * @OA\Get(
     *     path="/api/prescription/call/patient",
     *     tags={"prescription-patient"},
     *     summary="",
     *     @OA\Parameter(name="code", description="code to call", example="2670132952", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="limit", description="limit for prescription history", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Prescription"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param Request $request
     * @return JsonResponse [string] message
     */

    public function callPatient(Request             $request, DitasService $ditasService, AdmissionService $admissionService,
                                PrescriptionService $prescriptionService, EstehghaghService $estehghaghService)
    {

        /* @var User $user */
        $user = auth('api')->user();
        $employee = $user->employee;

        $code = $request->input('code');
        if (!$user->employee->salamat_user || !$user->employee->getRawOriginal('salamat_pass')) {
            return $this->sendError('نام کاربری سلامت شما اشتباه است', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }
        //----------remove patient from admission
        $admissionService->removePatient($code);

        $username = $user->employee->salamat_user;
        $pass = $user->employee->salamat_pass;
        $medical_no = $employee->medical_no;
        $provider = UserProvider::where('user_id', $user->id)->where('is_Active', 1)->first()->provider;
        $siamID = $provider->siam_code;
        $patient = $estehghaghService->getPatientInfo($medical_no, $code, $siamID);
        if (!$patient || (isset($patient['my_success']) && $patient['my_success'] == false)) {
            $userSession = $ditasService->createUserSession($username, $pass);
            if (!$userSession || (isset($userSession['my_success']) && $userSession['my_success'] == false)) {
                return $this->sendError($userSession['my_message'] ?? 'اختلال در سامانه وزارتخانه',
                    ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__],
                    404);
            }
            if ((isset($userSession['result']['data']['info']['isTwoStep']) && $userSession['result']['data']['info']['isTwoStep'] == true)) {
                return $this->sendError('جهت استفاده از سامانه لطفا لاگین دومرحله ای خود را در سازمان بیمه سلامت غیر فعال نمائید',
                    ['error' => 'otp', 'class' => __CLASS__, 'line' => __LINE__],
                    404);
            }
            $citizen = $ditasService->createCitizenSession($code, $username, $pass);
            if (!$citizen || (isset($citizen['my_success']) && $citizen['my_success'] == false)) {
                return $this->sendError($citizen['my_message'] ?? 'اختلال در سامانه وزارتخانه',
                    ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__],
                    404);
            }
            $prescArray = $prescriptionService->estehghaghSalamat($user, $citizen, $provider->id);
            $info = $citizen['result']['data']['info'];
            $bimeType = $info['issuerType'] === 'T' ? 'T' : 'B';
        }

        $bimeType = $bimeType ?? (($patient['insurer']['coded_string'] == '1') ? 'T' : 'B');
        if ($bimeType == 'B') {
            if (!isset($userSession, $citizen)) {
                $userSession = $ditasService->createUserSession($username, $pass);
                if (!$userSession || (isset($userSession['my_success']) && $userSession['my_success'] == false)) {
                    //todo retry
                    return $this->sendError($userSession['my_message'] ?? 'اختلال در سامانه وزارتخانه',
                        ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__],
                        404);
                }

                $citizen = $ditasService->createCitizenSession($code, $username, $pass);
                if (!$citizen || (isset($citizen['my_success']) && $citizen['my_success'] == false)) {
                    //todo retry
                    return $this->sendError($citizen['my_message'] ?? 'اختلال در سامانه وزارتخانه',
                        ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__],
                        404);
                }
            }
            $samad = $ditasService->createSamadCode($code, $username, $pass);

            if (!$samad || (isset($samad['my_success']) && $samad['my_success'] == false)) {
                return $this->sendError($samad['my_message'] ?? "An unexpected error has occurred",
                    ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__],
                    Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        $samadCode = $samad['result'] ?? null;
        $userProvider = UserProvider::where('user_id', $user->id)->where('is_active', 1)->first()->provider_id;
        $prescArray = $prescArray ?? $prescriptionService->estehghagh($user, $patient, $userProvider);
        $prescArray['samadcode'] = $samadCode;
        $resCode = $prescArray['res_code'];
        $prescData = $prescriptionService->createEstehghagh($prescArray);

        $log = ErrorLog::query()->where('res_code', $resCode)->first();
        if ($log == null) {
            //todo fix inserting errors
            $errorLog['res_code'] = $resCode;
            $errorLog['res_message'] = $patient['my_message'];
            $errorLog['description'] = 'ایجاد نسخه';
            ErrorLog::create($errorLog);
        }
        //permission in service
//        if (!$patient) {
//            return $this->sendError('Patient not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
//        }
        //--------------doctors previous prescriptions

        $success = [
            'patient' => PatientResource::make($prescData)
//            'history' => HistoryPaginateResource::collection($history_presc)
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * call patient by national code
     *
     * @OA\Get(
     *     path="/api/prescription/check/patient",
     *     tags={"prescription-patient"},
     *     summary="",
     *     @OA\Parameter(name="code", description="code to call", example="2670132952", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Prescription"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param Request $request
     * @return JsonResponse [string] message
     */

    public function checkPatient(Request $request, EstehghaghService $estehghaghService, PrescriptionService $prescriptionService, DitasService $ditasService)
    {

        /* @var User $user */
        $user = auth('api')->user();
        $employee = $user->employee;

        $national_code = $request->input('code');
        $username = $user->employee->salamat_user;
        $pass = $user->employee->salamat_pass;
        $medical_no = $employee->medical_no;
        $provider = UserProvider::where('user_id', $user->id)->where('is_active', 1)->first();
        if ($provider == null) {
            return $this->sendError("مرکز فعال برای شما وجود ندارد", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }
        $siamID = $provider->provider->siam_code;
        //check if patient is admitted form his
        $date = \Hekmatinasser\Verta\Facades\Verta::now()->subHours(24)->format('Y/m/d H:i:s');
        $admited = Admission::where('datetime', '>', $date)->where(function ($query) use ($medical_no) {
            $query->WhereNull('medical_no')->orWhere('medical_no', $medical_no);
        })->where('is_visited', 0)->where('provider_siam_code', $siamID)->where('national_code', $national_code)->first();
        $admission = isset($admited);

        $patient = $estehghaghService->getPatientInfo($medical_no, $national_code, $siamID);

        //if ditas estehghagh failed use salamat service for patient details
        Log::channel('emed')->info(json_encode($patient));
        if (!$patient || (isset($patient['my_success']) && $patient['my_success'] == false)) {
            $userSession = $ditasService->createUserSession($username, $pass);
            if (!$userSession || (isset($userSession['my_success']) && $userSession['my_success'] == false)) {
                return $this->sendError($userSession['my_message'] ?? 'اختلال در سامانه وزارتخانه',
                    ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__],
                    404);
            }
            if ((isset($userSession['result']['data']['info']['isTwoStep']) && $userSession['result']['data']['info']['isTwoStep'] == true)) {
                return $this->sendError('جهت استفاده از سامانه لطفا لاگین دومرحله ای خود را در سازمان بیمه سلامت غیر فعال نمائید',
                    ['error' => 'otp', 'class' => __CLASS__, 'line' => __LINE__],
                    404);
            }

            $citizen = $ditasService->createCitizenSession($national_code, $username, $pass);
            if (!$citizen || (isset($citizen['my_success']) && $citizen['my_success'] == false)) {
                return $this->sendError($citizen['my_message'] ?? 'اختلال در سامانه وزارتخانه',
                    ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__],
                    404);
            }
            $prescData = $prescriptionService->estehghaghSalamat($user, $citizen, $provider->id);
        }

        $userProvider = $provider->provider_id;
        $prescData = $prescData ?? $prescriptionService->estehghagh($user, $patient, $userProvider);

        $success = [
            'patient' => $prescData,
            'admission' => $admission
//            'history' => HistoryPaginateResource::collection($history_presc)
        ];
        return $this->sendResponse($success, 'success.');
    }

}
