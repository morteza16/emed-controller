<?php

namespace App\Http\Controllers\Identity;

use App\Http\Requests\FilterRequest;

use App\Http\Requests\Identity\Provider\UserProviderNewRequest;
use App\Http\Resources\Identity\UserProviderResource;
use App\Models\Identity\User;
use App\Models\Identity\UserProvider;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ThrottleFailedApi;
use App\Services\UserProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class UserProvider.
 * App\Models\UserProvider\UserProvider
 */
class UserProviderController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/identity/userprovider",
     *     tags={"Identity-UserProvider"},
     *     summary="List of UserProviders",
     *     @OA\Parameter(name="page", description="page of userProviders", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="filters", description="filters is an object include to and from ",
     *     required=false,
     *     example="{""from"":""2021-08-10"",""to"":""2021-09-10"", ""type"":1}",
     *     style="deepObject",
     *     @OA\Schema(type="object"),
     *      in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/UserProvider"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param FilterRequest $request
     * @param UserProviderService $userProviderService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, UserProviderService $userProviderService)
    {


        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service
        $limit = $request->input('limit') ?? 500;

        $userProviders = $userProviderService->get($user, null, $request->input('filters'));


        if ($userProviders === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $success = [
            'userProviders' => UserProviderResource::collection($userProviders),

        ];

        return $this->sendResponse($success, 'success.');
    }


    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/identity/userprovider/{id}",
     *     tags={"Identity-UserProvider"},
     *     summary="Show/Find UserProvider by ID",
     *     description="Returns a single UserProvider",
     *     @OA\Parameter(name="id", description="ID of UserProvider to return", example="F2361DA7-0C38-4DD6-8BA9-0393B4A2EA68", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/UserProvider"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="UserProvider not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param UserProviderService $userProviderService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, UserProviderService $userProviderService)
    {
        $userProvider_id = $id;

        /* @var User $user */
        $user = auth('api')->user();

        //permission in service

        $userProvider = $userProviderService->first($user, $userProvider_id);
        if (!$userProvider) {
            return $this->sendError('UserProvider not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [
            'UserProvider' => new UserProviderResource($userProvider),
        ];
        return $this->sendResponse($success, 'success.');
    }


    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/api/identity/userprovider",
     *      tags={"Identity-UserProvider"},
     *     summary="assign a new provider to user loggined",
     *     @OA\Parameter(name="provider_id", example="961d5296-b669-41af-90c2-105e3c7f4a8a", @OA\Schema(type="string", format="uuid"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/UserProvider"),
     *     ),
     *     @OA\Response(
     *         response=405,
     *         description="Validation exception"
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param UserProviderNewRequest $request
     * @param UserProviderService $userProviderService
     *
     * @return JsonResponse [string] message
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(UserProviderNewRequest $request, UserProviderService $userProviderService)
    {
        ThrottleFailedApi::limit(20);
        /* @var User $user */
        $user = auth('api')->user();


        $array = $request->only(array_keys($request->rules()));
        $array['is_active'] = 1;
        $providerId = $request->input('provider_id');
        $check = UserProvider::query()->where('provider_id', $providerId)->where('user_id', $user->id)->first();
        if($check!=null){
            $check->update(['is_active' => 1]);
            $userProvider = $check;
        } else{
            $userProvider = $userProviderService->create($user, $array, $msg);
        }

        if ($userProvider === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        UserProvider::query()->where('user_id',$user->id)->where('id','!=',$userProvider->id)->update([
            'is_active' => 0
        ]);
        $success = [
            'userProviders' => $userProvider,
        ];

        return $this->sendResponse($success, 'success.');
    }

}
