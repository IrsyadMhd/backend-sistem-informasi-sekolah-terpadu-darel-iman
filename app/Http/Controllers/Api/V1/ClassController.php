<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexRequest;
use App\Repositories\Contracts\ClassRepositoryInterface;
use Illuminate\Http\JsonResponse;

class ClassController extends Controller
{
    public function __construct(private readonly ClassRepositoryInterface $classRepository) {}

    public function index(IndexRequest $request): JsonResponse
    {
        $data = $this->classRepository->paginate(
            search: (string) $request->validated('search', ''),
            perPage: (int) $request->validated('per_page', 15)
        );

        return response()->json($data);
    }
}
