<?php

namespace App\Http\Controllers\Prescription;

use App\Http\Requests\FilterRequest;
use App\Http\Resources\Prescription\ItemLogResource;
use App\Models\Prescription\ItemLog;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ThrottleFailedApi;
use App\Services\Prescription\ItemLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /**
 * Class ItemLog.
 *
 * @package namespace App\Models\ItemLog;
 * @OA\Schema ( required={"id"}, schema="ItemLog")
 * App\Models\ItemLog\ItemLog
 */
class ItemLogController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/prescription/api/itemlog",
     *     tags={"Prescription-ItemLog"},
     *     summary="List of ItemLogs",
     *     @OA\Parameter(name="page", description="page of itemLogs", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *    @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="filters", description="filters is an object include to and from ",
     *     required=false,
     *     example="{""from"":""2021-08-10"",""to"":""2021-09-10"",""specialty_id"":""7b23b7b7-a5dc-47c6-8b1d-13ffa0af4837"" ,""is_brand"":1 ,""is_active"":1, ""itemLog_no"":333}",
     *     style="deepObject",
     *     @OA\Schema(type="object"),
     *      in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/ItemLog"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param FilterRequest $request
     * @param ItemLogService $itemLogService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, ItemLogService $itemLogService)
    {


        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service
        $limit = $request->input('limit') ?? 10;
        $itemLogs = $itemLogService->get($user, null, false, $request->input('filters'), $limit, $paginate = false,true);



        if ($itemLogs === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        $itemLogs_pg = new ItemLogPaginateResource($itemLogs);
        $success = [
            'itemLogs' => $itemLogs_pg,

        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/prescription/itemlog/{id}",
     *     tags={"Prescription-ItemLog"},
     *     summary="Show/Find ItemLog by ID",
     *     description="Returns a single ItemLog",
     *     @OA\Parameter(name="id", description="ID of ItemLog to return", example="47e41f4b-35fc-49ac-a8aa-0001bcc0c0ec", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/ItemLog"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="ItemLog not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param ItemLogService $itemLogService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, ItemLogService $itemLogService)
    {
        $itemLog_id = $id;

        /* @var User $user */
        $user = auth('api')->user();

        //permission in service


        $itemLog = $itemLogService->first($user, $itemLog_id);
        if (!$itemLog) {
            return $this->sendError('ItemLog not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [
            'ItemLog' => new ItemLogResource($itemLog),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/prescription/api/itemlog",
     *     tags={"Prescription-ItemLog"},
     *     summary="store a new itemLog",
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
     *         @OA\JsonContent(ref="#/components/schemas/ItemLog"),
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
     * @param ItemLogNewRequest $request
     * @param ItemLogService $itemLogService
     * @return JsonResponse [string] message
     */
    public function store(ItemLogNewRequest $request, ItemLogService $itemLogService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        //permi
        //todo


        $array = $request->only(array_keys($request->rules()));
        $array['secure_expire_time'] = filter_var($request->secure_expire_time ?? 24, FILTER_VALIDATE_INT);

        $itemLog = $itemLogService->create($user, $array, $msg);
        if ($itemLog === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $success = [
            'itemLog' => $itemLogService->hideAttributes($itemLog, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/prescription/itemlog/{itemLog}",
     *     tags={"Prescription-ItemLog"},
     *     summary="update a itemLog",
     *     @OA\Parameter(name="itemLog", example="90b16666-2f97-4ffd-8f07-3ba8a221d798", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
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
     *         @OA\JsonContent(ref="#/components/schemas/ItemLog"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="ItemLog not found"
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
     * @param ItemLogUpdateRequest $request
     * @param ItemLog $itemLog
     * @param ItemLogService $itemLogService
     * @return JsonResponse [string] message
     */
    public function update(ItemLogUpdateRequest $request, ItemLog $itemLog, ItemLogService $itemLogService)
    {
        //Done
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        // "ReadOnly: domain cannot be changed" ?

        //permission
        if (!$user->is_admin && $user->id != $itemLog->domain->owner_id) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }
        $array = $request->only(array_keys($request->rules()));
        $array['secure_expire_time'] = filter_var($request->secure_expire_time ?? 24, FILTER_VALIDATE_INT);

        $itemLogNULL = $itemLogService->update($itemLog, $array, $msg);
        if ($itemLogNULL === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [
            'itemLog' => $itemLogService->hideAttributes($itemLogNULL, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/Prescription/{itemlog}",
     *     tags={"Prescription-ItemLog"},
     *     summary="delete a itemLog",
     *     @OA\Parameter(name="itemLog", example="90b16666-2f97-4ffd-8f07-3ba8a221d798", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/ItemLog"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="ItemLog not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param ItemLog $itemLog
     * @param ItemLogService $itemLogService
     * @return JsonResponse [string] message
     */
    public function destroy(ItemLog $itemLog, ItemLogService $itemLogService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        //permi
        if (!$user->is_admin && $user->id != $itemLog->owner_id) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $response = $itemLogService->delete($user, $itemLog, $msg);
        if (!$response) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [];
        return $this->sendResponse($success, 'success.');
    }

}
