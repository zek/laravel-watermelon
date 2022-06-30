<?php

namespace NathanHeffley\LaravelWatermelon;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use NathanHeffley\LaravelWatermelon\Exceptions\ConflictException;
use NathanHeffley\LaravelWatermelon\Traits\Watermelon;

class SyncService
{
    protected array $models;

    public function __construct(array $models)
    {
        $this->models = $models;
    }

    public function pull(Request $request): JsonResponse
    {
        $lastPulledAt = $request->get('last_pulled_at');

        $timestamp = now()->timestamp;

        $changes = [];

        if ($lastPulledAt === 'null') {
            foreach ($this->models as $name => $class) {
                $changes[$name] = [
                    'created' => (new $class)::watermelon()
                        ->get()
                        ->map->toWatermelonArray(),
                    'updated' => [],
                    'deleted' => [],
                ];
            }
        } else {
            $lastPulledAt = Carbon::createFromTimestampUTC($lastPulledAt);

            foreach ($this->models as $name => $class) {
                /** @var Model|SoftDeletes $model */
                $model = new $class;
                $changes[$name] = [
                    'created' => (new $class)::withoutTrashed()
                        ->where($model->getCreatedAtColumn(), '>', $lastPulledAt)
                        ->watermelon()
                        ->get()
                        ->map->toWatermelonArray(),
                    'updated' => (new $class)::withoutTrashed()
                        ->where($model->getCreatedAtColumn(), '<=', $lastPulledAt)
                        ->where($model->getUpdatedAtColumn(), '>', $lastPulledAt)
                        ->watermelon()
                        ->get()
                        ->map->toWatermelonArray(),
                    'deleted' => (new $class)::onlyTrashed()
                        ->where($model->getCreatedAtColumn(), '<=', $lastPulledAt)
                        ->where($model->getDeletedAtColumn(), '>', $lastPulledAt)
                        ->watermelon()
                        ->pluck($model->getKey()),
                ];
            }
        }

        return response()->json([
            'changes' => $changes,
            'timestamp' => $timestamp,
        ]);
    }

    public function push(Request $request): JsonResponse
    {
        DB::beginTransaction();

        /** @var Watermelon $class */
        foreach ($this->models as $name => $class) {
            if (!$request->input($name) || !$class::allowWatermelonCreate()) {
                continue;
            }

            collect($request->input("$name.created"))->each(function ($create) use ($class) {
                /** @var Watermelon $model */
                $model = new $class;

                $create = collect($create)->only($model->watermelonAttributes);

                try {
                    /** @var Watermelon $model */
                    $model = $class::query()->whereKey(Arr::get($create, $model->getKeyName()))->firstOrFail();
                    if ($model->allowWatermelonUpdate()) {
                        $model->update($create);
                    }
                } catch (ModelNotFoundException) {
                    $class::query()->create($create->toArray());
                }
            });
        }

        try {
            /** @var Watermelon $class */
            foreach ($this->models as $name => $class) {
                if (!$request->input($name)) {
                    continue;
                }

                collect($request->input("$name.updated"))->each(function ($update) use ($class) {
                    /** @var Watermelon $model */
                    $model = new $class;

                    $update = collect($update)->only($model->watermelonAttributes);

                    if ($class::onlyTrashed()->whereKey($update->get($model->getKeyName()))->count() > 0) {
                        throw new ConflictException;
                    }

                    try {
                        /** @var Watermelon $task */
                        $task = $class::query()
                            ->whereKey($update->get($model->getKeyName()))
                            ->watermelon()
                            ->firstOrFail();

                        if ($task->allowWatermelonUpdate()) {
                            $task->update($update->toArray());
                        }
                    } catch (ModelNotFoundException) {
                        try {
                            if ($class::allowWatermelonCreate()) {
                                $class::query()->create($update->toArray());
                            } else {
                                throw new ConflictException;
                            }
                        } catch (QueryException) {
                            throw new ConflictException;
                        }
                    }
                });
            }
        } catch (ConflictException) {
            DB::rollBack();

            return response()->json('', 409);
        }

        /** @var Watermelon $class */
        foreach ($this->models as $name => $class) {
            if (!$request->input($name)) {
                continue;
            }

            collect($request->input("$name.deleted"))->each(function ($delete) use ($class) {
                /** @var Watermelon $model */
                $model = $class::query()->whereKey($delete)->watermelon()->first();
                if ($model->allowWatermelonDelete()) {
                    $model->delete();
                }
            });
        }

        DB::commit();

        return response()->json('', 204);
    }
}
