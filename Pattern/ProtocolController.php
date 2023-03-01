<?php

namespace App\Http\Controllers\Pattern;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ThrottleFailedApi;
use App\Http\Requests\FilterRequest;
use App\Http\Requests\Pattern\DoctorProtocolNewRequest;
use App\Http\Requests\Pattern\DoctorProtocolUpdateRequest;
use App\Http\Requests\Pattern\FilterProtocolRequest;
use App\Http\Requests\Pattern\ProtocolNameRequest;
use App\Http\Requests\Pattern\ProtocolNewRequest;
use App\Http\Resources\Pattern\HeaderResource;
use App\Http\Resources\Pattern\ItemResource;
use App\Http\Resources\Pattern\ProtocolPaginateResource;
use App\Models\Erx\Therapeutic;
use App\Models\Identity\User;
use App\Models\Pattern\Header;
use App\Models\Pattern\Pattern;
use App\Repositories\Pattern\PatternRepository;
use App\Rules\UUID;
use App\Services\Pattern\HeaderService;
use App\Services\Pattern\ItemService;
use App\Services\Pattern\PatternService;
use App\Services\Pattern\ProtocolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes\Head;


class ProtocolController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/pattern/protocol",
     *     tags={"Protocol"},
     *     summary="List of Protocols",
     *     @OA\Parameter(name="page", description="page of patterns", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="filters", description="filters is an object include to and from.  type =(named,created)",
     *     required=false,
     *     example="{""from"":""2021-08-10"",""to"":""2021-09-10"",""specialty_id"":""46ed2c4b-993b-4a2b-8c43-00abf5c93433"",""type"": ""named""}",
     *     style="deepObject",
     *     @OA\Schema(type="object"),
     *      in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Pattern"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param FilterRequest $request
     * @param HeaderService $headerService
     * @param ItemService $itemService
     * @return JsonResponse [string] message
     */
    public function index(FilterProtocolRequest $request, HeaderService $headerService)
    {
        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service

        if ($user->employee === null) {
            return $this->sendError("برای این کاربر اطلاعات کامل نشده است", [
                'error' => 'Not found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }
        $limit = $request->input('limit') ?? 10;

        $headers = $headerService->get($user, null, $request->input('filters'), 10, $paginate = false);


        if ($headers === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        $protocols_pg = new ProtocolPaginateResource($headers);
        $success = [
            'Protocols' => $protocols_pg,
        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Create the specified protocol.
     *
     * @OA\Post(
     *     path="/api/pattern/protocol/create",
     *     tags={"Protocol"},
     *     summary="Store new protocol",
     *     @OA\RequestBody(
     *     required=true,
     *      @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *               @OA\Property(
     *                    property="specialty_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="erx_therapeutic_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="erx_agegroup_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="patterns_items",
     *                    type="object",
     *                    description="items",
     *                    @OA\AdditionalProperties(type="object",
     *               @OA\Property(
     *                    property="standard_code",
     *                    type="integer",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *              @OA\Property(
     *                    property="name",
     *                    type="integer",
     *                    description="This statement status"
     *                 ),
     *                 @OA\Property(
     *                    property="count",
     *                    type="integer",
     *                    description="The staff member is present at work"
     *                 ),
     *               @OA\Property(
     *                    property="erx_consumption_id",
     *                    type="string",
     *                    description="Sending signature address ",
     *                 ),
     *               @OA\Property(
     *                    property="erx_instruction_id",
     *                    type="string",
     *                    description="Sending signature address ",
     *                 ),
     *               @OA\Property(
     *                    property="description",
     *                    type="string",
     *                    description="description  ",
     *                 ),
     *               @OA\Property(
     *                    property="erx_type_id",
     *                    type="string",
     *                    description="description  ",
     *                 ),
     *                    )
     *               ),
     *                 example={"specialty_id":"7b23b7b7-a5dc-47c6-8b1d-13ffa0af4837",
     *                 "erx_therapeutic_id":"dfe92a39-91fd-40bf-94a2-00d5f0f26039","erx_agegroup_id":"5131997f-54c1-4d6b-bb3e-0bb6ebe65def",
     *                 "drug": {{"standard_code":"1036","name": "drug_name","count": 1,
     *                 "erx_consumption_id": "d32837cf-8b99-4c53-a6c5-1e7578aba29b",
     *                 "erx_instruction_id":"2874ab60-e7cf-4e8f-9917-3f9425afed83","description":"description","erx_type_id":"566dc0f6-5db9-4781-9fc5-0c8d78fcfb7a"}},
     *                 "rvu": {{"standard_code":"1036","name": "drug_name","count": 1,
     *                 "description":"description","erx_type_id":"566dc0f6-5db9-4781-9fc5-0c8d78fcfb7a"}}}
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response="200",
     *         description="successful operation",
     *     ),
     * @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     * @OA\Response(
     *         response=404,
     *         description="Statement not found"
     *     ),
     * @OA\Response(
     *         response=405,
     *         description="Validation exception"
     *     ),
     * @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param ProtocolNewRequest $request
     * @param ProtocolService $patternService
     * @return JsonResponse [string] message
     */
    public function createProtocol(ProtocolNewRequest $request, HeaderService $headerService, ItemService $itemService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        if (!$user->is_admin && strtolower($user->employee->specialty_id) != strtolower($request->input('specialty_id'))) {
            return $this->sendError("اجازه ثبت پروتکل را ندارید", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }

        $headerKey = ['specialty_id', 'erx_therapeutic_id', 'erx_agegroup_id'];
        //permi
        //todo


        //$array = $request->only(array_keys($request->rules()));
        $headerArray = $request->only($headerKey);
        $headerArray['created_by'] = auth('api')->user()->id;
        $drugsArray = $request->except($headerKey)['drug'];
        $rvusArray = $request->except($headerKey)['rvu'];

        $header = $headerService->create($user, $headerArray, $msg);
        if ($header === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $drugs = $itemService->createAll($user, $header->id, $drugsArray);
        if ($drugs === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $rvus = $itemService->createAll($user, $header->id, $rvusArray);
        if ($rvus === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }


        $header = Header::query()->find($header->id);
        $success = [
            'header' => HeaderResource::make($header),
            'drugs' => ItemResource::collection($drugs),
            'rvus' => ItemResource::collection($rvus),
        ];
        Log::channel('uptodate')->info('الگوی مورد نظر ایجاد شد', $success);
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Name the specified protocol.
     *
     * @OA\Post(
     *     path="/api/pattern/protocol/name",
     *     tags={"Protocol"},
     *     summary="Store new protocol",
     *     @OA\RequestBody(
     *     required=true,
     *      @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *               @OA\Property(
     *                    property="specialty_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="pattern_id",
     *                    type="integer",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="erx_therapeutic_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="erx_agegroup_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="patterns_items",
     *                    type="object",
     *                    description="items",
     *                    @OA\AdditionalProperties(type="object",
     *               @OA\Property(
     *                    property="standard_code",
     *                    type="integer",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *              @OA\Property(
     *                    property="name",
     *                    type="integer",
     *                    description="This statement status"
     *                 ),
     *                 @OA\Property(
     *                    property="count",
     *                    type="integer",
     *                    description="The staff member is present at work"
     *                 ),
     *               @OA\Property(
     *                    property="erx_consumption_id",
     *                    type="string",
     *                    description="Sending signature address ",
     *                 ),
     *               @OA\Property(
     *                    property="erx_instruction_id",
     *                    type="string",
     *                    description="Sending signature address ",
     *                 ),
     *               @OA\Property(
     *                    property="description",
     *                    type="string",
     *                    description="description  ",
     *                 ),
     *               @OA\Property(
     *                    property="erx_type_id",
     *                    type="string",
     *                    description="description  ",
     *                 ),
     *                    )
     *               ),
     *                 example={"specialty_id":"7b23b7b7-a5dc-47c6-8b1d-13ffa0af4837","pattern_id":328,
     *                 "erx_therapeutic_id":"dfe92a39-91fd-40bf-94a2-00d5f0f26039","erx_agegroup_id":"5131997f-54c1-4d6b-bb3e-0bb6ebe65def",
     *                 "drug": {{"standard_code":"1036","name": "drug_name","count": 1,
     *                 "erx_consumption_id": "d32837cf-8b99-4c53-a6c5-1e7578aba29b",
     *                 "erx_instruction_id":"2874ab60-e7cf-4e8f-9917-3f9425afed83","description":"description","erx_type_id":"566dc0f6-5db9-4781-9fc5-0c8d78fcfb7a"}},
     *                 "rvu": {{"standard_code":"1036","name": "drug_name","count": 1,
     *                 "description":"description","erx_type_id":"566dc0f6-5db9-4781-9fc5-0c8d78fcfb7a"}}}
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response="200",
     *         description="successful operation",
     *     ),
     * @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     * @OA\Response(
     *         response=404,
     *         description="Statement not found"
     *     ),
     * @OA\Response(
     *         response=405,
     *         description="Validation exception"
     *     ),
     * @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param ProtocolNameRequest $request
     * @param PatternService $patternService
     * @return JsonResponse [string] message
     */
    public function nameProtocol(ProtocolNameRequest $request, HeaderService $headerService, ItemService $itemService,
                                 PatternService      $patternService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        if (!$user->is_admin && strtolower($user->employee->specialty_id) != strtolower($request->input('specialty_id'))) {
            return $this->sendError("اجازه ثبت پروتکل را ندارید", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }

        $headerKey = ['specialty_id', 'pattern_id', 'erx_therapeutic_id', 'erx_agegroup_id'];
        //permi
        //todo


        //$array = $request->only(array_keys($request->rules()));
        $headerArray = $request->only($headerKey);
        $check = $headerService->checkIsUnique($headerArray);
        if ($check != null) {
            return $this->sendError("This Protocol is registered before", ['code' => 202, 'class' => __CLASS__, 'line' => __LINE__], 500);
        }
        $headerArray['created_by'] = auth('api')->user()->id;
        $drugsArray = $request->except($headerKey)['drug'];
        $rvusArray = $request->except($headerKey)['rvu'];

        $header = $headerService->create($user, $headerArray, $msg);
        if ($header === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $drugs = $itemService->createAll($user, $header->id, $drugsArray);
        if ($drugs === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $rvus = $itemService->createAll($user, $header->id, $rvusArray);
        if ($rvus === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $header = Header::query()->find($header->id);
        $success = [
            'header' => HeaderResource::make($header),
            'drugs' => ItemResource::collection($drugs),
            'rvus' => ItemResource::collection($rvus),
        ];

        //$patternService = new PatternService(new PatternRepository(new Pattern()));
        $patterns = Pattern::query()->where('prescription_no', $request->input('pattern_id'))
            ->where('specialty_id', $request->input('specialty_id'))->get();
        foreach ($patterns as $pattern) {
            $patternService->update($pattern, ['is_active' => 0]);
        }
        Log::channel('uptodate')->info('الگوی مورد نظر نام گذاری شد', $success);
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/pattern/doctor/protocol",
     *     tags={"Protocol"},
     *     summary="List of doctor's Protocols",
     *     @OA\Parameter(name="page", description="page of patterns", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="medical_no", description="doctors medical number", example="181390", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Pattern"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param FilterRequest $request
     * @param HeaderService $headerService
     * @param ItemService $itemService
     * @return JsonResponse [string] message
     */
    public function doctorProtocols(Request $request, HeaderService $headerService)
    {
        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service

        if ($user->employee === null) {
            return $this->sendError("برای این کاربر اطلاعات کامل نشده است", [
                'error' => 'Not found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }
        $limit = $request->input('limit') ?? 10;
        $medical_no = $request->input('medical_no');

        $doctor_headers = Header::where('medical_no', $medical_no)->paginate();
        $headers = Header::Where('medical_no', null)->paginate();


        if ($headers === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        $protocols_pg = new ProtocolPaginateResource($headers);
        $doctor_protocols_pg = new ProtocolPaginateResource($doctor_headers);
        $success = [
            'doctor_protocols' => $doctor_protocols_pg,
            'Protocols' => $protocols_pg,
        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Create the specified protocol.
     *
     * @OA\Post(
     *     path="/api/pattern/doctor/protocol/create",
     *     tags={"Protocol"},
     *     summary="Store new protocol for doctor",
     *     @OA\RequestBody(
     *     required=true,
     *      @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *               @OA\Property(
     *                    property="specialty_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="erx_therapeutic_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="erx_therapeutic_en_name",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="erx_therapeutic_fa_name",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="erx_agegroup_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="patterns_items",
     *                    type="object",
     *                    description="items",
     *                    @OA\AdditionalProperties(type="object",
     *               @OA\Property(
     *                    property="standard_code",
     *                    type="integer",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *              @OA\Property(
     *                    property="name",
     *                    type="integer",
     *                    description="This statement status"
     *                 ),
     *                 @OA\Property(
     *                    property="count",
     *                    type="integer",
     *                    description="The staff member is present at work"
     *                 ),
     *               @OA\Property(
     *                    property="erx_consumption_id",
     *                    type="string",
     *                    description="Sending signature address ",
     *                 ),
     *               @OA\Property(
     *                    property="erx_instruction_id",
     *                    type="string",
     *                    description="Sending signature address ",
     *                 ),
     *               @OA\Property(
     *                    property="description",
     *                    type="string",
     *                    description="description  ",
     *                 ),
     *               @OA\Property(
     *                    property="erx_type_id",
     *                    type="string",
     *                    description="description  ",
     *                 ),
     *                    )
     *               ),
     *                 example={"specialty_id":"7b23b7b7-a5dc-47c6-8b1d-13ffa0af4837",
     *                 "erx_therapeutic_id":"73b9c6d4-448c-45e6-a5ba-2c74db3d9979","erx_therapeutic_en_name":"Movement disorders",
     *                 "erx_therapeutic_fa_name":"اختلالات حرکتي","erx_agegroup_id":"5131997f-54c1-4d6b-bb3e-0bb6ebe65def",
     *                 "items": {{"standard_code":"1036","name": "drug_name","count": 1,
     *                 "erx_consumption_id": "d32837cf-8b99-4c53-a6c5-1e7578aba29b",
     *                 "erx_instruction_id":"2874ab60-e7cf-4e8f-9917-3f9425afed83","description":"description","erx_type_id":"566dc0f6-5db9-4781-9fc5-0c8d78fcfb7a"}}}
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response="200",
     *         description="successful operation",
     *     ),
     * @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     * @OA\Response(
     *         response=404,
     *         description="Statement not found"
     *     ),
     * @OA\Response(
     *         response=405,
     *         description="Validation exception"
     *     ),
     * @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param ProtocolNewRequest $request
     * @param ProtocolService $patternService
     * @return JsonResponse [string] message
     */
    public function createDoctorProtocol(DoctorProtocolNewRequest $request, HeaderService $headerService, ItemService $itemService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        $medical_no = $user->employee->medical_no;

        $items = $request->input('items');
        $therapeutic_id = $request->input('erx_therapeutic_id');
        $headerKey = ['specialty_id', 'erx_therapeutic_id', 'erx_therapeutic_en_name',
            'erx_therapeutic_fa_name', 'erx_agegroup_id'];
        $headerArray = $request->only($headerKey);
        $headerArray['created_by'] = auth('api')->user()->id;
        $codes = Arr::pluck($items, 'standard_code');

        if (isset($therapeutic_id)) {
            $header = Header::where('erx_therapeutic_id', $headerArray['erx_therapeutic_id'])->first();
            if ($header == null) {
                return $this->sendError('پروتکل مورد نظر موجود نیست.',
                    ['class' => __CLASS__, 'line' => __LINE__], 404);
            }
            $protocol_codes = $header->items()->pluck('standard_code')->toArray();
            if ($codes == $protocol_codes) {
                return $this->sendError('این پروتکل قبلا ثبت شده است.',
                    ['class' => __CLASS__, 'line' => __LINE__], 404);
            }
        }

        $therapeutic = new Therapeutic();
        $therapeutic->fa_name = $headerArray['erx_therapeutic_fa_name'];
        $therapeutic->en_name = $headerArray['erx_therapeutic_en_name'];
        $therapeutic->created_by = $user->id;
        $therapeutic->save();
        $headerArray['erx_therapeutic_id'] = $therapeutic->id;

        $headerArray['medical_no'] = $medical_no;
        $header = $headerService->create($user, $headerArray, $msg);
        if ($header === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $pattern_items = $itemService->createAll($user, $header->id, $items);
        if ($pattern_items === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }


        $header = Header::query()->find($header->id);
        $header->searchable();
        $success = [
            'header' => HeaderResource::make($header),
            'items' => ItemResource::collection($pattern_items),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Update the specified protocol.
     *
     * @OA\Put(
     *     path="/api/pattern/doctor/protocol/update",
     *     tags={"Protocol"},
     *     summary="update protocol for doctor",
     *     @OA\RequestBody(
     *     required=true,
     *      @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *               @OA\Property(
     *                    property="header_id",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company",
     *                    format="uuid"
     *                 ),
     *               @OA\Property(
     *                    property="patterns_items",
     *                    type="object",
     *                    description="items",
     *                    @OA\AdditionalProperties(type="object",
     *               @OA\Property(
     *                    property="standard_code",
     *                    type="integer",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *              @OA\Property(
     *                    property="name",
     *                    type="integer",
     *                    description="This statement status"
     *                 ),
     *                 @OA\Property(
     *                    property="count",
     *                    type="integer",
     *                    description="The staff member is present at work"
     *                 ),
     *               @OA\Property(
     *                    property="erx_consumption_id",
     *                    type="string",
     *                    description="Sending signature address ",
     *                 ),
     *               @OA\Property(
     *                    property="erx_instruction_id",
     *                    type="string",
     *                    description="Sending signature address ",
     *                 ),
     *               @OA\Property(
     *                    property="description",
     *                    type="string",
     *                    description="description  ",
     *                 ),
     *               @OA\Property(
     *                    property="erx_type_id",
     *                    type="string",
     *                    description="description  ",
     *                 ),
     *                    )
     *               ),
     *                 example={"header_id":"97B58124-D9F3-4121-B22D-FA900FDD777A",
     *                 "items": {{"standard_code":"1036","name": "drug_name","count": 1,
     *                 "erx_consumption_id": "d32837cf-8b99-4c53-a6c5-1e7578aba29b",
     *                 "erx_instruction_id":"2874ab60-e7cf-4e8f-9917-3f9425afed83","description":"description","erx_type_id":"566dc0f6-5db9-4781-9fc5-0c8d78fcfb7a"}}}
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response="200",
     *         description="successful operation",
     *     ),
     * @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     * @OA\Response(
     *         response=404,
     *         description="Statement not found"
     *     ),
     * @OA\Response(
     *         response=405,
     *         description="Validation exception"
     *     ),
     * @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param ProtocolNewRequest $request
     * @param ProtocolService $patternService
     * @return JsonResponse [string] message
     */
    public function updateDoctorProtocol(DoctorProtocolUpdateRequest $request, HeaderService $headerService, ItemService $itemService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        $medical_no = $user->employee->medical_no;

        $items = $request->input('items');
        $header_id = $request->input('header_id');
        $codes = Arr::pluck($items, 'standard_code');


        $pattern_items = $itemService->createAll($user, $header_id, $items);
        if ($pattern_items === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }


        $header = Header::query()->find($header_id);
        $success = [
            'header' => HeaderResource::make($header),
            'items' => ItemResource::collection($pattern_items),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/pattern/doctor/protocol/delete",
     *     tags={"Protocol"},
     *     summary="delete a protocol",
     *     @OA\Parameter(name="header_id", example="97B5AA5F-41A6-4D14-81DD-2574E2749A97", required=true, @OA\Schema(type="string", format="uuid"), in="query"),
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
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @return JsonResponse [string] message
     */
    public function deleteProtocol(Request $request)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        $header_id = $request->input('header_id');
        $validator = Validator::make(['header_id' => $header_id ], ['header_id' => [new UUID(), 'exists:App\Models\Pattern\Header,id']]);
        if ($validator->fails()) {
            return $this->sendError('شناسه پروتکل معتبر نیست', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }
        $header = Header::find($header_id);
        $header->items()->delete();
        $header->delete();

        $success = [];
        return $this->sendResponse($success, 'success.');
    }

}
