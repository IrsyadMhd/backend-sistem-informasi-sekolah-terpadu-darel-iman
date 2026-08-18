<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\MutabaahDailyRequest;
use App\Services\MutabaahDailyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MutabaahDailyController extends Controller
{
    public function __construct(private MutabaahDailyService $service) {}

    public function context(Request $request): JsonResponse
    {
        $this->authorizeView($request);
        $data = $request->validate(['date' => ['required', 'date'], 'unit_id' => ['nullable', 'uuid'], 'kelas_id' => ['nullable', 'uuid'], 'rombel_id' => ['nullable', 'uuid'], 'supervisor_assignment_id' => ['nullable', 'uuid']]);

        return $this->ok('Konteks input berhasil dimuat.', $this->service->context($request->user(), $data));
    }

    public function students(Request $request): JsonResponse
    {
        $this->authorizeView($request);
        $data = $request->validate(['date' => ['required', 'date'], 'supervisor_assignment_id' => ['required', 'uuid'], 'search' => ['nullable', 'string', 'max:100']]);

        return $this->ok('Daftar siswa berhasil dimuat.', $this->service->students($request->user(), $data));
    }

    public function show(Request $request, string $studentId): JsonResponse
    {
        $this->authorizeView($request);
        $data = $request->validate(['date' => ['required', 'date'], 'supervisor_assignment_id' => ['required', 'uuid']]);

        return $this->ok('Detail siswa berhasil dimuat.', $this->service->detail($request->user(), $studentId, $data));
    }

    public function saveCell(MutabaahDailyRequest $request): JsonResponse
    {
        return $this->ok('Perubahan tersimpan.', $this->service->saveCell($request->user(), $request->validated(), $request));
    }

    public function bulkSave(MutabaahDailyRequest $request): JsonResponse
    {
        return $this->ok('Input massal tersimpan.', ['updated' => $this->service->bulkSave($request->user(), $request->validated(), $request)]);
    }

    public function copyPreviousDay(MutabaahDailyRequest $request): JsonResponse
    {
        return $this->ok('Data hari sebelumnya berhasil disalin.', ['copied' => $this->service->copyPrevious($request->user(), $request->validated(), $request)]);
    }

    public function finalizeStudent(MutabaahDailyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['student_ids'] = [$data['student_id']];

        return $this->ok('Data siswa berhasil difinalisasi.', ['finalized' => $this->service->finalize($request->user(), $data)]);
    }

    public function finalizeBulk(MutabaahDailyRequest $request): JsonResponse
    {
        return $this->ok('Finalisasi massal berhasil.', ['finalized' => $this->service->finalize($request->user(), $request->validated())]);
    }

    public function reopen(MutabaahDailyRequest $request): JsonResponse
    {
        return $this->ok('Data berhasil dibuka kembali.', $this->service->reopen($request->user(), $request->validated(), $request));
    }

    private function ok(string $message, mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data, 'meta' => (object) []]);
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()->hasRole('Super Admin') || $request->user()->can('mutabaah.daily.view'), 403);
    }
}
