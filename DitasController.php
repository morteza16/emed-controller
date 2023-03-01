<?php

namespace App\Http\Controllers;

use App\Models\Identity\Provider;
use App\Models\Identity\User;
use App\Services\AuthorizeService;
use App\Services\DitasService;
use App\Services\Gateways\Ditas\Base;
use App\Services\Prescription\PatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DitasController extends Controller
{


    /**
     * Display the specified resource.
     *
     * @OA\post(
     *     path="/api/ditas",
     *     tags={"Ditas"},
     *     summary=" request to ditas",
     *     description="Returns a ditas response",
     *     @OA\Parameter(name="uri", description="uri of request", example="Https://apigateway.behdasht.gov.ir/oauth/token", required=true,
     *     @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="params", description="params to send to ditas service",
     *     required=false,
     *     example="{""params"":""[]"" }",
     *     style="deepObject",
     *     @OA\Schema(type="object"),
     *      in="query"),
     *     @OA\Parameter(name="method", description="method of request", example="post", @OA\Schema(type="string"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Prescription"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description=" not found"
     *     ),
     * )
     *
     *
     *
     */
    public function postDitas(Request $request)
    {
        $all=$request->all();
        $uri= $all['ditas-uri'] ?? null;
        $method = $all['ditas-method'] ?? 'POST';
        $option = $all['ditas-option'] ?? [];

        $res = Base::getClientResult($method, $uri, $option, false);
        if (!$res) {
            return false;
        }

        $status = $res->getStatusCode();
        $body1 = $res->getBody() . '';
        $body1 = str_replace("\r\n", "", $body1);
        $body1 = str_replace("\n", "", $body1);
        $body = json_decode($body1, true);

        return response($body, $status);
    }

    /**
     * search magic
     *
     * @OA\Post(
     *     path="/api/authorize/ditas",
     *     tags={"Ditas"},
     *     summary="Admin user can add authorized provider to providers table",
     *     @OA\Parameter(name="username", description="ditas username", example="ghom_his_beheshti", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="password", description="ditas password", example="Ghomti@0603",required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="token", description="ditas token", example="Basic Z2hvbV9oaXNfYmVoZXNodGlDbGllbnQ6M09KME05ZFZYSlA4TmJtQg==", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="pidSalamat", description="salamat pid", example="612ccc3661ec8b7c0c425948", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="pidTamin", description="tamin pid", example="612ccc3d61ec8b7c0c42594a", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="pidDitas", description="ditas pid", example="612ccc3361ec8b7c0c425947", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Drug"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param Request $request
     * @param DitasService $ditasService
     * @return JsonResponse [string] message
     */
    public function authorizeDitas(Request $request, AuthorizeService $authorizeService)
    {

        /* @var User $user */
        $user = auth('api')->user();
//        $national_code = $user->employee->national_code;
//        $doctor = User::first();
//        $provider = Provider::first();
//        $siam_code = isset($provider) ? $provider->siam_code : '859773E8-5D81-46C6-9DF1-5FB551B00495';
//        $medical_no = isset($doctor) ? $doctor->employee->medical_no : '152471';
        $username = $request->input('username');
        $password = $request->input('password');
        $pidSalamat = $request->input('pidSalamat');
        $pidTamin = $request->input('pidTamin');
        $pidDitas = $request->input('pidDitas');
        $token = $request->input('token');
        $token = substr($token, 5);
        $string = preg_replace('/[^a-zA-Z0-9.?;:!@#$%^&*(){}_+=|-]/', '', $token);
        $basic = "Basic ".$string;

        //permission in Service
        $result = $authorizeService->authorizeProvider($username, $password, $pidSalamat, $pidTamin, $basic);

        if (!$result || (isset($result['my_success']) && $result['my_success'] == false)){
            $message = $result['my_message'] ?? null;
            $authorize = false;
        }

        $authorize = isset($result['salamat']) && isset($result['tamin']) && $result['salamat'] && $result['tamin'];
        $success = [
            'authorize' => $authorize
        ];
        $message = $message ?? 'با موفقیت انجام شد';
        if(!$authorize){
            return $this->sendError($message ?? 'مقادیر وارد شده نامعتبر است', $success, 404);
        }

        return $this->sendResponse($success, $message);
    }

}
