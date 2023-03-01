<?php

namespace App\Http\Controllers\Stimular;

use App\Http\Controllers\Stimular\Azadi;
use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use App\Models\Report\CommitteeDrug;
use App\Services\EmployeeService;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\SerializableClosure\SerializableClosure;


class CommitteeController extends Controller
{
    /**
     * Display a URL for report.
     *
     * @OA\Get(
     *
     *     path="/api/rpt/doctors/{id}",
     *     tags={"Reports"},
     *     summary="Karname eache doctor",
     *     @OA\Parameter(name="id", example="96BC359A-9630-4736-BB26-1406E881638E", @OA\Schema(type="string"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *     ),
     *      security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse [string] message
     */
    public function committee(string $id, Request $request)
    {
        $token = Str::random(32);
        /* @var User $user */
        $user = auth('api')->user();
        //check permission
        //$ability = 'can_see_doctors';
        //$this->authorize($ability);

        $report = CommitteeDrug::query()->where([
            ['id', '=', $id],
            ['user_id', '=', $user->id]
        ])->first();

        if (!$report) {
            return $this->sendError("گزارش مد نظر شما یافت نشد!", [
                'error' => 'Not Found'
            ], 404);
        }


        Azadi\Stimular\StimularController::setTokenObj($token, [
            'token' => $token,
            'user_id' => $user->id,
            'from' => $report->fromdate,
            'to' => $report->todate,
            'report_file' => 'committee.mrt',
            'report_title' => 'کمیته کشوری بررسی نسخ و خدمات پزشکی ',
            'onView' => [
                'c' => self::class,
                'm' => 'onView',
            ],
            'onPrepareVariables' => new SerializableClosure(
                function ($token_obj, $args) {
                    return $args;
                }
            ),
            'onData' => [
                'c' => self::class,
                'm' => 'onData',
            ],
            'onDesign' => [
                'c' => self::class,
                'm' => 'onDesign',
            ],
            'onSaveReport' => [
                'c' => self::class,
                'm' => 'onSaveReport',
            ],
            'onPrintReport' => '',
            'onBeginExportReport' => '',
            'onEndExportReport' => '',
            'onEmailReport' => '',
        ], 24 * 3600);

        $success = [
            'url' => route('report_viewer', ['token' => $token])
        ];
        return $this->sendResponse($success, 'success.');
    }


    public function onView($token_obj, $args)
    {
        $s = $token_obj;
        /* @var User $user */
        $user = User::where('id', $token_obj['user_id'])->first();

        //check permission
//        $ability = 'can_see_doctors';
//        $this->authorize($ability);

//        $args->allowed = false;
//        $args->error_msg = 'ErrorW msg ...';

        return $args;
    }

    public function onDesign($token_obj, $args)
    {
        $user = User::where('id', $token_obj['user_id'])->first();

        $args->allowed = false;
        $args->error_msg = 'Error msg ...';

        return $args;
    }

    public function onData($token_obj, $args)
    {
        $user = User::query()->where('id', $token_obj['user_id'])->first();
        $ip = request()->ip();

        $committees = collect(DB::select(
            "exec [dbo].[CommitteeSheet_level01] @fdate = ?, @tdate = ?, @user_id = ?", array($token_obj['from'], $token_obj['to'], $user->id)))
            ->map(function($item) {
                $item->prescribing_p = round((float)$item->prescribing_p, 2);
                return $item;
            })->all();

        $prescriptions = DB::select(
            "exec [dbo].[CommitteeSheet_level02] @fdate = ?, @tdate = ?, @user_id = ?",
            array($token_obj['from'], $token_obj['to'], $user->id)
        );

        $header = DB::select(
            "exec [dbo].[CommitteeSheet_Header] @fdate = ?, @tdate = ?, @user_id = ?",
            array($token_obj['from'], $token_obj['to'], $user->id)
        )[0];
        foreach (['pres_i_avg', 'pres_p_avg', 'prec_one_item', 'prec_much_item', 'generic', 'brand', 'pres_amp'] as $key) {
            $header->$key = round((float)$header->$key, 2);
        }

        $v = Verta::now()->format('Y/m/d - H:i');
        return [
            'ip' => 'محل اخذ گزارش : '  .$ip,
            'committees' => $committees,
            'prescriptions' => $prescriptions,
            'info' => [
                'from' => $token_obj['from'],
                'to' => $token_obj['to'],
                'full_name' => $user->full_name,
                'specialty' => $user->employee->speciality->description,
                'medical_no' => $user->employee->medical_no
            ],
            'header' => $header,
            'report_title' => 'برگ کمیته بررسی نسخ دارو ',
            'Print' => 'چاپ : ' . $v,
        ];
    }

    public function onSaveReport($token_obj, $args)
    {
        $user = User::where('id', $token_obj['user'])->first();

        $args->allowed = false;
        $args->error_msg = 'Error msg ...';

        return $args;
    }
}
