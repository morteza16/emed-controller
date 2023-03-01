<?php

namespace App\Http\Controllers\Stimular;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\NewCommitteeDrugRequest;
use App\Http\Resources\Report\CommitteeDrugPaginateResource;
use App\Http\Resources\Report\CommitteeDrugResource;
use App\Models\Identity\User;
use App\Models\Report\CommitteeDrug;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

class ReportsController extends Controller
{
    /**
     * Display the list of Reports.
     *
     * @OA\Get(
     *     path="/api/rpt",
     *     tags={"Reports"},
     *     summary="Show all Reports",
     *     description="Returns a list of Employee Reports",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/CommitteeDrug"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid Page Number"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $committees = CommitteeDrug::query()
                ->where('user_id', auth('api')->id())
                ->orderBy('id', 'desc')
                ->get();

            $success = [
                'result' => CommitteeDrugResource::collection($committees),
            ];

            return $this->sendResponse($success, 'success.');
        } catch (\Throwable $e) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
    }

    /**
     * Create new Report
     *
     * @OA\Post(
     *     path="/api/rpt",
     *     tags={"Reports"},
     *     summary="Create Report",
     *     @OA\RequestBody(
     *     required=true,
     *      @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *               @OA\Property(
     *                    property="from",
     *                    type="string",
     *                    description="From Date"
     *                 ),
     *               @OA\Property(
     *                    property="to",
     *                    type="string",
     *                    description="To Date"
     *                 ),
     *              @OA\Property(
     *                    property="type",
     *                    type="string",
     *                    description="prescription or prescription-details or committee"
     *                 ),
     *                 example={"from":"1401/03/01", "to":"1401/04/31", "type":"committee"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid IP"
     *     ),
     *      @OA\Response(
     *         response=405,
     *         description="Validation exception"
     *     ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param NewCommitteeDrugRequest $request
     * @return JsonResponse [string] message
     */
    public function store(NewCommitteeDrugRequest $request)
    {
        try {
            /* @var User $user */
            $user = auth('api')->user();
            $from = $request->input('from');
            $to = $request->input('to');
            $type = $request->input('type');

            if (Verta::parse($to)->diffDays() < 5) {
                return $this->sendError("کاربر گرامی زمان ایجاد گزارش باید 5 روز از ماه عملکرد درخواسته بگذرد.", [
                    'error' => 'Validation Error',
                ], 400);
            }

            $exists = CommitteeDrug::query()->where([
                ['user_id', '=', $user->id],
                ['fromdate', '=', $from],
                ['todate', '=', $to],
                ['type', '=', $type]
            ])->first();

            if ($exists) {
                $exists->created_at = Carbon::now();
                $exists->save();
                $cd = $exists;
            } else {
                $cd = new CommitteeDrug;
                $cd->user_id = $user->id;
                $cd->fromdate = $from;
                $cd->todate = $to;
                $cd->employee_fname = $user->employee->fname;
                $cd->employee_lname = $user->employee->lname;
                $cd->type = $type;
                $cd->status = 'ایجاد شده';
                $cd->status_id = 1;
                $cd->is_downloaded = false;
                $cd->is_deleted = false;
                $cd->created_at = Carbon::now();
                $cd->save();
            }

            $success = [
                'item' => CommitteeDrugResource::make($cd),
            ];

            return $this->sendResponse($success, 'success.');
        } catch (\Throwable $e) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
    }

    /**
     * Delete Report.
     *
     * @OA\Delete(
     *     path="/api/rpt/{id}",
     *     tags={"Reports"},
     *     summary="Delete Report",
     *     @OA\Parameter(name="id", example="3", @OA\Schema(type="string"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blacklisted IP not found"
     *     ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param CommitteeDrug $committeeDrug
     * @param Request $request
     * @return JsonResponse [string] message
     */
    public function destroy(CommitteeDrug $committeeDrug, Request $request)
    {
        try {
            $committeeDrug->delete();

            return $this->sendResponse([], 'success.');
        } catch (\Throwable $e) {
            return $this->sendError("گزارش مد نظر شما یافت نشد", [
                'error' => 'Not Found',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }
    }
}
