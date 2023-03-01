<?php

namespace App\Http\Controllers\Pattern;

use App\Http\Requests\FilterRequest;
use App\Http\Resources\Pattern\ItemPaginateResource;
use App\Http\Resources\Pattern\ItemResource;
use App\Models\Identity\User;
use App\Models\Pattern\PatternItem;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ThrottleFailedApi;
use App\Services\Pattern\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /**
 * Class Item.
 *
 * @package namespace App\Models\Pattern;
 * @OA\Schema ( required={"id"}, schema="Item")
 * App\Models\Pattern\Item
 */
class ItemController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/pattern/item",
     *     tags={"Pattern-Item"},
     *     summary="List of items",
     *     @OA\Parameter(name="page", description="page of Items", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *    @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="filters", description="filters is an object include to and from ",
     *     required=false,
     *     example="{""from"":""2021-08-10"",""to"":""2021-09-10"",""patterns_header_id"":""34e9451c-d71f-4cac-9749-a2fa92941deb"" ,""standard_code"":""1036""}",
     *     style="deepObject",
     *     @OA\Schema(type="object"),
     *      in="query"),
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
    public function index(FilterRequest $request, ItemService $itemService)
    {


        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service
        $items = $itemService->get($user, null, $request->input('filters'), 10, $paginate = false);



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
     *     path="/api/pattern/item/{id}",
     *     tags={"Pattern-Item"},
     *     summary="Show/Find item by ID",
     *     description="Returns a single Item",
     *     @OA\Parameter(name="id", description="ID of Item to return", example="95e8c4d1-a9ec-4e23-ad95-04d11ab2d294", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
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


}
