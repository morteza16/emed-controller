<?php

namespace App\Http\Controllers;

use App\Traits\SendJsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

/**
 *
 * @OA\Info(
 * title="Uptodate",
 * version="1.0.0",
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, SendJsonResponse;

    /**
     * Log unhandled requests
     *
     * @param  Request  $request
     *
     * @return JsonResponse
     */
    public function notFound(Request $request)
    {
        return $this->sendError('Route not found.', [
            'error' => 'Not Found',
            'class' => __CLASS__,
            'line' => __LINE__,
        ], 404);
    }
}
