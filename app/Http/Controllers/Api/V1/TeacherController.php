<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexRequest;
use App\Repositories\Contracts\TeacherRepositoryInterface;
use Illuminate\Http\JsonResponse;

class TeacherController extends Controller
{
    public function __construct(private readonly TeacherRepositoryInterface $teacherRepository) {}

    public function index(IndexRequest $request): JsonResponse
    {
        $data = $this->teacherRepository->paginate(
            search: (string) $request->validated('search', ''),
            perPage: (int) $request->validated('per_page', 15)
        );

        return response()->json($data);
    }
}
