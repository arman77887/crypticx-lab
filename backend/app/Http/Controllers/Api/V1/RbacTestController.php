<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RbacTestController extends Controller
{
    public function usersView(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'RBAC permission check passed.',
            'permission' => 'users.view',
        ]);
    }
}
