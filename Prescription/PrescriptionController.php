<?php

namespace App\Http\Controllers\Prescription;

use App\Http\Requests\FilterRequest;
use App\Http\Requests\Prescription\FetchPrescriptionRequest;
use App\Http\Requests\Prescription\PrintPrescriptionRequest;
use App\Http\Resources\Erx\HistoryPaginateResource;
use App\Http\Resources\PatientResource;
use App\Http\Resources\Prescription\ItemResource;
use App\Http\Resources\Prescription\PrescFetchGroupResource;
use App\Http\Resources\Prescription\PrescHistoryPaginateResource;
use App\Http\Resources\Prescription\PrescriptionPaginateResource;
use App\Http\Resources\Prescription\PrescriptionResource;
use App\Http\Resources\Prescription\PrintResource;
use App\Models\Erx\Type;
use App\Models\Identity\User;
use App\Models\Identity\UserProvider;
use App\Models\Prescription\Item;
use App\Models\Prescription\Prescription;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ThrottleFailedApi;
use App\Models\Prescription\Registration;
use App\Rules\UUID;
use App\Services\DitasService;
use App\Services\Prescription\PrescriptionService;
use App\Services\Prescription\RegistrationService;
use App\Services\SearchService;
use App\Services\TaminService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * /**
 * Class Prescription.
 *
 * @package namespace App\Models\Prescription;
 * @OA\Schema ( required={"id"}, schema="Prescription")
 * App\Models\Prescription\Prescription
 */
