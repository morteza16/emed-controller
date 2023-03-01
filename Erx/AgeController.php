<?php

namespace App\Http\Controllers\Erx;

use App\Rules\UUID;
use App\Http\Controllers\Controller;
use App\Http\Resources\Erx\AgeResource;
use App\Services\Erx\AgeService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgeController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/erx/age",
     *     tags={"erx"},
     *     summary="List of ages",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *       @OA\Items(ref="#/components/schemas/AgeGroup"),
     *
     *         ),
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param AgeService $ageService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, AgeService $ageService): JsonResponse
    {
        $ages = $ageService->get();

        if ($ages === null) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $success = [
            'ages' => AgeResource::collection($ages),
        ];

        return $this->sendResponse($success, 'success.');
    }


    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/erx/age/{id}",
     *     tags={"erx"},
     *     summary="Show/Find age by uuID",
     *     description="Returns a single age",
     *     @OA\Parameter(name="id", description="ID of age to return" , example="5131997F-54C1-4D6B-BB3E-0BB6EBE65DEF", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/AgeGroup"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="AgeGroup not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param AgeService $ageService
     * @return JsonResponse
     */
    public function show(Request $request, $id, AgeService $ageService): JsonResponse
    {
        $validator = Validator::make(['id'=>$id], ['id' => new UUID()]);

        if ($validator->fails()) {
            return $this->sendError('مقدار دریافتی معتبر نیست', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }

        $age = $ageService->first($id);
        if (!$age) {
            return $this->sendError('گروه سنی مورد نظر پیدا نشد!', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }

        $success = [
            'age' => new AgeResource($age),
        ];

        return $this->sendResponse($success, 'success.');
    }
}
