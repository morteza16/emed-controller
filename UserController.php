<?php

namespace App\Http\Controllers;

use App\Http\Requests\Identity\Employee\EmployeeUpdateMobileRequest;
use App\Http\Requests\User\OtpRequest;
use App\Http\Requests\User\UserPasswordRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\SettingResource;
use App\Http\Resources\TokenResource;
use App\Http\Resources\UserResource;
use App\Models\Identity\Property;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Models\Identity\Employee;
use App\Http\Controllers\Controller;
use App\Models\Identity\UserProvider;
use App\Models\Setting;
use App\Services\DitasService;
use App\Services\SmsService;
use App\Services\UserService;
use App\Http\Middleware\ThrottleFailedApi;
use App\Http\Requests\Identity\Employee\RegisterRequest;
use App\Http\Requests\Identity\Employee\EmployeeNewRequest;
use App\Http\Requests\Identity\Employee\EmployeeUpdateRequest;
use App\Services\EmployeeService;
use App\Utilities\SMS;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use phpDocumentor\Reflection\Types\Collection;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/user/{user}",
     *     tags={"admin-user"},
     *     summary="update a user",
     *     @OA\Parameter(name="user", example="90b16666-2f97-4ffd-8f07-3ba8a221d798", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Parameter(name="title", example="this is a title", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/User"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param UserUpdateRequest $request
     * @param User $user
     * @param UserService $userService
     * @return JsonResponse [string] message
     */
    public function update(UserUpdateRequest $request, User $user, UserService $userService)
    {
        //Done
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        // "ReadOnly: domain cannot be changed" ?

        //permission
        if (!$user->is_admin && $user->id != $user->domain->owner_id) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }
        $array = $request->only(array_keys($request->rules()));
        $array['secure_expire_time'] = filter_var($request->secure_expire_time ?? 24, FILTER_VALIDATE_INT);

        $userNULL = $userService->update($user, $array, $msg);
        if ($userNULL === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [
            'user' => $userService->hideAttributes($userNULL, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Get(
     *     path="/api/checkpassword/user",
     *     tags={"admin-user"},
     *     summary="sets password for user",
     *     @OA\Parameter(name="password", example="123456", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Employee"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param EmployeeUpdateMobileRequest $request
     * @param Employee $employee
     * @param \App\Services\Identity\EmployeeService $employeeService
     * @return JsonResponse [string] message
     */
    public function checkPassword(UserPasswordRequest $request, UserService $userService)
    {
        ThrottleFailedApi::limit(20);
        //test deploy

        /* @var User $user */
        $user = auth('api')->user();
        $userPassword = $user->password;

        $pass = $request->input('password');

        if ($pass != $userPassword) {
            return $this->sendError("توکن ارسالی نامعتبر است");
        }
        $array['password'] = $pass;
        $userService->update($user, $array);

        $success = [
            'user' => $user->name,
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/user/{id}",
     *     tags={"admin-user"},
     *     summary="Show/Find user by ID",
     *     description="Returns a single user",
     *     @OA\Parameter(name="id", description="ID of user to return", example="90b16666-2f97-4ffd-8f07-3ba8a221d7b8", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/User"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param EmployeeService $userService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, EmployeeService $userService)
    {
        $user_id = $id;

        /* @var User $user */
        $user = auth('api')->user();

        //permission in service


        $user = $userService->first($user, $user_id);
        if (!$user) {
            return $this->sendError('Employee not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [
            'user' => $userService->hideAttributes($user, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }


    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/after/login/user",
     *     tags={"admin-user"},
     *     summary="Show msg after login for user by ID",
     *     description="Returns a msg for a user",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/User"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param UserService $userService
     * @return JsonResponse [string] message
     */
    public function afterLogin(Request $request, UserService $userService, EmployeeService $employeeService)
    {
        /* @var User $user */
        $user = auth('api')->user();
        $user_id = $user->id;
        //permission in service


        $user = $userService->first($user, $user_id);
        if (!$user) {
            return $this->sendError('User not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $employee = $employeeService->first($user, $user->employee_id);
        $dt = new \DateTime();
        $is_first=$employeeService->isNewUser($user);
        $today = $dt->format('Y-m-d H:i:s');
        $settings=Setting::all();
        $waiting = Property::where('name', 'waiting')->value('value');
        $success = [
            'user' => new UserResource($user),
            'employee' => new EmployeeResource($employee),
            'is_Admin' => $user->is_admin,
            'is_first' => $is_first,
            'settings'=>SettingResource::collection($settings),
            'datetime' => $today,
            'waiting' => $waiting
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/active/sessions/user",
     *     tags={"admin-user"},
     *     summary="Show active sessions for user by ID",
     *     description="Returns a msg for a user",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/User"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param UserService $userService
     * @return JsonResponse [string] message
     */
    public function activeSessions(Request $request, UserService $userService, EmployeeService $employeeService)
    {
        /* @var User $user */
        $user = auth('api')->user();
        $user_id = $user->id;
        //permission in service


        $user = $userService->first($user, $user_id);
        if (!$user) {
            return $this->sendError('User not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }
        $active_tokens = $user->accessTokens()->where('revoked', false)->where('expires_at','>',Carbon::now())->get();
        $token_result = TokenResource::collection($active_tokens);
        $success = [
            'result' => $token_result
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     *
     *
     * @OA\Post(
     *     path="/api/create/session/otp/user",
     *     tags={"admin-user"},
     *     summary="create session with otp",
     *     @OA\Parameter(name="otp", description="otp for creating user session", example="1234", required=true, @OA\Schema(type="string"), in="query"),
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
     */
    public function createSessionOtp(OtpRequest $request, DitasService $ditasService)
    {
        /* @var User $user */
        $user = auth('api')->user();
        $user_id = $user->id;
        //permission in service
        $otp = $request->input('otp');
        $employee = $user->employee;
        $username = $user->employee->salamat_user;
        $pass = $user->employee->salamat_pass;
        $medical_no = $employee->medical_no;

        $userSession = $ditasService->createUserSessionOtp($otp, $username, $pass);
        if (!$userSession || (isset($userSession['my_success']) && $userSession['my_success'] == false)) {
            return $this->sendError($userSession['my_message'] ?? 'اختلال در سامانه وزارتخانه',
                ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__],
                404);
        }
        $success = true;
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/resend/password/user",
     *     tags={"admin-user"},
     *     summary="reset password",
     *     description="reset password",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/User"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param UserService $userService
     * @return JsonResponse [string] message
     */
    public function resendPassword(Request $request, UserService $userService, EmployeeService $employeeService, SmsService $smsService)
    {
        /* @var User $user */
        $user = auth('api')->user();
        $user_id = $user->id;

        $mobile = $user->employee->mobile;

        $token = $user->password;
//            TokenStoreFacade::saveToken($token, $param, $user->id);
        $smsService->lookUp($user, $token);
        $success = [
            'resend' => 'ok',
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * reset attempt the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/reset/unsuccessful/attempt",
     *     tags={"admin-user"},
     *     summary="reset attempt ",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/User"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param User $user
     * @param UserService $userService
     * @return JsonResponse [string] message
     */
    public function resetAttempt(UserService $userService)
    {
        //Done
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        $array['unsuccessful_attempt'] = 0;

        $userNULL = $userService->update($user, $array, $msg);
        if ($userNULL === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [
            'unsuccessful_attempt' => $user->unsuccessful_attempt,
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/reset/password/user",
     *     tags={"admin-user"},
     *     summary="reset user password",
     *     description="reset password",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/User"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param UserService $userService
     * @return JsonResponse [string] message
     */
    public function resetPassword(Request $request, UserService $userService, SmsService $smsService)
    {
        try {
            ThrottleFailedApi::limit(20);

            /* @var User $user */
            $user = auth('api')->user();
            $employee = $user->employee;
            $resend_count = cache()->has($user->id.'resend_count') ? (int)cache($user->id.'resend_count') : 0;
            $resend_ttl = cache()->has($user->id.'resend_ttl') ? cache($user->id.'resend_ttl') : now()->addDay();
            if ($resend_count >= 3) {
                return $this->sendError($msg ?? "امکان ارسال رمز در طول یک روز 3 بار می باشد", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // generate token
            $number = pow(10, 3);
            $token = random_int($number, ($number * 10) - 1);
            $userArr['password'] = $token;
            // 3. save token
            //TokenStoreFacade::saveToken($token, $param, $user->id);
            $userResult = $userService->update($user, $userArr, $msg);
            if ($userResult === false) {
                return $this->sendError($msg ?? "خطای بروزرسانی", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            //            TokenStoreFacade::saveTokenInDB($token, $user->id);
            // 4. send Token
            $smsService->lookUp($user, $token);
            $resend_count++;
            cache([$user->id.'resend_count' => $resend_count], $resend_ttl);
            cache([$user->id.'resend_ttl' => $resend_ttl], $resend_ttl);

            return $this->sendResponse(true, 'رمز 4 رقمی با موفقیت ارسال شد');
        }catch (\Exception $e) {
            return $this->sendError($msg ?? "خطای ارسال پیامک", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



}
