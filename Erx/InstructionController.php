<?php

namespace App\Http\Controllers\Erx;

use App\Rules\UUID;
use App\Http\Controllers\Controller;
use App\Http\Resources\Erx\InstructionResource;
use App\Services\Erx\InstructionService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstructionController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/erx/instruction",
     *     tags={"erx"},
     *     summary="List of instructions",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *       @OA\Items(ref="#/components/schemas/Instruction"),
     *
     *         ),
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param InstructionService $instructionService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, InstructionService $instructionService): JsonResponse
    {
        $instructions = $instructionService->get();

        if ($instructions === null) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $success = [
            'instructions' => InstructionResource::collection($instructions),
        ];

        return $this->sendResponse($success, 'success.');
    }


    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/erx/instruction/{id}",
     *     tags={"erx"},
     *     summary="Show/Find instruction by uuID",
     *     description="Returns a single instruction",
     *     @OA\Parameter(name="id", description="ID of instruction to return" , example="2874AB60-E7CF-4E8F-9917-3F9425AFED83", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Instruction"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Instruction not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param InstructionService $instructionService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, InstructionService $instructionService): JsonResponse
    {
        $validator = Validator::make(['id'=>$id], ['id' => new UUID()]);

        if ($validator->fails()) {
            return $this->sendError('مقدار دریافتی معتبر نیست', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }
        $instruction = $instructionService->first($id);
        if (!$instruction) {
            return $this->sendError('Instruction not found.', [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }

        $success = [
            'instruction' => new InstructionResource($instruction),
        ];

        return $this->sendResponse($success, 'success.');
    }
}
