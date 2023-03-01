<?php

namespace App\Http\Controllers\Stimular;

use Illuminate\Support\Facades\Log;
use App\Models\Report\CommitteeDrug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrescriptionController
{
    /**
     * Display a URL for report.
     *
     * @OA\Get(
     *
     *     path="/api/rpt/prescription/{id}",
     *     tags={"Reports"},
     *     summary="Prescription Report",
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
     * @param CommitteeDrug $committeeDrug
     * @param Request $request
     * @return JsonResponse|StreamedResponse
     */
    public function PrescriptionReport(CommitteeDrug $committeeDrug, Request $request)
    {
        try {
            $user = auth()->user();
            if ($user->id !== $committeeDrug->user_id || $committeeDrug->type !== 'prescription') {
                return response()->json([
                    'success' => false,
                    'error' => 'شما دسترسی لازم برای مشاهده این گزارش را ندارید!'
                ]);
            }

            $prescriptions = DB::select(
                "exec [dbo].[Prescription] @fdate = ?, @tdate = ?, @user_id = ?",
                array($committeeDrug->fromdate, $committeeDrug->todate, $user->id)
            );

            $result = $this->generateCSV(
                $prescriptions,
                ['کد ملی', 'نام بیمار', 'سن', 'تاریخ تولد', 'گروه سنی', 'جنسیت', 'اطلاعات بیمه', 'نام بیمه', 'تاریخ اعتبار بیمه', 'تلفن بیمار', 'وضعیت تاهل', 'تاریخ نسخه', 'زمان نسخه'],
                ['national_code', 'name', 'age', 'birthdate', 'description', 'gender', 'geo_info', 'product', 'account_validto', 'cellphone', 'marital', 'pres_date', 'pres_time']
            );

            return Response::stream($result['callback'], 200, $result['headers']);
        } catch (\Exception $ex) {
            return response()->json([
                'success' => false,
                'error' => 'متاسفانه مشکلی در سرور رخ داده است'
            ]);
        }
    }

    /**
     * Display a URL for report.
     *
     * @OA\Get(
     *
     *     path="/api/rpt/prescription-details/{id}",
     *     tags={"Reports"},
     *     summary="Prescription Details Report",
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
     * @param CommitteeDrug $committeeDrug
     * @param Request $request
     * @return JsonResponse|StreamedResponse
     */
    public function PrescriptionDetailsReport(CommitteeDrug $committeeDrug, Request $request)
    {
        try {
            $user = auth()->user();
            if ($user->id !== $committeeDrug->user_id || $committeeDrug->type !== 'prescription-details') {
                return response()->json([
                    'success' => false,
                    'error' => 'شما دسترسی لازم برای مشاهده این گزارش را ندارید!'
                ]);
            }

            $prescriptions = DB::select(
                "exec [dbo].[Prescription_Details] @fdate = ?, @tdate = ?, @user_id = ?",
                array($committeeDrug->fromdate, $committeeDrug->todate, $user->id)
            );

            $result = $this->generateCSV(
                $prescriptions,
                ['کد ملی', 'نام بیمار', 'سن', 'تاریخ تولد', 'گروه سنی', 'جنسیت', 'اطلاعات بیمه', 'نام بیمه', 'تاریخ اعتبار بیمه', 'تاریخ نسخه', 'زمان نسخه', 'پیغام ارسالی', 'شماره رهگیری', 'شماره سریال نسخه', 'کد قلم', 'نام قلم', 'تعداد', 'تواتر مصرف', 'میزان مصرف'],
                ['national_code', 'name', 'age', 'birthdate', 'description', 'gender', 'geo_info', 'product', 'account_validto', 'pres_date', 'pres_time', 'res_message', 'tracking_code', 'subscription', 'code', 'en_name', 'count', 'consumption_name', 'consumption_des']
            );

            return Response::stream($result['callback'], 200, $result['headers']);
        } catch (\Exception $ex) {
            return response()->json([
                'success' => false,
                'error' => 'متاسفانه مشکلی در سرور رخ داده است'
            ]);
        }
    }

    private function generateCSV($data, array $columns, array $fields)
    {
        $fileName = 'report.csv';
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $data = json_decode(json_encode($data), true);
        $callback = function() use($data, $columns, $fields) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $columns);

            foreach ($data as $item) {
                $row = [];
                for ($i = 0; $i < count($fields); $i++) {
                    $val = $item[$fields[$i]];
                    $row[$columns[$i]] = empty($val) ? '' : $val;
                }
                fputcsv($file, $row);
            }

            fclose($file);
        };
        return [
            'headers' => $headers,
            'callback' => $callback
        ];
    }
}
