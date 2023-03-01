<?php

namespace App\Http\Controllers\Prescription;

use App\Http\Requests\FilterRequest;
use App\Http\Resources\Prescription\RegistrationResource;
use App\Http\Resources\Prescription\RegistrationPaginateResource;
use App\Models\Identity\User;
use App\Models\Prescription\Prescription;
use App\Models\Prescription\Registration;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ThrottleFailedApi;
use App\Services\Prescription\RegistrationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * /**
 * Class Registration.
 *
 * @package namespace App\Models\Registration;
 * @OA\Schema ( required={"id"}, schema="Registration")
 * App\Models\Registration\Registration
 */
class RegistrationController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/prescription/registration",
     *     tags={"Prescription-Registration"},
     *     summary="List of Registrations",
     *     @OA\Parameter(name="page", description="page of registrations", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Registration"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param FilterRequest $request
     * @param RegistrationService $registrationService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, RegistrationService $registrationService)
    {
        /* @var User $user */
        $user = auth('api')->user();
        try {
            $limit = $request->input('limit') ?? 10;
            $page = $request->input('page') ?? 1;
            $items = Registration::query()
                ->whereHas('prescription', function($query) use($user){
                    $query->where('user_id', $user->id)->where('created_at', '>', Carbon::now()->subHours(24));
                })
                ->orderBy('created_at', 'desc')->get()->unique(function ($item) {
                    return $item['tracking_code'] == null ? $item['id'] : $item['tracking_code'];
                });

            $registrations = new LengthAwarePaginator($items->forPage($page, $limit), $items->count(), $limit, $page);
            $success = new RegistrationPaginateResource($registrations);

            return $this->sendResponse($success, 'success.');
        } catch (\Exception $ex) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/prescription/registration/{id}",
     *     tags={"Prescription-Registration"},
     *     summary="Show/Find Registration by ID",
     *     description="Returns a single Registration",
     *     @OA\Parameter(name="id", description="ID of Registration to return", example="47e41f4b-35fc-49ac-a8aa-0001bcc0c0ec", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Registration"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Registration not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param RegistrationService $registrationService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, RegistrationService $registrationService)
    {
        $registration_id = $id;

        /* @var User $user */
        $user = auth('api')->user();

        //permission in service


        $registration = $registrationService->first($user, $registration_id);
        if (!$registration) {
            return $this->sendError('Registration not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [
            'Registration' => new RegistrationResource($registration),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/api/prescription/registration",
     *     tags={"Prescription-Registration"},
     *     summary="store a new registration",
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
     *         @OA\JsonContent(ref="#/components/schemas/Registration"),
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
     * @param RegistrationNewRequest $request
     * @param RegistrationService $registrationService
     * @return JsonResponse [string] message
     */
    public function store(RegistrationNewRequest $request, RegistrationService $registrationService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        //permi
        //todo


        $array = $request->only(array_keys($request->rules()));
        $array['secure_expire_time'] = filter_var($request->secure_expire_time ?? 24, FILTER_VALIDATE_INT);

        $registration = $registrationService->create($user, $array, $msg);
        if ($registration === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }

        $success = [
            'registration' => $registrationService->hideAttributes($registration, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/prescription/registration/{registration}",
     *     tags={"Prescription-Registration"},
     *     summary="update a registration",
     *     @OA\Parameter(name="registration", example="90b16666-2f97-4ffd-8f07-3ba8a221d798", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
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
     *         @OA\JsonContent(ref="#/components/schemas/Registration"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Registration not found"
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
     * @param RegistrationUpdateRequest $request
     * @param Registration $registration
     * @param RegistrationService $registrationService
     * @return JsonResponse [string] message
     */
    public function update(RegistrationUpdateRequest $request, Registration $registration, RegistrationService $registrationService)
    {
        //Done
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();
        // "ReadOnly: domain cannot be changed" ?

        //permission
        if (!$user->is_admin && $user->id != $registration->domain->owner_id) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_FORBIDDEN);
        }
        $array = $request->only(array_keys($request->rules()));
        $array['secure_expire_time'] = filter_var($request->secure_expire_time ?? 24, FILTER_VALIDATE_INT);

        $registrationNULL = $registrationService->update($registration, $array, $msg);
        if ($registrationNULL === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [
            'registration' => $registrationService->hideAttributes($registrationNULL, $user),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/Prescription/{registration}",
     *     tags={"Prescription-Registration"},
     *     summary="delete a registration",
     *     @OA\Parameter(name="registration", example="90b16666-2f97-4ffd-8f07-3ba8a221d798", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Registration"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Registration not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Registration $registration
     * @param RegistrationService $registrationService
     * @return JsonResponse [string] message
     */
    public function destroy(Registration $registration, RegistrationService $registrationService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        //permi
        if (!$user->is_admin && $user->id != $registration->owner_id) {
            return $this->sendError("Invalid request", ['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $response = $registrationService->delete($user, $registration, $msg);
        if (!$response) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = [];
        return $this->sendResponse($success, 'success.');
    }

}
