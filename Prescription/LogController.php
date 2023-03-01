<?php

namespace App\Http\Controllers\Prescription;

use App\Http\Requests\FilterRequest;
use App\Http\Resources\Prescription\LogResource;
use App\Models\Prescription\Log;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ThrottleFailedApi;
use App\Services\Prescription\LogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /**
 * Class Log.
 *
 * @package namespace App\Models\Log;
 * @OA\Schema ( required={"id"}, schema="Log")
 * App\Models\Log\Log
 */
class LogController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/prescription/api/log",
     *     tags={"Prescription-Log"},
     *     summary="List of Logs",
     *     @OA\Parameter(name="page", description="page of logs", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *    @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="filters", description="filters is an object include to and from ",
     *     required=false,
     *     example="{""from"":""2021-08-10"",""to"":""2021-09-10"",""specialty_id"":""7b23b7b7-a5dc-47c6-8b1d-13ffa0af4837"" ,""is_brand"":1 ,""is_active"":1, ""log_no"":333}",
     *     style="deepObject",
     *     @OA\Schema(type="object"),
     *      in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Log"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param FilterRequest $request
     * @param LogService $logService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, LogService $logService)
    {


        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service
        $limit = $request->input('limit') ?? 10;
        $logs = $logService->get($user, null, false, $request->input('filters'), $limit, $paginate = false,true);



        if ($logs === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        $logs_pg = new LogPaginateResource($logs);
        $success = [
            'logs' => $logs_pg,

        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/prescription/log/{id}",
     *     tags={"Prescription-Log"},
     *     summary="Show/Find Log by ID",
     *     description="Returns a single Log",
     *     @OA\Parameter(name="id", description="ID of Log to return", example="47e41f4b-35fc-49ac-a8aa-0001bcc0c0ec", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Log"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Log not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param LogService $logService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, LogService $logService)
    {
        $log_id = $id;

        /* @var User $user */
        $user = auth('api')->user();

        //permission in service


        $log = $logService->first($user, $log_id);
        if (!$log) {
            return $this->sendError('Log not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [
            'Log' => new LogResource($log),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/prescription/api/log",
     *     tags={"Prescription-Log"},
     *     summary="store a new log",
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
     *         @OA\JsonContent(ref="#/components/schemas/Log"),
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
     * @param LogNewRequest $request
     * @param LogService $logService
     * @return JsonResponse [string] message
     */
    public function store(LogNewRequest $request, LogService $logService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        //permi
        //todo


        $array = $request->only(array_keys($request->rules()));
        $array['secure_expire_time'] = filter_var($request->secure_expire_time ?? 24, FILTER_VALIDATE_INT);

        $log = $logService->create($user, $array, $msg);
        if ($log === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $success = [
            'log' => $logService->hideAttributes($log, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/prescription/log/{log}",
     *     tags={"Prescription-Log"},
     *     summary="update a log",
     *     @OA\Parameter(name="log", example="90b16666-2f97-4ffd-8f07-3ba8a221d798", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
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
     *         @OA\JsonContent(ref="#/components/schemas/Log"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Log not found"
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
     * @param LogUpdateRequest $request
     * @param Log $log
     * @param LogService $logService
     * @return JsonResponse [string] message
     */
    public function update(LogUpdateRequest $request, Log $log, LogService $logService)
    {
        //Done
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        // "ReadOnly: domain cannot be changed" ?

        //permission
        if (!$user->is_admin && $user->id != $log->domain->owner_id) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }
        $array = $request->only(array_keys($request->rules()));
        $array['secure_expire_time'] = filter_var($request->secure_expire_time ?? 24, FILTER_VALIDATE_INT);

        $logNULL = $logService->update($log, $array, $msg);
        if ($logNULL === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [
            'log' => $logService->hideAttributes($logNULL, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/Prescription/{log}",
     *     tags={"Prescription-Log"},
     *     summary="delete a log",
     *     @OA\Parameter(name="log", example="90b16666-2f97-4ffd-8f07-3ba8a221d798", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Log"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Log not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Log $log
     * @param LogService $logService
     * @return JsonResponse [string] message
     */
    public function destroy(Log $log, LogService $logService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        //permi
        if (!$user->is_admin && $user->id != $log->owner_id) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $response = $logService->delete($user, $log, $msg);
        if (!$response) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [];
        return $this->sendResponse($success, 'success.');
    }

}
