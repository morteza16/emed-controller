<?php

namespace App\Http\Controllers\Erx;

use App\Http\Resources\Erx\TherapeuticGroupResource;
use App\Http\Resources\Erx\TherapeuticSummeryResource;
use App\Rules\UUID;
use App\Http\Controllers\Controller;
use App\Http\Resources\Erx\TherapeuticResource;
use App\Services\Erx\TherapeuticService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TherapeuticController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/erx/therapeutic",
     *     tags={"erx"},
     *     summary="List of therapeutics",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *       @OA\Items(ref="#/components/schemas/Therapeutic"),
     *
     *         ),
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param TherapeuticService $therapeuticService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, TherapeuticService $therapeuticService): JsonResponse
    {
        $therapeutics = $therapeuticService->get();

        if ($therapeutics === null) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $success = [
            'therapeutics' => TherapeuticSummeryResource::collection($therapeutics),
        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/erx/grouping/therapeutic",
     *     tags={"erx"},
     *     summary="List of therapeutics",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *       @OA\Items(ref="#/components/schemas/Therapeutic"),
     *
     *         ),
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param TherapeuticService $therapeuticService
     * @return JsonResponse [string] message
     */
    public function indexWithGrouping(Request $request, TherapeuticService $therapeuticService): JsonResponse
    {
        $therapeutics = $therapeuticService->getByGrouping();

        if ($therapeutics === null) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $success = [

            'therapeutics' => TherapeuticGroupResource::collection($therapeutics),
        ];

        return $this->sendResponse($success, 'success.');
    }


    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/erx/therapeutic/{id}",
     *     tags={"erx"},
     *     summary="Show/Find therapeutic by uuID",
     *     description="Returns a single therapeutic",
     *     @OA\Parameter(name="id", description="ID of therapeutic to return" , example="54b9eaa5-d436-4755-8211-405727773fa6", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Therapeutic"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Therapeutic not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param TherapeuticService $therapeuticService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, TherapeuticService $therapeuticService): JsonResponse
    {
        $validator = Validator::make(['id'=>$id], ['id' => new UUID()]);

        if ($validator->fails()) {
            return $this->sendError('مقدار دریافتی معتبر نیست', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }
        $therapeutic = $therapeuticService->first($id);
        if (!$therapeutic) {
            return $this->sendError('Therapeutic not found.', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }

        $success = [
            'therapeutic' => new TherapeuticResource($therapeutic),
        ];

        return $this->sendResponse($success, 'success.');
    }


    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/erx/sub/therapeutic/{id}",
     *     tags={"erx"},
     *     summary="List sub therapeutic by uuID",
     *     description="Returns therapeutics",
     *     @OA\Parameter(name="id", description="ID of sub therapeutic to return" , example="A9A3050C-8AFF-4C35-827F-01A62CDBC109", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Therapeutic"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Therapeutic not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param TherapeuticService $therapeuticService
     * @return JsonResponse [string] message
     */
    public function children(Request $request, $id, TherapeuticService $therapeuticService): JsonResponse
    {
        $validator = Validator::make(['id'=>$id], ['id' => new UUID()]);

        if ($validator->fails()) {
            return $this->sendError('مقدار دریافتی معتبر نیست', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }
        $therapeutics = $therapeuticService->getChildren($id);
        if (!$therapeutics) {
            return $this->sendError('Therapeutic not found.', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }

        $success = [
            'therapeutics' => TherapeuticResource::collection($therapeutics),
        ];

        return $this->sendResponse($success, 'success.');
    }
}
