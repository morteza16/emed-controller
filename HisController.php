<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ThrottleFailedApi;
use App\Http\Requests\HisRequest;
use App\Http\Resources\Identity\AdmissionPaginateResource;
use App\Http\Resources\Identity\AdmissionResource;
use App\Models\Identity\Admission;
use App\Models\Identity\Provider;
use App\Models\Identity\User;
use App\Services\AuthorizeService;
use App\Services\DitasService;
use App\Services\Gateways\Ditas\Base;
use App\Services\Prescription\PatientService;
use Carbon\Carbon;
use Hekmatinasser\Verta\Facades\Verta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class HisController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/api/his/admission/queue",
     *     tags={"HIS"},
     *     summary="store a new admission queue in db",
     *     @OA\RequestBody(
     *     required=true,
     *      @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *               @OA\Property(
     *                    property="provider_siam_code",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="hospital",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="username",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="password",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="auth",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="pid",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="medical_no",
     *                    type="integer",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="specialty_name",
     *                    type="integer",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="specialty_code",
     *                    type="integer",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="national_code",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="erx_prodoct_code",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="erx_prodoct_name",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="fname",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="lname",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="payment",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="datetime",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="validity",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="fcash",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="lcash",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="avatar",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *               @OA\Property(
     *                    property="ReferralID",
     *                    type="string",
     *                    description="This statement was submitted to the Micfava company"
     *                 ),
     *                 example={"provider_siam_code":"859773E8-5D81-46C6-9DF1-5FB551B00495","hospital":"96279eb6-261b-4dd0-84c4-0b6516d2772a",
     *                 "username":"ghom_his_beheshti","password":"Ghomti@0603",
     *                 "auth":"Basic Z2hvbV9oaXNfYmVoZXNodGlDbGllbnQ6M09KME05ZFZYSlA4TmJtQg==","pid":"612ccc3661ec8b7c0c425948",
     *                 "medical_no":"94082","specialty_name":"متخصص بیهوشی",
     *                 "specialty_code":"171178","national_code":"2670132952","erx_prodoct_code":"EHR_ID",
     *                 "erx_prodoct_name":"EHR_INSDescription","fname":"Staff_First_Name",
     *                 "lname":"Staff_Last_Name","payment":1,
     *                 "datetime":"1401/04/29 11:26:43","validity":"1401/04/29 11:26:43",
     *                 "fcash":"91654014-c1fa-4d44-b8d5-0338e7f3473c","lcash":"متخصص بیهوشی",
     *                 "avatar":"","ReferralID":"123456"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Item"),
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception"
     *     ),
     * )
     *
     * @param ItemNewRequest $request
     * @param ItemService $itemService
     * @return JsonResponse [string] message
     */
    public function admission(HisRequest $request, AuthorizeService $authorizeService)
    {
        ThrottleFailedApi::limit(20);

        $authKey = ['username', 'password'];
        $pid = $request->input('pid');
        $authArray = $request->only($authKey);
        $basic = $request->input('auth');
        $token = substr($basic, 5);
        $string = preg_replace('/[^a-zA-Z0-9.?;:!@#$%^&*(){}_+=|-]/', '', $token);
        $authArray['token']= "Basic ".$string;
        $first = Provider::query()->firstWhere($authArray);
        $authorize = isset($first) ? 1 : 0;

        if(!$authorize){
            $result = $authorizeService->authorizeDitas($authArray['username'], $authArray['password'], $pid, $authArray['token']);
            if (!$result){
                return $this->sendError($result['my_message'] ?? 'خطای اعتبارسنجی بیمه',
                    ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__],
                    404);
            }
        }

        $itemKey = ['provider_siam_code', 'hospital', 'medical_no', 'specialty_name', 'specialty_code', 'national_code',
            'erx_prodoct_code', 'erx_prodoct_name', 'fname', 'lname', 'payment', 'datetime', 'validity', 'fcash', 'lcash',
            'avatar','ReferralID'];
        $itemArray = $request->only($itemKey);
        $result = Admission::query()->create($itemArray);
        $success = [
            'admission' => (bool)$result
        ];

        return $this->sendResponse($success, 'با موفقیت انجام شد');

    }

    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/his/queue",
     *     tags={"HIS"},
     *     summary="List of admission queue",
     *     @OA\Parameter(name="page", description="page of Headers", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
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
     * @return JsonResponse [string] message
     */
    public function getQueue(Request $request): JsonResponse
    {
        /* @var User $user */
        $user = auth('api')->user();
        $limit = $request->input('limit') ?? 10;
        $medical_no = $user->employee->medical_no;
        $provider = $user->providers()->where('is_active', 1)->first();
        if($provider==null){
            return $this->sendError("مرکز فعال برای شما وجود ندارد", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 404);
        }
        $siam = Provider::where('id', $provider->id)->value('siam_code');
        $date = \Hekmatinasser\Verta\Facades\Verta::now()->subHours(24)->format('Y/m/d H:i:s');
        $queue = Admission::where('datetime','>',$date)->where(function($query) use($medical_no){
            $query->WhereNull('medical_no')->orWhere('medical_no',$medical_no);
        })->where('is_visited', 0)->where('provider_siam_code', $siam)->orderBy('datetime', 'asc')->paginate($limit);

        if ($queue === null) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $success =  new AdmissionPaginateResource($queue);

        return $this->sendResponse($success, 'success.');
    }

}
