<?php

namespace App\Http\Controllers;

use App\Models\Identity\User;
use App\Services\ReportService;
use App\Models\Identity\Employee;
use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Http\Middleware\ThrottleFailedApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\Report\ReportPaginateResource;
use App\Http\Resources\Pattern\PatternPaginateResource;
use App\Http\Resources\Pattern\ProtocolPaginateResource;

class ReportController extends Controller
{
    public function __construct()
    {
//        $this->middleware('IsAdmin');
    }

    /**
     * Display a number  of the all prescription from pattern .
     *
     * @OA\Get(
     *     path="/api/report/total/protocols",
     *     tags={"Report"},
     *     summary="The total number of protocols includes all and with name  and without name  and created doc-N-010.pdf",
     *     @OA\Parameter(name="filters", description="filters is an object include to  and from date in Miladi ",
     *     required=false,
     *     example="{""specialty_id"":""7B23B7B7-A5DC-47C6-8B1D-13FFA0AF4837"", ""from"":""2022-03-26"",""to"":""2022-03-26""}",
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
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param  Request  $request
     * @param  ReportService  $repService
     *
     * @return JsonResponse [string] message
     */
    public function totalPrescriptions(Request $request, ReportService $repService)
    {


        /* @var User $user */
        $user = auth('api')->user();

        //permission

        $total = $repService->total($user, $request->input('filters'));
//        $reqsNULL = $reqService->get($user, null, false, $request->input('filters'));

        if ($total === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $success = [
            'total' => $total,
        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display a number  of the all prescription from pattern .
     *
     * @OA\Get(
     *     path="/api/report/total/named/protocols",
     *     tags={"Report"},
     *     summary="The total number of protocols with name groupBy each specialty_id  doc-N-010.pdf",
     *     @OA\Parameter(name="page", description="page of results", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="filters", description="filters is an object include to  and from date in Miladi ",
     *     required=false,
     *     example="{""specialty_id"":""7B23B7B7-A5DC-47C6-8B1D-13FFA0AF4837"", ""from"":""2022-03-26"",""to"":""2022-03-26""}",
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
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param  Request  $request
     * @param  ReportService  $repService
     *
     * @return JsonResponse [string] message
     */
    public function totalNamed(Request $request, ReportService $repService)
    {


        /* @var User $user */
        $user = auth('api')->user();

        //permission
        $limit = $request->input('limit') ?? 100;
        $total = $repService->totalNamed($user, $request->input('filters'),$limit);
//        $reqsNULL = $reqService->get($user, null, false, $request->input('filters'));

        if ($total === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $success = [
            'results' => new ReportPaginateResource($total),
        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display a number  of the all prescription from pattern .
     *
     * @OA\Get(
     *     path="/api/report/total/created/protocols",
     *     tags={"Report"},
     *     summary="The total number of protocols created groupBy each specialty_id  doc-N-010.pdf",
     *     @OA\Parameter(name="page", description="page of results", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="filters", description="filters is an object include to  and from date in Miladi ",
     *     required=false,
     *     example="{""specialty_id"":""7B23B7B7-A5DC-47C6-8B1D-13FFA0AF4837"", ""from"":""2022-03-26"",""to"":""2022-03-26""}",
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
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param  Request  $request
     * @param  ReportService  $repService
     *
     * @return JsonResponse [string] message
     */
    public function totalCreated(Request $request, ReportService $repService)
    {


        /* @var User $user */
        $user = auth('api')->user();

        //permission
        $limit = $request->input('limit') ?? 100;
        $total = $repService->totalCreated($user, $request->input('filters'),$limit);
//        $reqsNULL = $reqService->get($user, null, false, $request->input('filters'));

        if ($total === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $success = [
            'results' => new ReportPaginateResource($total),
        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display a number  of the all prescription from pattern .
     *
     * @OA\Get(
     *     path="/api/report/total/not/named/protocols",
     *     tags={"Report"},
     *     summary="The total number of protocols without named  groupBy each specialty_id  doc-N-010.pdf",
     *     @OA\Parameter(name="page", description="page of results", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="filters", description="filters is an object include to  and from date in Miladi ",
     *     required=false,
     *     example="{""specialty_id"":""7B23B7B7-A5DC-47C6-8B1D-13FFA0AF4837"", ""from"":""2022-03-26"",""to"":""2022-03-26""}",
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
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param  Request  $request
     * @param  ReportService  $repService
     *
     * @return JsonResponse [string] message
     */
    public function totalWithOutName(Request $request, ReportService $repService)
    {


        /* @var User $user */
        $user = auth('api')->user();

        //permission
        $limit = $request->input('limit') ?? 100;
        $total = $repService->totalWithOutName($user, $request->input('filters'),$limit);
//        $reqsNULL = $reqService->get($user, null, false, $request->input('filters'));

        if ($total === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $success = [
            'results' => new ReportPaginateResource($total),
        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display a number  of the all prescription from pattern .
     *
     * @OA\Get(
     *     path="/api/report/not/named/protocols",
     *     tags={"Report"},
     *     summary="The get protocols without named  groupBy each specialty_id  doc-N-010.pdf",
     *     @OA\Parameter(name="page", description="page of results", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="filters", description="filters is an object include to  and from date in Miladi ",
     *     required=false,
     *     example="{""specialty_id"":""7B23B7B7-A5DC-47C6-8B1D-13FFA0AF4837"", ""from"":""2022-03-26"",""to"":""2022-03-26""}",
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
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param  Request  $request
     * @param  ReportService  $repService
     *
     * @return JsonResponse [string] message
     */
    public function getWithOutName(Request $request, ReportService $repService)
    {


        /* @var User $user */
        $user = auth('api')->user();

        //permission
        $limit = $request->input('limit') ?? 100;
        $patterns = $repService->getWithOutName($user, $request->input('filters'),$limit);

        if ($patterns === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $success = [
            'patterns' => new PatternPaginateResource($patterns),

        ];

        return $this->sendResponse($success, 'success.');
    }
}
