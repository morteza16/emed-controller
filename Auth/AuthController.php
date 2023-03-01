<?php

namespace App\Http\Controllers\Auth;

use App\Models\Identity\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserNameLoginRequest;
use App\Http\Requests\User\UserChangePassRequest;
use App\Rules\Mobile;
use App\Services\Identity\UserService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class AuthController extends Controller
{


    /**
     * Login user and create token
     *
     * @OA\Post(
     *     path="/oauth/login",
     *     tags={"oauth"},
     *     summary="oauth login",
     *     @OA\Parameter(name="username", example="admin", @OA\Schema(type="string"), required=true, in="query"),
     *     @OA\Parameter(name="password", example="123456", @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="remember_me", in="query", example="true", @OA\Schema(type="boolean") ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *     )
     * )
     *
     * @param UserNameLoginRequest $request
     * @return JsonResponse [string] access_token
     */
    public function login(UserNameLoginRequest $request)
    {


        $credentials = request(['username', 'password']);
        if (!\Auth::attempt($credentials)) {
            return $this->sendError('Unauthorized.', ['error'=>'Unauthorised', 'class' => __CLASS__, 'line' => __LINE__], 401);
        }

        $user = $request->user();

        $tokenResult = $user->createToken('Personal Access Token');
        $token = $tokenResult->token;
        if ($request->remember_me) {
            $token->expires_at = Carbon::now()->addWeeks(1);
        }
        $token->save();


        $success = [
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse(
                $tokenResult->token->expires_at
            )->toDateTimeString(),
        ];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Logout user (Revoke the token)
     *
     * @return [string] message
     */
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();

        $success = [];
        return $this->sendResponse($success, 'success.');
    }

    /**
     * Get the authenticated User
     *
     * @return [json] user object
     */
    public function user(Request $request)
    {
        return $this->sendResponse($request->user(), 'current user info');
    }


}
