<?php

namespace App\Http\Controllers;

use App\Http\Resources\Erx\ItemBrandResource;
use App\Http\Resources\HistoryResource;
use App\Rules\UUID;
use App\Models\Erx\ErxItemBimeh;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Middleware\ThrottleFailedApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HistoryController extends Controller
{
    /**
     * get patient history
     *
     * @OA\Get(
     *     path="/api/patient/history",
     *     tags={"patient-history"},
     *     summary="",
     *     @OA\Parameter(name="code", description="patient code", example="2670132952", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Prescription"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param Request $request
     * @return JsonResponse [string] message
     */

    public function getHistory(Request $request)
    {
//        $path = storage_path("history/1.json") ;
//        $json = file_get_contents($path);
//        $array = json_decode($json);


        /* @var User $user */
//        $user = auth('api')->user();


        $history = DB::table('casehistory')
            ->join('erx_items', 'casehistory.erx_item_id', '=', 'erx_items.id')
            ->join('erx_types', 'erx_items.erx_type_id', '=', 'erx_types.id')
            ->selectRaw('casehistory.count, casehistory.date, erx_items.*, erx_types.*')->get();

        $success = [
            'history' => HistoryResource::collection($history),
        ];

        return $this->sendResponse($success, 'success.');
    }


}
