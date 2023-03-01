<?php

namespace App\Http\Controllers\Erx;

use App\Http\Resources\Erx\ItemBrandResource;
use App\Http\Controllers\Controller;
use App\Models\Erx\ErxItem;
use App\Services\Erx\ItemService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ItemController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/erx/getbrands/item/{item}",
     *     tags={"erx"},
     *     summary="Show/Find all items in this brand",
     *     description="Returns brands items",
     *     @OA\Parameter(name="item", description="ID of item to return" , example="3fdbaa71-28d6-441d-8698-00006e1c3f68", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Parameter(name="type", description="T , I", example="T", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Item"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalcode ID"
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
     * @param $item
     * @param ItemService $itemService
     * @return JsonResponse [string] message
     */
    public function getBrands(Request $request, ErxItem $item, ItemService $itemService): JsonResponse
    {
        //todo get item type
        $type = $request->input('type');
        $validator = Validator::make(['type'=>$type], ['type' => ['bail', 'sometimes', 'nullable', 'string']]);
        if ($validator->fails()) {
            return $this->sendError('مقدار دریافتی معتبر نیست', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }

        $brands = $itemService->getBrands($item->id, $type);
        if (!$brands) {
            return $this->sendError('Item not found.', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }

        $success = [
            'brands' => ItemBrandResource::collection($brands),
        ];

        return $this->sendResponse($success, 'success.');
    }
}
