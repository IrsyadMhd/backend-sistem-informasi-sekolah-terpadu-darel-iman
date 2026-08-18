<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\MutabaahCrudExport;
use App\Http\Controllers\Controller;
use App\Services\MutabaahAnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MutabaahAnalyticsController extends Controller
{
    public function __construct(private MutabaahAnalyticsService $service) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'mutabaah.dashboard.view');

        return $this->ok('Dashboard berhasil dimuat.', $this->service->dashboard($this->filters($request) + ['_user' => $request->user()]));
    }

    public function recap(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'mutabaah.recap.view');

        return $this->ok('Rekap berhasil dimuat.', $this->service->recap($this->filters($request) + ['_user' => $request->user()]));
    }

    public function export(Request $request)
    {
        $this->authorizePermission($request, 'mutabaah.report.export');
        $filters = $this->filters($request) + ['_user' => $request->user()];
        $rows = $this->service->exportRows($filters);
        $columns = ['nama_siswa', 'nis', 'unit', 'kelas', 'baik', 'kurang', 'belum', 'na', 'progress', 'finalisasi', 'paraf_orang_tua'];
        $filename = 'rekap-mutabaah-'.now()->format('Ymd-His');
        if ($request->string('format')->toString() === 'pdf') {
            return Pdf::loadView('exports.mutabaah-crud', ['title' => 'Rekap Mutaba’ah', 'rows' => $rows, 'headings' => $columns])->setPaper('a4', 'landscape')->download($filename.'.pdf');
        }

        return Excel::download(new MutabaahCrudExport($rows, $columns), $filename.'.xlsx');
    }

    private function filters(Request $request): array
    {
        return $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'education_unit_id' => ['nullable', 'uuid'], 'education_level' => ['nullable', 'string', 'max:30'], 'academic_year_id' => ['nullable', 'uuid'], 'semester_id' => ['nullable', 'uuid'], 'kelas_id' => ['nullable', 'uuid'], 'rombel_id' => ['nullable', 'uuid'], 'supervisor_assignment_id' => ['nullable', 'uuid'], 'agenda_item_id' => ['nullable', 'uuid'], 'signature_status' => ['nullable', 'in:signed,unsigned'], 'search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'sort' => ['nullable', 'in:s.full_name,c.name,progress'], 'direction' => ['nullable', 'in:asc,desc']]);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user();
        $hasRoleAccess = $user->hasRole([
            'Super Admin', 'Admin', 'Yayasan', 'Kepala Sekolah', 'Divisi Pendidikan',
            'Waka Kesiswaan', 'Waka Kurikulum', 'Tata Usaha', 'TU', 'Operator',
            'Wali Kelas', 'Guru', 'Musyrif', 'Musyrifah', 'Pembimbing', 'Pengurus Yayasan',
        ]);

        abort_unless($hasRoleAccess || $user->can($permission), 403);
    }

    private function ok(string $message, mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data, 'meta' => (object) []]);
    }
}
