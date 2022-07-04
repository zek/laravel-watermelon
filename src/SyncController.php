<?php

namespace NathanHeffley\LaravelWatermelon;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SyncController extends Controller
{
    public function pull(SyncService $watermelon, Request $request): JsonResponse
    {
        $res = $watermelon->pull($request);
        return response()->json($res);
    }

    public function push(SyncService $watermelon, Request $request): JsonResponse
    {
        $res = $watermelon->push($request);

        return response()->json($res, 204);
    }
}
