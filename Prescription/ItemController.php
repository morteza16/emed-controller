<?php

namespace App\Http\Controllers\Prescription;

use App\Http\Requests\FilterRequest;
use App\Http\Requests\Prescription\ItemDeleteRequest;
use App\Http\Requests\Prescription\ItemNewRequest;
use App\Http\Requests\Prescription\ItemUpdateRequest;
use App\Http\Resources\Prescription\ItemPaginateResource;
use App\Http\Resources\Prescription\ItemResource;
use App\Models\Erx\Consumption;
use App\Models\Erx\ErxItem;
use App\Models\Erx\Instruction;
use App\Models\Erx\Type;
use App\Models\Erx\ErxItemBimeh;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ThrottleFailedApi;
use App\Models\Prescription\Item;
use App\Models\Prescription\Log;
use App\Models\Prescription\Prescription;
use App\Services\DitasService;
use App\Services\Gateways\Ditas\Salamat;
use App\Services\Prescription\ItemLogService;
use App\Services\Prescription\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /**
 * Class Item.
 *
 * @package namespace App\Models\Prescription;
 * @OA\Schema ( required={"id"}, schema="PrescriptionItem")
 * App\Models\Prescription\Item
 */
class ItemController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/prescription/prescitem",
     *     tags={"Prescription-Item"},
     *     summary="List of Items",
     *     @OA\Parameter(name="page", description="page of items", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="prescription_id", description="filter items for a prescription", example="9692f175-54dd-4a84-b617-00004242f1a1", required=false, @OA\Schema(type="string", format="uuid"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Item"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param FilterRequest $request
     * @param ItemService $itemService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, ItemService $itemService)
    {


        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service
        $limit = $request->input('limit') ?? 10;
        $prescription_id = $request->input('prescription_id') ;
        $items = Item::query();
        if(isset($prescription_id)){
            $items = $items->where('prescription_id', $prescription_id);
        }
        $items = $items->with('item')->paginate($limit);


        if ($items === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        $items_pg = new ItemPaginateResource($items);
        $success = [
            'items' => $items_pg,

        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/prescription/prescitem/{id}",
     *     tags={"Prescription-Item"},
     *     summary="Show/Find Item by ID",
     *     description="Returns a single Item",
     *     @OA\Parameter(name="id", description="ID of Item to return", example="47e41f4b-35fc-49ac-a8aa-0001bcc0c0ec", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
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
     * @param Request $request
     * @param $id
     * @param ItemService $itemService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, ItemService $itemService)
    {
        $item_id = $id;

        /* @var User $user */
        $user = auth('api')->user();

        //permission in service


        $item = $itemService->first($user, $item_id);
        if (!$item) {
            return $this->sendError('Item not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [
            'Item' => new ItemResource($item),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/api/prescription/prescitem",
     *     tags={"Prescription-Item"},
     *     summary="store a new item",
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
     *                    property="erx_item_id",
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
     *         response=405,
     *         description="Validation exception"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param ItemNewRequest $request
     * @param ItemService $itemService
     * @return JsonResponse [string] message
     */
    public function store(ItemNewRequest $request, ItemService $itemService, DitasService $ditasService, ItemLogService $itemLogService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        $national_code = $request->input('national_code');
        $prescription_id = $request->input('prescription_id');
        $bime_type = Prescription::find($prescription_id)->issuertype;
        $itemKey = ['prescription_id', 'erx_item_id', 'erx_type_id', 'erx_consumption_id', 'erx_instruction_id', 'count',
            'period', 'bulk_id', 'activeform', 'description'];
        $itemArray = $request->only($itemKey);
        $username = $user->employee->salamat_user;
        $pass = $user->employee->salamat_pass;

        $item = Item::where('prescription_id', $prescription_id)->where('erx_item_id', $itemArray['erx_item_id'])->first();
        if(isset($item)){
            $type_name = $item->item->type->name;
            $msg = $type_name == 'drug' ? 'دارو' : 'خدمت';
            return $this->sendError("این $msg قبلا در این نسخه ثبت شده است.", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);

        }
//todo add this after changes in ui

//        $taminInstructions = Instruction::query()->whereNotNull('tamin')->whereId($itemArray['erx_instruction_id'])->first();
//        $taminconsumptions = Consumption::query()->whereNotNull('tamin_code')->whereId($itemArray['erx_consumption_id'])->first();
//        if($bime_type == 'T' && ($taminInstructions==null || $taminconsumptions==null)){
//            return $this->sendError("توضیحات مصرف دارو معتبر نیست.", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
//        }

        $type = Type::find($itemArray['erx_type_id']);
        $prescItem['nationalNumber'] = ErxItemBimeh::whereId($itemArray['erx_item_id'])->value('compact');
        $prescItem['count'] = $itemArray['count'] ?? 1;
        $prescItem['type'] = $type->name;
        $prescItem['mode'] = $type->mode;
        $prescItem['description'] = $itemArray['description'] ?? '';
        $consumption = $bime_type == 'T' ? 'tamin_code' : 'name';
        $prescItem['consumption'] = Consumption::whereId($itemArray['erx_consumption_id'])->value($consumption) ?? '';
        $instruction = $bime_type == 'T' ? 'tamin' : 'salamat';
        $prescItem['consumptionInstruction'] = Instruction::whereId($itemArray['erx_instruction_id'])->value($instruction) ?? '';
        $prescItem['numberOfPeriod'] = $itemArray['period'];
        $prescItem['bulkId'] = $itemArray['bulk_id'] ?? 1;
        $prescItem['activeForm'] = $itemArray['activeform'] ?? null;

        $is_sharbat = ErxItem::whereId($itemArray['erx_item_id'])->first()->is_sharbat;
        if(($prescItem['type']=='drug' && empty($prescItem['consumption'])) ){
            return $this->sendError("تواتر مصرف به درستی وارد نشده است.", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        if($prescItem['type']=='drug' && $is_sharbat && empty($prescItem['consumptionInstruction'])){
            return $this->sendError("میزان مصرف به درستی وارد نشده است.", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        //todo if patient is from salamat call prescription item service else jsut save in db
        if ($bime_type != 'T') {
            //if bime salamat
            $revokedCheckCode = $itemService->getRevokedCheckCodes($prescription_id);
            if (!empty($revokedCheckCode)) {
                return $this->sendError("کد چک های نسخه منقضی شده است.", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
                //todo find a good solution for this problem
            }
            //call services
            $result = $ditasService->salamatPrescriptionItem($national_code, $username, $pass, $prescItem, $prescription_id, null);
            if ($result == false || (isset($result['my_success']) && $result['my_success'] == false)) {
                //todo retry
                return $this->sendError($result['my_message'] ?? " An unexpected error has occurred",
                    ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__],
                    Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            if ((isset($userSession['result']['data']['info']['isTwoStep']) && $userSession['result']['data']['info']['isTwoStep'] == true)) {
                return $this->sendError('جهت استفاده از سامانه لطفا لاگین دومرحله ای خود را در سازمان بیمه سلامت غیر فعال نمائید',
                    ['error' => 'otp', 'class' => __CLASS__, 'line' => __LINE__],
                    404);
            }
            $checkCode = $result['result']['data']['info']['checkCode'];

            $resCode = $result['result']['data']['resCode'];
            if ($resCode != 1) {
                return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
            }
        }

        //unset($itemArray['national_code']);
        $tamin_type = ErxItem::where('id', $itemArray['erx_item_id'])->value('erx_type_idtamin');
        $itemArray['created_by'] = $user->id;
        $itemArray['created_at'] = now();
        $itemArray['erx_type_id'] = $bime_type == 'T' ? $tamin_type : $itemArray['erx_type_id'];
        $itemArray['mode'] = $type->mode;
        $itemArray['check_code'] = $checkCode ?? null;
        $item = $itemService->create($user, $itemArray, $msg);
        if ($item === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }
        $msg = $result['result']['data']['info']['message'] ?? '';
        $itemLog['prescription_item_id'] = $item->id;
        $itemLog['res_code'] = $resCode ?? null;
        $itemLog['is_allowed'] = $result['result']['data']['info']['isAllowed'] ?? 1;
        $itemLog['contract'] = $result['result']['data']['info']['hasContract'] ?? null;
        $itemLog['message'] = json_encode($msg);
        $itemLog['check_code'] = $result['result']['data']['info']['checkCode'] ?? null;
        $itemLog['max_covered'] = $result['result']['data']['info']['maxCoveredCount'] ?? 0;
        $itemLog['created_by'] = $user->id;
        $itemLogService->create($user, $itemLog);

        $success = [
            'item' => itemResource::make($item),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/prescription/prescitem/{item}",
     *     tags={"Prescription-Item"},
     *     summary="update a item",
     *     @OA\Parameter(name="item", example="3fdbaa71-28d6-441d-8698-00006e1c3f68", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
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
     *                 "description":"description to use",
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
     * @param $item
     * @param ItemService $itemService
     * @return JsonResponse [string] message
     */
    public function update(ItemUpdateRequest $request, $item, ItemService $itemService, DitasService $ditasService)
    {
        //Done
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        $prescription_id = $request->input('prescription_id');
        $prescription = Prescription::query()->findOrFail($prescription_id);
        $national_code = $request->input('national_code');
        $bime_type = Prescription::find($prescription_id)->issuertype;

        if ($user->id != $prescription->user_id) {
            return $this->sendError("این نسخه مربوط به شما نمی باشد", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }

        $prescription_item = \App\Models\Prescription\Item::query()->where('prescription_id', $prescription_id)->where('erx_item_id', $item)->first();
        if(!isset($prescription_item)){
            return $this->sendError($msg ?? "آیتم مورد نظر در این نسخه وجود ندارد", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }


        $itemKey = ['prescription_id', 'erx_type_id', 'erx_consumption_id', 'erx_instruction_id', 'count',
            'period', 'bulk_id', 'activeform', 'description'];
        $itemArray = $request->only($itemKey);
        $username = $user->employee->salamat_user;
        $pass = $user->employee->salamat_pass;
        $type = Type::find($itemArray['erx_type_id']);

        $prescItem['nationalNumber'] = ErxItemBimeh::whereId($item)->value('compact');
        $prescItem['count'] = $itemArray['count'];
        $prescItem['type'] = $type->name;
        $prescItem['mode'] = $type->mode;
        $prescItem['description'] = $itemArray['description'] ?? '';
        $prescItem['consumption'] = Consumption::whereId($itemArray['erx_consumption_id'])->value('name') ?? '';
        $prescItem['consumptionInstruction'] = Instruction::whereId($itemArray['erx_instruction_id'])->value('salamat') ?? '';
        $prescItem['numberOfPeriod'] = $itemArray['period'];
        $prescItem['bulkId'] = $itemArray['bulk_id'] ?? 1;
        $prescItem['activeForm'] = $itemArray['activeform'] ?? '';
//        $itemResult = $ditasService->getPrescrptionItem($national_code, $username, $pass, $prescItem);

        if ($bime_type != 'T') {
            //if bime salamat
            $revokedCheckCode = $itemService->getRevokedCheckCodes($prescription_id);
            if (!empty($revokedCheckCode)) {
                return $this->sendError("کد چک های نسخه منقضی شده است.", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
                //todo find a good solution for this problem
            }
            //call services
            $result = $ditasService->salamatPrescriptionItem($national_code, $username, $pass, $prescItem, $prescription_id, $item);
            if ($result == false || (isset($result['my_success']) && $result['my_success'] == false)) {
                //todo retry
                return $this->sendError($result['my_message'] ?? " An unexpected error has occurred",
                    ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__],
                    Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            $checkCode = $result['result']['data']['info']['checkCode'];

            $resCode = $result['result']['data']['resCode'];
            if ($resCode != 1) {
                return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
            }
        }

        $itemArray['updated_at'] = now();
        $itemArray['mode'] = $type->mode;
        $itemArray['check_code'] = $checkCode ?? null;

        $itemNULL = $itemService->update($prescription_item, $itemArray, $msg);
        if ($itemNULL === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [
            'item' => $itemService->hideAttributes($itemNULL, $user),
        ];
        return $this->sendResponse([], 'success.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/prescription/prescitem/{item}",
     *     tags={"Prescription-Item"},
     *     summary="delete a item",
     *     @OA\Parameter(name="item", example="3fdbaa71-28d6-441d-8698-00006e1c3f68", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Parameter(name="prescription_id", example="96279eb6-261b-4dd0-84c4-0b6516d2772a", required=true, @OA\Schema(type="string", format="uuid"), in="query"),
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
     * @param $item
     * @param ItemService $itemService
     * @return JsonResponse [string] message
     */
    public function destroy(ItemDeleteRequest $request, $item, ItemService $itemService, DitasService $ditasService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        $prescription_id = $request->input('prescription_id');
        $prescription = Prescription::find($prescription_id);

//        if ($user->id != $prescription->user_id) {
//            return $this->sendError("این نسخه مربوط به شما نمی باشد", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
//        }
        if (!\App\Models\Erx\ErxItemBimeh::where('id', $item)->exists()) {
            return $this->sendError("این قلم موجود نمی باشد", ['error' => 'not found', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_NOT_FOUND);
        }
        $prescription_item = \App\Models\Prescription\Item::query()->where('prescription_id', $prescription_id)->where('erx_item_id', $item)->first();
        if (!isset($prescription_item)) {
            return $this->sendError("این قلم در این نسخه موجود نمی باشد", ['error' => 'not found', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_NOT_FOUND);
        }

        if($prescription_item->log()->exists()){
            $prescription_item->log()->delete();
        }
        $prescription_item->delete();

        $success = [];
        return $this->sendResponse($success, 'success.');
    }

}
