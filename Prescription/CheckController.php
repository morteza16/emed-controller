<?php

namespace App\Http\Controllers\Prescription;

use App\Http\Requests\FilterRequest;
use App\Http\Resources\Prescription\CheckResource;
use App\Models\Prescription\Check;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ThrottleFailedApi;
use App\Services\Prescription\CheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /**
 * Class Check.
 *
 * @package namespace App\Models\Check;
 * @OA\Schema ( required={"id"}, schema="Check")
 * App\Models\Check\Check
 */
class CheckController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/prescription/api/check",
     *     tags={"Prescription-Check"},
     *     summary="List of Checks",
     *     @OA\Parameter(name="page", description="page of checks", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *    @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="filters", description="filters is an object include to and from ",
     *     required=false,
     *     example="{""from"":""2021-08-10"",""to"":""2021-09-10"",""specialty_id"":""7b23b7b7-a5dc-47c6-8b1d-13ffa0af4837"" ,""is_brand"":1 ,""is_active"":1, ""check_no"":333}",
     *     style="deepObject",
     *     @OA\Schema(type="object"),
     *      in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Check"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param FilterRequest $request
     * @param CheckService $checkService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, CheckService $checkService)
    {


        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service
        $limit = $request->input('limit') ?? 10;
        $checks = $checkService->get($user, null, false, $request->input('filters'), $limit, $paginate = false,true);



        if ($checks === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        $checks_pg = new CheckPaginateResource($checks);
        $success = [
            'checks' => $checks_pg,

        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/prescription/check/{id}",
     *     tags={"Prescription-Check"},
     *     summary="Show/Find Check by ID",
     *     description="Returns a single Check",
     *     @OA\Parameter(name="id", description="ID of Check to return", example="47e41f4b-35fc-49ac-a8aa-0001bcc0c0ec", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Check"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Check not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param CheckService $checkService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, CheckService $checkService)
    {
        $check_id = $id;

        /* @var User $user */
        $user = auth('api')->user();

        //permission in service


        $check = $checkService->first($user, $check_id);
        if (!$check) {
            return $this->sendError('Check not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [
            'Check' => new CheckResource($check),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/prescription/api/check",
     *     tags={"Prescription-Check"},
     *     summary="store a new check",
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
     *         @OA\JsonContent(ref="#/components/schemas/Check"),
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
     * @param CheckNewRequest $request
     * @param CheckService $checkService
     * @return JsonResponse [string] message
     */
    public function store(CheckNewRequest $request, CheckService $checkService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        //permi
        //todo


        $array = $request->only(array_keys($request->rules()));
        $array['secure_expire_time'] = filter_var($request->secure_expire_time ?? 24, FILTER_VALIDATE_INT);

        $check = $checkService->create($user, $array, $msg);
        if ($check === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $success = [
            'check' => $checkService->hideAttributes($check, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/prescription/check/{check}",
     *     tags={"Prescription-Check"},
     *     summary="update a check",
     *     @OA\Parameter(name="check", example="90b16666-2f97-4ffd-8f07-3ba8a221d798", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
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
     *         @OA\JsonContent(ref="#/components/schemas/Check"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Check not found"
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
     * @param CheckUpdateRequest $request
     * @param Check $check
     * @param CheckService $checkService
     * @return JsonResponse [string] message
     */
    public function update(CheckUpdateRequest $request, Check $check, CheckService $checkService)
    {
        //Done
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        // "ReadOnly: domain cannot be changed" ?

        //permission
        if (!$user->is_admin && $user->id != $check->domain->owner_id) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }
        $array = $request->only(array_keys($request->rules()));
        $array['secure_expire_time'] = filter_var($request->secure_expire_time ?? 24, FILTER_VALIDATE_INT);

        $checkNULL = $checkService->update($check, $array, $msg);
        if ($checkNULL === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [
            'check' => $checkService->hideAttributes($checkNULL, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/Prescription/{check}",
     *     tags={"Prescription-Check"},
     *     summary="delete a check",
     *     @OA\Parameter(name="check", example="90b16666-2f97-4ffd-8f07-3ba8a221d798", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Check"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Check not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Check $check
     * @param CheckService $checkService
     * @return JsonResponse [string] message
     */
    public function destroy(Check $check, CheckService $checkService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        //permi
        if (!$user->is_admin && $user->id != $check->owner_id) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $response = $checkService->delete($user, $check, $msg);
        if (!$response) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [];
        return $this->sendResponse($success, 'success.');
    }

}
