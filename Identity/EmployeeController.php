<?php

namespace App\Http\Controllers\Identity;

use App\Http\Requests\Identity\Employee\EmployeeUpdateMobileRequest;
use App\Http\Requests\Identity\Employee\TaminMobileRequest;
use App\Models\Identity\User;
use App\Models\Identity\Employee;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ThrottleFailedApi;
use App\Http\Requests\Identity\Employee\RegisterRequest;
use App\Http\Requests\Identity\Employee\EmployeeNewRequest;
use App\Http\Requests\Identity\Employee\EmployeeUpdateRequest;
use App\Services\EmployeeService;
use App\Services\SmsService;
use App\Services\UserService;
use App\Utilities\SMS;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/identity/employee",
     *     tags={"identity-employee"},
     *     summary="List of Employees",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Employee"),
     *         ),
     *     ),
     *
     * )
     *
     * @param Request $request
     * @param EmployeeService $employeeService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, EmployeeService $employeeService)
    {

        /* @var User $user */
//        $user = auth('api')->user();
        $user=User::first();

        //permission in Service

        $employeesNULL = $employeeService->get($user);

        if ($employeesNULL === false) {
            return $this->sendError("An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $success = [
            'employees' => $employeeService->hideAttributes($employeesNULL, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }


    /**
     * Display a listing of the deleted resources.
     *
     * @OA\Get(
     *     path="/api/identity/employee/deleted",
     *     tags={"identity-employee-d"},
     *     summary="List of deleted employees",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Employee"),
     *         ),
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param EmployeeService $employeeService
     * @return JsonResponse [string] message
     */
    public function deleted(Request $request, EmployeeService $employeeService)
    {
        /* @var User $user */
        $user = auth('api')->user();

        //permission in Services

        $employeesNULL = $employeeService->get($user, null, true);
        if ($employeesNULL === false) {
            return $this->sendError("An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $success = [
            'employees' => $employeeService->hideAttributes($employeesNULL, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/api/identity/employee",
     *     tags={"identity-employee"},
     *     summary="store a new employee",
     *     @OA\Parameter(name="domain_id", example="90b16053-5033-4e4f-b150-895aa4bee247", @OA\Schema(type="string", format="uuid"), in="query"),
     *     @OA\Parameter(name="title", example="this is a title", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="description", example="your descriotion", @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="secure_link_enabled", example=false, @OA\Schema(type="boolean"), in="query"),
     *     @OA\Parameter(name="secure_link_key", @OA\Schema(type="string",  format="password"), in="query"),
     *     @OA\Parameter(name="secure_link_with_ip", example=false, @OA\Schema(type="boolean"), in="query"),
     *     @OA\Parameter(name="secure_expire_time", example="24", @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="ads_enabled", example=false, @OA\Schema(type="boolean"), in="query"),
     *     @OA\Parameter(name="present_type",description="1 means Auto and 0 means Manual" , example="1", @OA\Schema(type="string" ), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Employee"),
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
     * @param EmployeeNewRequest $request
     * @param EmployeeService $employeeService
     * @return JsonResponse [string] message
     */
    public function store(EmployeeNewRequest $request, EmployeeService $employeeService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        //permi
        //todo


        $array = $request->only(array_keys($request->rules()));
        $array['secure_expire_time'] = filter_var($request->secure_expire_time ?? 24, FILTER_VALIDATE_INT);

        $employee = $employeeService->create($user, $array, $msg);
        if ($employee === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $success = [
            'employee' => $employeeService->hideAttributes($employee, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/identity/employee",
     *     tags={"identity-employee"},
     *     summary="update a employee",
     *     @OA\Parameter(name="title", example="this is a title", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="description", example="your descriotion", @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="secure_link_enabled", example=false, @OA\Schema(type="boolean"), in="query"),
     *     @OA\Parameter(name="secure_link_key", @OA\Schema(type="string",  format="password"), in="query"),
     *     @OA\Parameter(name="secure_link_with_ip", example=false, @OA\Schema(type="boolean"), in="query"),
     *     @OA\Parameter(name="secure_expire_time", example="24", @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="ads_enabled", example=false, @OA\Schema(type="boolean"), in="query"),
     *     @OA\Parameter(name="present_type",description="1 means Auto and 0 means Manual" , example="1", @OA\Schema(type="string" ), in="query"),
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
     * @param EmployeeUpdateRequest $request
     * @param Employee $employee
     * @param EmployeeService $employeeService
     * @return JsonResponse [string] message
     */
    public function update(EmployeeUpdateRequest $request, Employee $employee, EmployeeService $employeeService)
    {
        //Done
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        // "ReadOnly: domain cannot be changed" ?

        //permission
        if (!$user->is_admin && $user->id != $employee->domain->owner_id) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }
        $array = $request->only(array_keys($request->rules()));
        $array['secure_expire_time'] = filter_var($request->secure_expire_time ?? 24, FILTER_VALIDATE_INT);

        $employeeNULL = $employeeService->update($employee, $array, $msg);
        if ($employeeNULL === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [
            'employee' => $employeeService->hideAttributes($employeeNULL, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/identity/mobile/employee",
     *     tags={"identity-employee"},
     *     summary="update Mobiles a employee",
     *     @OA\Parameter(name="salamatmobile", example="09126495396", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="taminmobile", example="09126495396", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="tamin", example="0", required=true, @OA\Schema(type="string"), in="query"),
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
    public function updateMobile(EmployeeUpdateMobileRequest $request, EmployeeService $employeeService, UserService $userService, SmsService $smsService)
    {
        //Done
        try {
            ThrottleFailedApi::limit(20);

            /* @var User $user */
            $user = auth('api')->user();
            $employee = $user->employee;

            $array = $request->only(array_keys($request->rules()));
            $array['mobile'] = $request->input('tamin') ? $request->input('taminmobile') : $request->input('salamatmobile');
            unset($array['tamin']);

            $employeeNULL = $employeeService->update($employee, $array, $msg);
            if ($employeeNULL === false) {
                return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $success = [
                'employee' => $employeeService->hideAttributes($employeeNULL, $user),
            ];

            // generate token
            $number = pow(10, 3);
            $token = random_int($number, ($number * 10) - 1);
            $userArr['password'] = $token;
            // 3. save token
            //TokenStoreFacade::saveToken($token, $param, $user->id);
            $userResult = $userService->update($user, $userArr, $msg);
            if ($userResult === false) {
                return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
//            TokenStoreFacade::saveTokenInDB($token, $user->id);
            // 4. send Token
            $smsService->lookUp($user, $token);

            return $this->sendResponse($success, 'success.');
        } catch (\Exception $e) {
            return response()->json('خطای بروزرسانی اطلاعات کاربر');
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/identity/tamin/mobile/employee",
     *     tags={"identity-employee"},
     *     summary="update Mobiles a employee",
     *     @OA\Parameter(name="taminmobile", example="09126495396", required=true, @OA\Schema(type="string"), in="query"),
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
     * @param TaminMobileRequest $request
     * @param EmployeeService $employeeService
     * @return JsonResponse [string] message
     */
    public function updateTaminMobile(TaminMobileRequest $request, EmployeeService $employeeService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        $employee = $user->employee;

        $array = ['taminmobile' => $request->input('taminmobile')];


        $employeeNULL = $employeeService->update($employee, $array, $msg);

        if ($employeeNULL === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [
            'employee' => $employeeService->hideAttributes($employeeNULL, $user),
        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/identity/employee/{id}",
     *     tags={"identity-employee"},
     *     summary="Show/Find employee by ID",
     *     description="Returns a single employee",
     *     @OA\Parameter(name="id", description="ID of employee to return", example="90b16666-2f97-4ffd-8f07-3ba8a221d7b8", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
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
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param EmployeeService $employeeService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, EmployeeService $employeeService)
    {
        $employee_id = $id;

        /* @var User $user */
        $user = auth('api')->user();

        //permission in service


        $employee = $employeeService->first($user, $employee_id);
        if (!$employee) {
            return $this->sendError('Employee not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [
            'employee' => $employeeService->hideAttributes($employee, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * restore the specified resource.
     *
     * @OA\Post(
     *     path="/api/identity/employee/{id}/restore",
     *     tags={"identity-employee-d"},
     *     summary="restore employee",
     *     @OA\Parameter(name="id", example="90b16666-2f97-4ffd-8f07-3ba8a221d798", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
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
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param EmployeeService $employeeService
     * @return JsonResponse [string] message
     */
    public function restore(Request $request, $id, EmployeeService $employeeService)
    {
        /* @var User $user */
        $user = auth('api')->user();

        //permi
        if (!$user->is_admin) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }

        //permi
        $employee = $employeeService->restore($user, $id, $msg);
        if (!$employee) {
            return $this->sendError($msg ?? 'Employee not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/identity/employee/{employee}",
     *     tags={"identity-employee-d"},
     *     summary="delete a employee",
     *     @OA\Parameter(name="employee", example="90b16666-2f97-4ffd-8f07-3ba8a221d798", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
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
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Employee $employee
     * @param EmployeeService $employeeService
     * @return JsonResponse [string] message
     */
    public function destroy(Employee $employee, EmployeeService $employeeService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        //permi
        if (!$user->is_admin && $user->id != $employee->owner_id) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $response = $employeeService->delete($user, $employee, $msg);
        if (!$response) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Force-Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/identity/employee/{id}/remove",
     *     tags={"identity-employee-d"},
     *     summary="Force-delete a employee",
     *     @OA\Parameter(name="id", example="90b16666-2f97-4ffd-8f07-3ba8a221d798", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
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
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param $id
     * @param EmployeeService $employeeService
     * @return JsonResponse [string] message
     */
    public function remove($id, EmployeeService $employeeService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        //permission
        if (!$user->is_admin) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $response = $employeeService->remove($user, $id);
        if (!$response) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/identity/profile",
     *     tags={"identity-employee"},
     *     summary="Show owner employee ",
     *     description="Returns profile of employee",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Employee"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid Employee"
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
     * @param EmployeeService $employeeService
     * @return JsonResponse [string] message
     */
    public function profile(Request $request, EmployeeService $employeeService)
    {


        /* @var User $user */
        $user = auth('api')->user();
        $employee_id = $user->employee_id;

        //permission in service


        $employee = $employeeService->first($user, $employee_id);
        if (!$employee) {
            return $this->sendError('Employee not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [
            'employee' => $employeeService->hideAttributes($employee, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }


    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/identity/register",
     *     tags={"identity-employee"},
     *     summary="regoster  a employee",
     *     @OA\Parameter(name="national_code", example="0010503366", required=true, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="mobile", example="09126495396", @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="salamat_user", example="salamt", @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="salamat_pass", @OA\Schema(type="string",  format="password"), in="query"),
     *     @OA\Parameter(name="salamat_pass_confirmation", @OA\Schema(type="string",  format="password"), in="query"),
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
     *      security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param RegisterRequest $request
     * @param EmployeeService $employeeService
     * @return JsonResponse [string] message
     */


    public function register(RegisterRequest $request, EmployeeService $employeeService)
    {

        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        $employee=$user->employee;

        if($employee==null){
            return $this->sendError('پرسنل مورد نظر یافت نشد', ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        //permission

        $array = $request->only(array_keys($request->rules()));

        $employeeNULL = $employeeService->update($employee, $array, $msg);
        if ($employeeNULL === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [
            'employee' => $employeeService->hideAttributes($employeeNULL, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/identity/required/complete/info",
     *     tags={"identity-employee"},
     *     summary="Employee need to provide complete information",
     *     description="Returns (true or false) if  a field is not filled in returns true. Else, returns false",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Employee"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid Employee"
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
     * @param EmployeeService $employeeService
     * @return JsonResponse [string] message
     */
    public function RequiredCompleteInfo(Request $request, EmployeeService $employeeService)
    {
        /* @var User $user */
        $user = auth('api')->user();
        $employee = $user->employee;
        //permission in service

        if ($employee == null) {
            return $this->sendError($msg ?? "Employee Not found", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $info = $employeeService->requiredCompleteInfo($employee);
        $requiredCompleteInfo = (bool)$info;

        $success = [
            'complete_info' => $requiredCompleteInfo ,
        ];
        return $this->sendResponse($success, 'success.');
    }

}
