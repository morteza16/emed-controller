<?php

namespace App\Http\Controllers\Pattern;

use App\Http\Requests\FilterRequest;
use App\Http\Requests\Pattern\FilterPatternRequest;
use App\Http\Resources\Pattern\PatternPaginateResource;
use App\Http\Resources\Pattern\PatternResource;
use App\Models\Identity\User;
use App\Models\Pattern\Pattern;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ThrottleFailedApi;
use App\Services\Pattern\PatternService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /**
 * Class Pattern.
 *
 * @package namespace App\Models\Pattern;
 * @OA\Schema ( required={"id"}, schema="Pattern")
 * App\Models\Pattern\Pattern
 */
class PatternController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/pattern",
     *     tags={"Pattern"},
     *     summary="List of Patterns",
     *     @OA\Parameter(name="page", description="page of patterns", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *    @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="filters", description="filters is an object include to and from ",
     *     required=false,
     *     example="{""from"":""2021-08-10"",""to"":""2021-09-10"",""specialty_id"":""7b23b7b7-a5dc-47c6-8b1d-13ffa0af4837"" ,""is_brand"":1 ,""is_active"":1, ""prescription_no"":333}",
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
     * @param PatternService $patternService
     * @return JsonResponse [string] message
     */
    public function index(FilterPatternRequest $request, PatternService $patternService)
    {


        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service
        $limit = $request->input('limit') ?? 10;
        $patterns = $patternService->get($user, null, false, $request->input('filters'), $limit, $paginate = false,true);



        if ($patterns === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        $patterns_pg = new PatternPaginateResource($patterns);
        $success = [
            'patterns' => $patterns_pg,

        ];

        return $this->sendResponse($success, 'success.');
    }


    /**
     * Display a listing of the deleted resources.
     *
     * @OA\Get(
     *     path="/api/pattern/deleted",
     *     tags={"Pattern-d"},
     *     summary="List of deleted Patterns",
     *     @OA\Parameter(name="page", description="page of patterns", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="filters", description="filters is an object include to and from ",
     *     required=false,
     *     example="{""from"":""2021-08-10"",""to"":""2021-09-10"",""specialty_id"":""7b23b7b7-a5dc-47c6-8b1d-13ffa0af4837"" ,""is_brand"":1 ,""is_active"":1, ""prescription_no"":333}",
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
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param PatternService $patternService
     * @return JsonResponse [string] message
     */
    public function deleted(FilterPatternRequest $request, PatternService $patternService)
    {
        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service
        $patterns = $patternService->get($user, null, true, $request->input('filters'), 10, $paginate = false);



        if ($patterns === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        $patterns_pg = new PatternPaginateResource($patterns);
        $success = [
            'patterns' => $patterns_pg,

        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/pattern/{id}",
     *     tags={"Pattern"},
     *     summary="Show/Find Pattern by ID",
     *     description="Returns a single Pattern",
     *     @OA\Parameter(name="id", description="ID of Pattern to return", example="47e41f4b-35fc-49ac-a8aa-0001bcc0c0ec", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Pattern"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Pattern not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param PatternService $patternService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, PatternService $patternService)
    {
        $pattern_id = $id;

        /* @var User $user */
        $user = auth('api')->user();

        //permission in service


        $pattern = $patternService->first($user, $pattern_id);
        if (!$pattern) {
            return $this->sendError('Pattern not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [
            'Pattern' => new PatternResource($pattern),
        ];
        return $this->sendResponse($success, 'success.');
    }

}
