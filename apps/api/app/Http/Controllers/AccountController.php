<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\DeleteAccountRequest;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class AccountController extends Controller
{
    // Meme nom/chemin que AuthController::REFRESH_COOKIE_NAME/PATH : le
    // cookie doit etre efface avec exactement le meme Path pour que le
    // navigateur le retire (sinon il reste actif jusqu'a expiration).
    private const REFRESH_COOKIE_NAME = 'jeuncy_refresh_token';

    private const REFRESH_COOKIE_PATH = '/api/auth';

    public function __construct(private readonly AccountService $service) {}

    public function export(Request $request): JsonResponse
    {
        return response()->json($this->service->exportData($request->user()));
    }

    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        $this->service->deleteAccount($request->user(), $request->validated('confirm_email'));

        return response()
            ->json(['deleted' => true])
            ->cookie(Cookie::forget(self::REFRESH_COOKIE_NAME, self::REFRESH_COOKIE_PATH));
    }
}
