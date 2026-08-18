<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MutabaahPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MutabaahPortalController extends Controller
{
    public function __construct(private MutabaahPortalService $service) {}

    public function children(Request $request): JsonResponse
    {
        $this->allow($request, 'mutabaah.daily.view');

        return $this->ok('Daftar anak berhasil dimuat.', $this->service->children($request->user()));
    }

    public function parentOverview(Request $request, string $studentId): JsonResponse
    {
        $this->allow($request, 'mutabaah.daily.view');
        $data = $request->validate(['date' => ['nullable', 'date']]);

        return $this->ok('Mutaba’ah anak berhasil dimuat.', $this->service->overview($this->service->parentStudent($request->user(), $studentId), $data));
    }

    public function parentHistory(Request $request, string $studentId): JsonResponse
    {
        $this->allow($request, 'mutabaah.daily.view');
        $data = $this->historyFilters($request);

        return $this->ok('Riwayat anak berhasil dimuat.', $this->service->history($this->service->parentStudent($request->user(), $studentId), $data));
    }

    public function signature(Request $request, string $dailyHeaderId): JsonResponse
    {
        $this->allow($request, 'mutabaah.parent.sign');
        $data = $request->validate(['signature_status' => ['required', Rule::in(['approved', 'clarification_requested', 'unable_to_verify'])], 'comment' => ['nullable', 'string', 'max:1000'], 'pin' => ['nullable', 'string', 'max:20'], 'device_info' => ['nullable', 'array'], 'device_info.platform' => ['nullable', 'string', 'max:100'], 'device_info.app' => ['nullable', 'string', 'max:100']]);

        return $this->ok('Paraf orang tua berhasil disimpan.', $this->service->sign($request->user(), $dailyHeaderId, $data, $request));
    }

    public function studentOverview(Request $request): JsonResponse
    {
        $this->allow($request, 'mutabaah.daily.view');
        $data = $request->validate(['date' => ['nullable', 'date']]);

        return $this->ok('Mutaba’ah siswa berhasil dimuat.', $this->service->overview($this->service->ownStudent($request->user()), $data));
    }

    public function studentHistory(Request $request): JsonResponse
    {
        $this->allow($request, 'mutabaah.daily.view');

        return $this->ok('Riwayat siswa berhasil dimuat.', $this->service->history($this->service->ownStudent($request->user()), $this->historyFilters($request)));
    }

    private function historyFilters(Request $request): array
    {
        return $request->validate(['from' => ['nullable', 'date'], 'until' => ['nullable', 'date', 'after_or_equal:from'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:60']]);
    }

    private function ok(string $message, mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data, 'meta' => (object) []]);
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasRole('Super Admin') || $request->user()->can($permission), 403);
    }
}
