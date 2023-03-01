<?php

namespace App\Http\Controllers\Erx;

use App\Rules\UUID;
use App\Http\Controllers\Controller;
use App\Http\Resources\Erx\TypeResource;
use App\Services\Erx\TypeService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TypeController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/erx/type",
     *     tags={"erx"},
     *     summary="List of types",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Type"),
     *         ),
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param TypeService $typeService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, TypeService $typeService): JsonResponse
    {
        $types = $typeService->get();

        if ($types === null) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $success = [
            'types' => TypeResource::collection($types),
        ];

        return $this->sendResponse($success, 'success.');
    }


    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/erx/type/{id}",
     *     tags={"erx"},
     *     summary="Show/Find type by uuID",
     *     description="Returns a single type",
     *     @OA\Parameter(name="id", description="ID of type to return" , example="eba793ce-2f40-4fd4-944c-1a2cd17d0103", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Type"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Type not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param TypeService $typeService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, TypeService $typeService): JsonResponse
    {
        $validator = Validator::make(['id'=>$id], ['id' => new UUID()]);

        if ($validator->fails()) {
            return $this->sendError('مقدار دریافتی معتبر نیست', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }

        $type = $typeService->first($id);
        if (!$type) {
            return $this->sendError('Type not found.', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }

        $success = [
            'type' => new TypeResource($type),
        ];

        return $this->sendResponse($success, 'success.');
    }
}
