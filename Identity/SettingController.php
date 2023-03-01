<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Resources\SettingResource;
use App\Http\Resources\SpecialityResource;
use App\Models\Setting;
use App\Models\Identity\Specialty;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SettingController extends Controller
{
    /**
     * Display a listing of the resources.
     *
     * @OA\Get(
     *     path="/api/identity/setting/version",
     *     tags={"setting"},
     *     summary="List of settings",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *       @OA\Items(ref="#/components/schemas/Setting")
     *
     *         ),
     *     ),
     * )
     *
     * @param Request $request
     * @return JsonResponse [string] message
     */
    public function getVersion(Request $request): JsonResponse
    {
        $version = Setting::where('keytitle', 'emed_api')->first();

        if ($version === null) {
            return $this->sendError("An unexpected error has occurred", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $success = [
            'version' => SettingResource::make($version),
        ];

        return $this->sendResponse($success, 'success.');
    }

}