class PrescriptionController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/prescription",
     *     tags={"Prescription"},
     *     summary="List of Prescriptions",
     *     @OA\Parameter(name="page", description="page of prescriptions", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *    @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
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
     * @param FilterRequest $request
     * @param PrescriptionService $prescriptionService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, PrescriptionService $prescriptionService)
    {


        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service
        $limit = $request->input('limit') ?? 10;
        $prescriptions = Prescription::query()->whereHas('registrations')->orderBy('created_at','desc')->paginate($limit);

        if ($prescriptions === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        $prescriptions_pg = new PrescriptionPaginateResource($prescriptions);
        $success = [
            'result' => $prescriptions_pg,

        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/prescription/{id}",
     *     tags={"Prescription"},
     *     summary="Show/Find Prescription by ID",
     *     description="Returns a single Prescription",
     *     @OA\Parameter(name="id", description="ID of Prescription to return", example="47e41f4b-35fc-49ac-a8aa-0001bcc0c0ec", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
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
     *         description="Prescription not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param PrescriptionService $prescriptionService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, PrescriptionService $prescriptionService)
    {
        $prescription_id = $id;

        /* @var User $user */
        $user = auth('api')->user();

        //permission in service


        $prescription = $prescriptionService->first($user, $prescription_id);
        if (!$prescription) {
            return $this->sendError('Prescription not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [
            'Prescription' => new PrescriptionResource($prescription),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/api/prescription",
     *     tags={"Prescription"},
     *     summary="store a new prescription",
     *     @OA\RequestBody(
     *     required=true,
     *      @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *               @OA\Property(
     *                    property="prescription_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *                 example={"national_code":"2670132952","prescription_id":"96279eb6-261b-4dd0-84c4-0b6516d2772a"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Item"),
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
     * @param Request $request
     * @param PrescriptionService $prescriptionService
     * @return JsonResponse [string] message
     */
    public function store(Request       $request, PrescriptionService $prescriptionService, DitasService $ditasService, RegistrationService $registrationService,
                          SearchService $searchService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        $prescription_id = $request->input('prescription_id');
        $prescription = Prescription::query()->findOrFail($prescription_id);
        $bime_type = $prescription->issuertype;

        //$national_code = $request->input('national_code');
        $national_code = Prescription::where('id', $prescription_id)->value('national_code');

        if ($user->id != $prescription->user_id) {
            return $this->sendError("این نسخه مربوط به شما نمی باشد", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }


        $username = $user->employee->salamat_user;
        $pass = $user->employee->salamat_pass;
        $registerArray['prescription_id'] = $prescription_id;
        $doc = [
            'national_code' => $user->employee->national_code,
            'medical_no' => $user->employee->medical_no,
            'mobile' => $user->employee->taminmobile
        ];


        $precItems = Item::query()->where('prescription_id', $prescription_id)->get();
        if ($bime_type != 'T') {
//        if ($bime_type == '?') {
            $check_codes = Item::query()->where('prescription_id', $prescription_id)->whereNotNull('check_code')->pluck('check_code')->toArray();
            $register_salamat = $ditasService->salamatPrescriptionRegister($national_code, $username, $pass, $check_codes);
            if ($register_salamat == false || (isset($register_salamat['my_success']) && $register_salamat['my_success'] == false)) {
                return $this->sendError($register_salamat['my_message'] ?? " An unexpected error has occurred",
                    ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__],
                    Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            if ((isset($userSession['result']['data']['info']['isTwoStep']) && $userSession['result']['data']['info']['isTwoStep'] == true)) {
                return $this->sendError('جهت استفاده از سامانه لطفا لاگین دومرحله ای خود را در سازمان بیمه سلامت غیر فعال نمائید',
                    ['error' => 'otp', 'class' => __CLASS__, 'line' => __LINE__],
                    404);
            }
            $register = $register_salamat['result']['data']['info'];
            $registerArray['res_code'] = $register_salamat['result']['data']['resCode'];
            $registerArray['res_message'] = $register_salamat['result']['data']['resMessage'];;
            $registerArray['message'] = json_encode($register['message']);
            $registerArray['tracking_code'] = $register['trackingCode'];
            $registerArray['sequence'] = $register['sequenceNumber'];
            $registrationService->create($user, $registerArray);
            $tracking_code['tracking_code'] = $registerArray['tracking_code'];
        } else {
            //register tamin
            $up = UserProvider::where('user_id', $user->id)->where('is_Active', 1)->first();
            $siamID = @$up->provider->siam_code;
            if (empty($siamID)) {
                return $this->sendError("اطلاعات ورودی نامعتبر است",
                    ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__],
                    Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            $register_salamat = $ditasService->taminPrescriptionRegister($doc, $prescription, $siamID, $precItems);
            Log::channel('emed')->info(json_encode($register_salamat));
            if ($register_salamat == false ||
                (isset($register_salamat['my_success'])
                    && $register_salamat['my_success'] == false)) {
                if (isset($register_salamat['prescriptions'][0]['message']) && str_contains($register_salamat['prescriptions'][0]['message'], 'تلفن همراه')) {
                    return $this->sendError($register_salamat['prescriptions'][0]['message'] ?? "خطای ثبت نسخه",
                        ['error' => 'mobile', 'class' => __CLASS__, 'line' => __LINE__],
                        Response::HTTP_INTERNAL_SERVER_ERROR);
                } else {
                    return $this->sendError($register_salamat['prescriptions'][0]['message'] ?? "خطای ثبت نسخه",
                        ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__],
                        Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }
            $tracking_code = $register_salamat;
        }

        //import items to magic search tables

//        $doctor = $searchService->createOrUpdate($user, $precIems);

        $registeration_id = $prescription->registrations->first()->id;
        $tracking = $tracking_code['tracking_code'] ?? null;
        $success = [
            'tracking_code' => $tracking,
            'registration_id' => $registeration_id
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     *
     *
     * @OA\Post(
     *     path="/api/resend/prescription",
     *     tags={"Prescription"},
     *     summary="resend prescription to ditas register services",
     *     @OA\Parameter(name="registration_id", description="prescription registration id", example="9715c584-2398-4eca-8752-008c6c45e925", required=true, @OA\Schema(type="string", format="uuid"), in="query"),
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
     */
    public function resend(Request $request, PrescriptionService $prescriptionService, DitasService $ditasService, RegistrationService $registrationService,
                           SearchService $searchService, TaminService $taminService)
    {
        ThrottleFailedApi::limit(20);
        /* @var User $user */
        $user = auth('api')->user();
        $register_id = $request->input('registration_id');
        $validator = Validator::make(['register_id'=>$register_id], ['register_id' => [new UUID(), 'exists:App\Models\Prescription\Registration,id']]);

        if ($validator->fails()) {
            return $this->sendError('شناسه معتبر معتبر نیست', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }
        $register = Registration::query()->find($register_id);
        $prescription = $register->prescription;
        $bime_type = $prescription->issuertype;
        $national_code = $prescription->national_code;

        if ($user->id != $prescription->user_id) {
            return $this->sendError("این نسخه مربوط به شما نمی باشد", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }


        $username = $user->employee->salamat_user;
        $pass = $user->employee->salamat_pass;
        $doc = [
            'national_code' => $user->employee->national_code,
            'medical_no' => $user->employee->medical_no,
            'mobile' => $user->employee->taminmobile
        ];

        if ($bime_type != 'T') {
            $resend_salamat = $ditasService->salamatPrescriptionResend($national_code, $username, $pass, $prescription);
            if ($resend_salamat == false || (isset($resend_salamat['my_success']) && $resend_salamat['my_success'] == false)) {
                return $this->sendError($resend_salamat['my_message'] ?? " An unexpected error has occurred",
                    ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__],
                    Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            $reg = $resend_salamat['result']['data']['info'];
            $tracking_code = $reg['tracking_code'];
        } else {
            //register tamin
            $siamID = UserProvider::where('user_id', $user->id)->where('is_Active', 1)->first()->provider->siam_code;
            if (empty($siamID)) {
                return $this->sendError("اطلاعات ورودی نامعتبر است",
                    ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__],
                    Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            $resend_salamat = $taminService->taminPrescriptionResend($doc, $register, $siamID);
            Log::channel('emed')->info(json_encode($resend_salamat));
            if ($resend_salamat == false || (isset($resend_salamat['my_success']) && $resend_salamat == false)) {
                return $this->sendError($resend_salamat['my_message'] ?? "خطای ثبت نسخه",
                    ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__],
                    Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            $tracking_code = $resend_salamat['trackingCode'];
        }

//        $tracking = $tracking_code ?? null;
        $success = [
            'tracking_code' => $tracking_code,
            'registration_id' => $register->id
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/prescription/prescitem/{prescription}",
     *     tags={"Prescription"},
     *     summary="update a item",
     *     @OA\Parameter(name="prescription", example="3fdbaa71-28d6-441d-8698-00006e1c3f68", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\RequestBody(
     *     required=true,
     *      @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *               @OA\Property(
     *                    property="national_code",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="prescription_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="erx_type_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="erx_consumption_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="erx_instruction_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="count",
     *                    type="integer",
     *                    description="This statement was submitted to the Micfava company",
     *                 ),
     *               @OA\Property(
     *                    property="number_of_period",
     *                    type="integer",
     *                    description="This statement was submitted to the Micfava company",
     *                 ),
     *               @OA\Property(
     *                    property="bulk_id",
     *                    type="integer",
     *                    description="This statement was submitted to the Micfava company",
     *                 ),
     *               @OA\Property(
     *                    property="active_form",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                 ),
     *               @OA\Property(
     *                    property="description",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                 ),
     *                 example={"national_code":"2670132952","prescription_id":"96279eb6-261b-4dd0-84c4-0b6516d2772a",
     *                 "erx_item_id":"3fdbaa71-28d6-441d-8698-00006e1c3f68","description":"description to use",
     *                 "erx_type_id":"eba793ce-2f40-4fd4-944c-1a2cd17d0103","erx_consumption_id":"e2f58337-53e8-4026-a3eb-3203466b138e",
     *                 "erx_instruction_id":"91654014-c1fa-4d44-b8d5-0338e7f3473c","count":10,
     *                 "period":7,"bulk_id":0,"activeform":"14010210"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Item"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Item not found"
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
     * @param ItemUpdateRequest $request
     * @param Item $item
     * @param ItemService $itemService
     * @return JsonResponse [string] message
     */
    public function update(PrescriptionUpdateRequest $request, Prescription $prescription, PrescriptionService $prescriptionService)
    {
        //Done
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        // "ReadOnly: domain cannot be changed" ?

        //permission
        if (!$user->is_admin && $user->id != $prescription->domain->owner_id) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }
        $array = $request->only(array_keys($request->rules()));
        $array['secure_expire_time'] = filter_var($request->secure_expire_time ?? 24, FILTER_VALIDATE_INT);

        $prescriptionNULL = $prescriptionService->update($prescription, $array, $msg);
        if ($prescriptionNULL === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [
            'prescription' => $prescriptionService->hideAttributes($prescriptionNULL, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/prescription/{prescription}",
     *     tags={"Prescription"},
     *     summary="delete a prescription",
     *     @OA\Parameter(name="prescription", example="96a46aa0-7986-464e-b561-021ad3982b67", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
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
     *         description="Prescription not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Prescription $prescription
     * @param PrescriptionService $prescriptionService
     * @return JsonResponse [string] message
     */
    public function destroy(Prescription $prescription, PrescriptionService $prescriptionService, DitasService $ditasService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        $prescription_id = $prescription->id;
        $bime_type = $prescription->issuertype;
        $national_code = $prescription->national_code;
        $samad = $prescription->samadcode;

        if ($bime_type == 'T') {
            $this->sendError("سرویس حذف نسخه برای بیمه تامین اجتماعی فعال نمی باشد",
                ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }

        if ($user->id != $prescription->user_id) {
            return $this->sendError("این نسخه مربوط به شما نمی باشد", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }


        $username = $user->employee->salamat_user;
        $pass = $user->employee->salamat_pass;
        $check_codes = Item::query()->where('prescription_id', $prescription_id)
            ->whereNotNull('check_code')->pluck('check_code')->toArray();
        $updateResult = $ditasService->salamatPrescriptionDelete($national_code, $username, $pass, $samad);
        if (!$updateResult || (isset($updateResult['my_success']) && !$updateResult['my_success'])) {
            return $this->sendError($updateResult['my_message'] ?? " An unexpected error has occurred",
                ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__],
                Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = $prescriptionService->safeDelete($user, $prescription, $msg);
        if (!$response) {
            return $this->sendError($msg ?? "خطای حذف نسخه", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [
            'tracking_code' => $updateResult['result']['data']['info']['trackingCode']
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/fetch/prescription",
     *     tags={"Prescription"},
     *     summary="fetch Prescription by ID",
     *     description="Returns a single Prescription",
     *     @OA\Parameter(name="code", description="code to call", example="5940061524", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="tracking_code", description="tracking code", example="63669", required=true, @OA\Schema(type="string"), in="query"),
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
     *         description="Prescription not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param PrescriptionService $prescriptionService
     * @return JsonResponse [string] message
     */
    public function fetchPrescription(FetchPrescriptionRequest $request, PrescriptionService $prescriptionService, DitasService $ditasService)
    {

        /* @var User $user */
        $user = auth('api')->user();
        //permission in service
        $national_code = $request->input('code');
        $tracking_code = $request->input('tracking_code');

        $register = Registration::query()->where('tracking_code', $tracking_code)->get();
        $prescription_id = $register->first()->prescription_id;
        $prescription = Prescription::where('id', $prescription_id)->first();
        if ($prescription == null) {
            return $this->sendError("نسخه مورد نظر یافت نشد", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }

        if ($user->id != $prescription->user_id) {
            return $this->sendError("این نسخه مربوط به شما نمی باشد", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }

        $presc_gr = PrescFetchGroupResource::make($prescription);
        return $this->sendResponse($presc_gr, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/fetch/salamat/prescription",
     *     tags={"Prescription"},
     *     summary="fetch Prescription by ID",
     *     description="Returns a single Prescription",
     *     @OA\Parameter(name="code", description="code to call", example="5940061524", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="tracking_code", description="tracking code", example="63669", required=true, @OA\Schema(type="string"), in="query"),
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
     *         description="Prescription not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param PrescriptionService $prescriptionService
     * @return JsonResponse [string] message
     */
    public function fetchSalamatPrescription(FetchPrescriptionRequest $request, PrescriptionService $prescriptionService, DitasService $ditasService)
    {

        /* @var User $user */
        $user = auth('api')->user();
        //permission in service
        $national_code = $request->input('code');
        $tracking_code = $request->input('tracking_code');

        $register = Registration::where('tracking_code', $tracking_code)->first();
        $prescription_id = $register->prescription_id;
        $prescription = Prescription::whereId($prescription_id)->first();
        $bime_type = $prescription->issuertype;
        $register = Registration::query()->where('tracking_code', $tracking_code)->get();
        $prescription_id = $register->first()->prescription_id;
        $prescription = Prescription::where('id',$prescription_id)->first();
        if ($prescription==null) {
            return $this->sendError("نسخه مورد نظر یافت نشد", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }
        if ($bime_type=='T') {
            return $this->sendError("این سرویس فقط برای بیمه سلامت برقرار است", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }

        $samad = $prescription->samadcode;

        if ($user->id != $prescription->user_id) {
            return $this->sendError("این نسخه مربوط به شما نمی باشد", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }


        $username = $user->employee->salamat_user;
        $pass = $user->employee->salamat_pass;

        $fetch_presc = $ditasService->salamatPrescriptionFetch($national_code, $username, $pass, $samad);
        if ($fetch_presc['my_success'] === false) {
            return $this->sendError($register_salamat['my_message'] ?? "An unexpected error has occurred",
                ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__],
                Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $presc = $prescriptionService->decoratePrescription(($fetch_presc['result']['data']['info']), $bime_type);

        return $this->sendResponse($presc, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/print/prescription",
     *     tags={"Prescription"},
     *     summary="print specific prescription",
     *     description="Returns a single Prescription pdf",
     *     @OA\Parameter(name="registration_id", description="registration to call", example="974CD6A9-E6B2-42C6-8962-6916C38B8D75", required=true, @OA\Schema(type="string"), in="query"),
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
     *         description="Prescription not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param PrintPrescriptionRequest $request
     * @param PrescriptionService $prescriptionService
     * @param DitasService $ditasService
     * @return JsonResponse [string] message
     */
    public function printPrescription(PrintPrescriptionRequest $request, PrescriptionService $prescriptionService, DitasService $ditasService)
    {

        /* @var User $user */
        $user = auth('api')->user();

        //permission in service
        $registration_id = $request->input('registration_id');

        $prescription = Registration::find($registration_id)->prescription;
//        if ($user->id != $prescription->user_id) {
//            return $this->sendError("این نسخه مربوط به شما نمی باشد", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
//        }

        //prescription data
        $presc_data = PrintResource::make($prescription);
        $success = [
            'presc_data' => $presc_data
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/doctor/prescription",
     *     tags={"Prescription"},
     *     summary="doctor Prescription by ID",
     *     description="Returns doctors previous Prescription",
     *     @OA\Parameter(name="page", description="page of patterns", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
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
     *         description="Prescription not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param PrescriptionService $prescriptionService
     * @return JsonResponse [string] message
     */
    public function doctorPrescription(Request $request, PrescriptionService $prescriptionService, DitasService $ditasService)
    {
        /* @var User $user */
        $user = auth('api')->user();
        $employee = $user->employee;
        $user_id = $user->id;

        $limit = $request->input('limit') ?? 10;
        $presc_id = DB::table('prescriptions as pre')->where('pre.user_id', $user_id)->orderBy('created_at', 'desc')
            ->pluck('id')->toArray();
        $history_presc = DB::table('prescription_items as pre_i')
            ->whereIn('Pre_i.prescription_id', $presc_id)
            ->select('Pre_i.prescription_id', 'i.id')
            ->join('erx_items as i', 'i.id', '=', 'pre_i.erx_item_id')
            ->orderBy('pre_i.created_at', 'desc')
            ->get();
//        $history_model = ErxItem::query()->whereIn('id', $history_presc)->get();
        $history_pr = $history_presc->unique('id')->take($limit);
        $history = HistoryPaginateResource::collection($history_pr);
        $success = [
            'history' => $history
        ];
        return $this->sendResponse($success, 'success.');
    }

}
