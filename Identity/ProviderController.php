<?php

namespace App\Http\Controllers\Identity;

use App\Http\Requests\FilterRequest;
use App\Http\Resources\Identity\ProviderPaginateResource;
use App\Http\Resources\Identity\ProviderResource;
use App\Models\Identity\Cooperation;
use App\Models\Identity\User;
use App\Models\Identity\Provider;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ThrottleFailedApi;
use App\Services\ProviderService;
use App\Services\UserProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Provider.
 * App\Models\Provider\Provider
 */
class ProviderController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/identity/provider",
     *     tags={"Identity-Provider"},
     *     summary="List of Providers",
     *     @OA\Parameter(name="page", description="page of providers", example="1", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="filters", description="filters is an object include to and from, cop:(university, headquarters, non-university, office)",
     *     required=false,
     *     example="{""from"":""2021-08-10"",""to"":""2021-09-10"", ""cop"":""university""}",
     *     style="deepObject",
     *     @OA\Schema(type="object"),
     *      in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Provider"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param FilterRequest $request
     * @param ProviderService $providerService
     * @return JsonResponse [string] message
     */
    public function index(Request $request, ProviderService $providerService)
    {


        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service
        $limit = $request->input('limit') ?? 500;
        $selected = ['id', 'city_id', 'parent_id', 'cooperation_id', 'name'];
        $providers = $providerService->get($user, null, $request->input('filters'), $limit,  $selected);


        if ($providers === false) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        $providers_pg = new ProviderPaginateResource($providers);
        $success = [
            'result' => $providers_pg,

        ];

        return $this->sendResponse($success, 'success.');
    }


    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/identity/provider/{id}",
     *     tags={"Identity-Provider"},
     *     summary="Show/Find Provider by ID",
     *     description="Returns a single Provider",
     *     @OA\Parameter(name="id", description="ID of Provider to return", example="F2361DA7-0C38-4DD6-8BA9-0393B4A2EA68", required=true, @OA\Schema(type="string", format="uuid"), in="path"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Provider"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Provider not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param ProviderService $providerService
     * @return JsonResponse [string] message
     */
    public function show(Request $request, $id, ProviderService $providerService)
    {
        $provider_id = $id;

        /* @var User $user */
        $user = auth('api')->user();

        //permission in service

        $provider = $providerService->first($user, $provider_id);
        if (!$provider) {
            return $this->sendError('Provider not found.', ['error' => 'Not Found', 'class' => __CLASS__, 'line' => __LINE__], 404);
        }

        $success = [
            'Provider' => new ProviderResource($provider),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Create the specified provider.
     *
     * @OA\Post(
     *     path="/api/identity/provider",
     *     tags={"Identity-Provider"},
     *     summary="Store new provider",
     *     @OA\Parameter(name="name", example="مطب شخصی", @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="cooperation", example="office", @OA\Schema(type="string"), in="query", description="cooperation = (office, non-university)"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Statement not found"
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
     * @param ProviderNewRequest $request
     * @param ProviderService $patternService
     * @return JsonResponse [string] message
     */
    public function store(Request $request, ProviderService $providerService, UserProviderService $userProviderService)
    {
        ThrottleFailedApi::limit(20);

        /* @var User $user */
        $user = auth('api')->user();

        $providerKey = ['name', 'cooperation_id'];

        $firstByName = $providerService->firstByName($request->input('name'));
        if($firstByName!=null) {
            $success = [
                'provider' => providerResource::make($firstByName),
                'registered'=>true,
            ];
            return $this->sendResponse($success, 'success.');
        }

        $providerArray = $request->only($providerKey);
        $providerArray['created_by'] = $user->id;
        $providerArray['cooperation_id'] = Cooperation::whereName($request->input('cooperation'))->first()->id;
        $providerArray['is_actived'] = 1;
        $providerArray['created_at'] = now();

        $provider = $providerService->create($user, $providerArray, $msg);
        if ($provider === false) {
            return $this->sendError($msg ?? "An unexpected error has occurred", ['error' => 'Server error', 'class' => __CLASS__, 'line' => __LINE__], 500);
        }


        $provider = provider::query()->find( $provider->id);
        $success = [
            'provider' => providerResource::make($provider),
            'registered' => false
        ];
        return $this->sendResponse($success, 'success.');
    }


}
