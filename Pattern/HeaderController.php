<?php

namespace App\Http\Controllers\Pattern;

use App\Http\Requests\FilterRequest;
use App\Http\Resources\Pattern\HeaderPaginateResource;
use App\Http\Resources\Pattern\HeaderResource;
use App\Models\Identity\User;
use App\Models\Pattern\Header;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ThrottleFailedApi;
use App\Services\Pattern\HeaderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /**
 * Class Header.
 *
 * @package namespace App\Models\Pattern;
 * @OA\Schema ( required={"id"}, schema="Header")
 * App\Models\Pattern\Header
 */
class HeaderController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/pattern/header",
     *     tags={"Pattern-Header"},
     *     summary="List of headers",
     *     @OA\Parameter(name="page", description="page of Headers", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *    @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="filters", description="filters is an object include to and from ",
     *     required=false,
     *     example="{""from"":""2021-08-10"",""to"":""2021-09-10"",""specialty_id"":5 ,""pattern_id"":""47e41f4b-35fc-49ac-a8aa-0001bcc0c0ec""}",
     *     style="deepObject",
     *     @OA\Schema(type="object"),
     *      in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Header"),
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
     * @return JsonResponse [string] message
     */
    public function index(FilterRequest $request, HeaderService $headerService)
    {


        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service
        $headers = $headerService->get($user, null, $request->input('filters'), 10, $paginate = false);



        if ($headers === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        $headers_pg = new HeaderPaginateResource($headers);
        $success = [
            'Headers' => $headers_pg,

        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/pattern/header/{id}",
     *     tags={"Pattern-Header"},
     *     summary="Show/Find header by ID",
     *     description="Returns a single Header",
     *     @OA\Parameter(name="id", description="ID of Header to return", example="95e8bd73-2f6e-49f9-93d4-0c1157451b85", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Header"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Header not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param HeaderService $headerService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, HeaderService $headerService)
    {
        $header_id = $id;

        /* @var User $user */
        $user = auth('api')->user();

        //permission in service


        $header = $headerService->first($user, $header_id);
        if (!$header) {
            return $this->sendError('Header not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [
            'Header' => new HeaderResource($header),
        ];
        return $this->sendResponse($success, 'success.');
    }

}
