<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeployStaticFrontendRequest;
use App\Services\StaticFrontendDeploymentService;
use Illuminate\Http\JsonResponse;

class DeployStaticFrontendController extends Controller
{
    public function __invoke(
        DeployStaticFrontendRequest $request,
        StaticFrontendDeploymentService $deployer,
    ): JsonResponse {
        $result = $deployer->deploy($request->string('commit')->toString());

        return response()->json([
            'status' => 'deployed',
            ...$result,
        ]);
    }
}
