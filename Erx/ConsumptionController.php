<?php

namespace App\Http\Controllers\Erx;

use App\Rules\UUID;
use App\Http\Controllers\Controller;
use App\Http\Resources\Erx\ConsumptionResource;
use App\Services\Erx\ConsumptionService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConsumptionController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/erx/consumption",
     *     tags={"erx"},
     *     summary="List of consumptions",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *       @OA\Items(ref="#/components/schemas/Consumption"),
     *
     *         ),
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param ConsumptionService $consumptionService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, ConsumptionService $consumptionService): JsonResponse
    {
        $consumptions = $consumptionService->get();

        if ($consumptions === null) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $success = [
            'consumptions' => ConsumptionResource::collection($consumptions),
        ];

        return $this->sendResponse($success, 'success.');
    }


    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/erx/consumption/{id}",
     *     tags={"erx"},
     *     summary="Show/Find consumption by uuID",
     *     description="Returns a single consumption",
     *     @OA\Parameter(name="id", description="ID of consumption to return" , example="D32837CF-8B99-4C53-A6C5-1E7578ABA29B", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Consumption"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Consumption not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param ConsumptionService $consumptionService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, ConsumptionService $consumptionService): JsonResponse
    {
        $validator = Validator::make(['id'=>$id], ['id' => new UUID()]);

        if ($validator->fails()) {
            return $this->sendError('مقدار دریافتی معتبر نیست', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }

        $consumption = $consumptionService->first($id);
        if (! $consumption) {
            return $this->sendError('Consumption not found.', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }

        $success = [
            'consumption' => new ConsumptionResource($consumption),
        ];

        return $this->sendResponse($success, 'success.');
    }
}
