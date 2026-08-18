<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\MutabaahCrudExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\MutabaahCrudRequest;
use App\Http\Resources\V1\MutabaahResource;
use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\JenisUnitPendidikan;
use App\Models\Kelas;
use App\Models\MutabaahActivityLog;
use App\Models\MutabaahAgendaItem;
use App\Models\MutabaahCategory;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplate;
use App\Models\MutabaahTemplateItem;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Services\MutabaahDataScope;
use App\Services\MutabaahEnterpriseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MutabaahEnterpriseController extends Controller
{
    private const EXPORT_COLUMNS = [
        'categories' => ['code', 'name', 'icon', 'color', 'sort_order', 'is_active', 'description'],
        'agendas' => ['code', 'name', 'category', 'input_type', 'target', 'weight', 'level', 'sort_order', 'is_active', 'description'],
        'templates' => ['code', 'name', 'education_unit_id', 'education_level', 'academic_year_id', 'semester_id', 'start_date', 'end_date', 'status', 'description'],
        'template-assignments' => ['template_id', 'education_unit_id', 'education_level', 'kelas_id', 'rombel_id', 'student_id', 'start_date', 'end_date', 'priority', 'status'],
        'supervisor-assignments' => ['employee_id', 'supervisor_type', 'education_unit_id', 'kelas_id', 'rombel_id', 'dormitory_id', 'room_id', 'mentoring_group', 'template_id', 'start_date', 'end_date', 'status'],
    ];

    public function __construct(private readonly MutabaahEnterpriseService $service, private readonly MutabaahDataScope $dataScope) {}

    public function index(Request $request, string $resource): JsonResponse
    {
        $this->authorizeAction($request, $resource, 'view');
        $page = $this->service->paginate($resource, $request->all() + ['_user' => $request->user()]);

        return response()->json(['success' => true, 'message' => 'Data berhasil dimuat.', 'data' => MutabaahResource::collection($page->items()), 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
        ]]);
    }

    public function show(Request $request, string $resource, string $id): JsonResponse
    {
        $this->authorizeAction($request, $resource, 'view');
        $model = $this->scopedModel($request, $resource, $id, true);

        return response()->json(['success' => true, 'message' => 'Detail berhasil dimuat.', 'data' => new MutabaahResource($model), 'meta' => (object) []]);
    }

    public function store(MutabaahCrudRequest $request, string $resource): JsonResponse
    {
        $this->authorizeAction($request, $resource, 'create');
        $this->assertPayloadScope($request, $resource, $request->validated());

        return response()->json(['success' => true, 'message' => 'Data berhasil ditambahkan.', 'data' => new MutabaahResource($this->service->create($resource, $request->validated())), 'meta' => (object) []], 201);
    }

    public function update(MutabaahCrudRequest $request, string $resource, string $id): JsonResponse
    {
        $this->authorizeAction($request, $resource, 'update');
        $this->scopedModel($request, $resource, $id);
        $this->assertPayloadScope($request, $resource, $request->validated());

        return response()->json(['success' => true, 'message' => 'Data berhasil diperbarui.', 'data' => new MutabaahResource($this->service->update($resource, $id, $request->validated())), 'meta' => (object) []]);
    }

    public function destroy(Request $request, string $resource, string $id): JsonResponse
    {
        $this->authorizeAction($request, $resource, 'delete');
        $this->scopedModel($request, $resource, $id);
        $this->service->delete($resource, $id);

        return response()->json(['success' => true, 'message' => 'Data dipindahkan ke sampah dan tetap dipertahankan untuk riwayat transaksi.', 'data' => null, 'meta' => (object) []]);
    }

    public function restore(Request $request, string $resource, string $id): JsonResponse
    {
        $this->authorizeAction($request, $resource, 'restore');
        $this->scopedModel($request, $resource, $id, true);

        return response()->json(['success' => true, 'message' => 'Data berhasil dipulihkan.', 'data' => new MutabaahResource($this->service->restore($resource, $id)), 'meta' => (object) []]);
    }

    public function bulkDelete(Request $request, string $resource): JsonResponse
    {
        $this->authorizeAction($request, $resource, 'delete');
        $data = $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['uuid']]);
        foreach ($data['ids'] as $id) {
            $this->scopedModel($request, $resource, $id);
        }

return response()->json(['success' => true, 'message' => $this->service->bulkDelete($resource, $data['ids']).' data dipindahkan ke sampah.', 'data' => null, 'meta' => (object) []]);
    }

    public function bulkRestore(Request $request, string $resource): JsonResponse
    {
        $this->authorizeAction($request, $resource, 'restore');
        $data = $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['uuid']]);
        foreach ($data['ids'] as $id) {
            $this->scopedModel($request, $resource, $id, true);
        }

return response()->json(['success' => true, 'message' => $this->service->bulkRestore($resource, $data['ids']).' data dipulihkan.', 'data' => null, 'meta' => (object) []]);
    }

    public function forceDelete(Request $request, string $resource, string $id): JsonResponse
    {
        abort_unless($request->user()->hasRole('Super Admin'), 403, 'Hanya Super Admin yang dapat menghapus permanen.');
        $this->scopedModel($request, $resource, $id, true);
        $this->service->forceDelete($resource, $id);

        return response()->json(['success' => true, 'message' => 'Data berhasil dihapus permanen.', 'data' => null, 'meta' => (object) []]);
    }

    public function storeTemplateItem(MutabaahCrudRequest $request, string $id): JsonResponse
    {
        $this->scopedModel($request, 'templates', $id);

        return response()->json(['success' => true, 'message' => 'Item template ditambahkan.', 'data' => new MutabaahResource($this->service->addTemplateItem($id, $request->validated())), 'meta' => (object) []], 201);
    }

    public function updateTemplateItem(MutabaahCrudRequest $request, string $id): JsonResponse
    {
        $item = MutabaahTemplateItem::with('template')->findOrFail($id);
        $this->dataScope->assertEnterpriseModel($request->user(), $item->template);

        return response()->json(['success' => true, 'message' => 'Item template diperbarui.', 'data' => new MutabaahResource($this->service->updateTemplateItem($id, $request->validated())), 'meta' => (object) []]);
    }

    public function destroyTemplateItem(Request $request, string $id): JsonResponse
    {
        $this->authorizeAction($request, 'templates', 'update');
        $item = MutabaahTemplateItem::with('template')->findOrFail($id);
        $this->dataScope->assertEnterpriseModel($request->user(), $item->template);
        $this->service->deleteTemplateItem($id);

        return response()->json(['success' => true, 'message' => 'Item template dihapus.', 'data' => null, 'meta' => (object) []]);
    }

    public function reorderTemplate(Request $request, string $id): JsonResponse
    {
        $this->authorizeAction($request, 'templates', 'update');
        $this->scopedModel($request, 'templates', $id);
        $data = $request->validate(['items' => ['required', 'array'], 'items.*.id' => ['required', 'uuid'], 'items.*.sort_order' => ['required', 'integer', 'min:0']]);
        $this->service->reorderTemplate($id, $data['items']);

        return response()->json(['success' => true, 'message' => 'Urutan item template diperbarui.', 'data' => null, 'meta' => (object) []]);
    }

    public function export(Request $request, string $resource)
    {
        abort_unless($request->user()->hasRole('Super Admin') || $request->user()->can('mutabaah.report.export'), 403);
        abort_unless(isset(self::EXPORT_COLUMNS[$resource]), 404);
        $format = $request->string('format', 'xlsx')->lower()->toString();
        $page = $this->service->paginate($resource, $request->all() + ['per_page' => 100, '_user' => $request->user()]);
        $rows = collect($page->items());
        $headings = self::EXPORT_COLUMNS[$resource];
        $filename = 'mutabaah-'.$resource.'-'.now()->format('Ymd-His');
        if ($format === 'pdf') {
            return Pdf::loadView('exports.mutabaah-crud', ['title' => str($resource)->replace('-', ' ')->title(), 'rows' => $rows, 'headings' => $headings])->setPaper('a4', 'landscape')->download($filename.'.pdf');
        }
        if ($format === 'csv') {
            return Excel::download(new MutabaahCrudExport($rows, $headings), $filename.'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new MutabaahCrudExport($rows, $headings), $filename.'.xlsx');
    }

    public function import(Request $request, string $resource): JsonResponse
    {
        $this->authorizeAction($request, $resource, 'create');
        abort_unless(isset(self::EXPORT_COLUMNS[$resource]), 404);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120']]);
        $sheet = Excel::toCollection(null, $request->file('file'))->first();
        abort_if(! $sheet || $sheet->count() < 2, 422, 'File tidak memiliki data.');
        $headings = $sheet->shift()->map(fn ($value) => str($value)->trim()->snake()->value())->all();
        $created = 0;
        $errors = [];
        foreach ($sheet as $index => $row) {
            try {
                $payload = array_filter(array_combine($headings, $row->all()), fn ($value) => $value !== null && $value !== '');
                validator($payload, (new MutabaahCrudRequest)->setRouteResolver(fn () => $request->route())->rules())->validate();
                $this->service->create($resource, $payload);
                $created++;
            } catch (\Throwable $error) {
                $errors[] = ['row' => $index + 2, 'message' => $error->getMessage()];
            }
        }

        return response()->json(['message' => "{$created} data berhasil diimpor.", 'data' => ['created' => $created, 'errors' => $errors]]);
    }

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $hasRoleAccess = $user->hasRole([
            'Super Admin', 'Admin', 'Yayasan', 'Kepala Sekolah', 'Divisi Pendidikan',
            'Waka Kesiswaan', 'Waka Kurikulum', 'Tata Usaha', 'TU', 'Operator',
            'Wali Kelas', 'Guru', 'Musyrif', 'Musyrifah', 'Pembimbing', 'Pengurus Yayasan',
        ]);
        $hasPermission = collect(['mutabaah.category.view', 'mutabaah.agenda.view', 'mutabaah.template.view', 'mutabaah.supervisor.view', 'mutabaah.daily.input'])->contains(fn ($permission) => $user->can($permission));

        abort_unless($hasRoleAccess || $hasPermission, 403);
        $wide = $this->dataScope->isFoundationWide($user);
        $unitId = $wide ? null : $this->dataScope->employeeUnitId($user);

        return response()->json(['data' => [
            'categories' => MutabaahCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name']),
            'agendas' => MutabaahAgendaItem::active()->orderBy('sort_order')->get(['id', 'code', 'name']),
            'templates' => MutabaahTemplate::active()->when($unitId, fn ($q, $id) => $q->where('education_unit_id', $id))->orderBy('name')->get(['id', 'code', 'name']),
            'units' => EducationUnit::where('is_active', true)->when($unitId, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'name', 'jenis_unit_id']),
            'jenis_units' => JenisUnitPendidikan::where('status', true)->orderBy('urutan')->get(['uuid', 'singkatan', 'nama_jenis']),
            'employees' => Employee::where('status', 'Aktif')->when($unitId, fn ($q, $id) => $q->where('unit_id', $id))->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'unit_id']),
            'students' => Student::active()->when($unitId, fn ($q, $id) => $q->where('unit_id', $id))->orderBy('full_name')->get(['id', 'full_name', 'nis', 'unit_id', 'class_id']),
            'supervisor_assignments' => MutabaahSupervisorAssignment::active()->when($unitId, fn ($q, $id) => $q->where('education_unit_id', $id))->with('employee:id,nama_lengkap')->get()->map(fn ($assignment) => [
                'id' => $assignment->id,
                'name' => $assignment->employee?->nama_lengkap.' · '.str($assignment->supervisor_type->value)->replace('_', ' ')->title(),
            ]),
            'classes' => Kelas::where('status', 'Aktif')->when($unitId, fn ($q, $id) => $q->where('unit_pendidikan_id', $id))->orderBy('nama_kelas')->get(['id', 'nama_kelas', 'unit_pendidikan_id', 'jenjang']),
            'school_classes' => SchoolClass::when($unitId, fn ($q, $id) => $q->whereIn('id', Student::active()->where('unit_id', $id)->whereNotNull('class_id')->select('class_id')))->orderBy('name')->get(['id', 'name', 'level', 'academic_year_id', 'semester_id']),
            'semesters' => Semester::where('is_active', true)->get(['id', 'name', 'academic_year_id']),
            'academic_years' => AcademicYear::orderByDesc('start_date')->get(['id', 'name', 'is_active']),
        ]]);
    }

    public function audit(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole(['Super Admin', 'Admin', 'Yayasan', 'Kepala Sekolah', 'Tata Usaha']) || $request->user()->can('mutabaah.report.view'), 403);

        return response()->json(['data' => MutabaahActivityLog::with('user:id,name')->latest('created_at')->paginate(25)]);
    }

    private function authorizeAction(Request $request, string $resource, string $action): void
    {
        $permission = match ($resource) {
            'categories' => "mutabaah.category.{$action}",
            'agendas' => "mutabaah.agenda.{$action}",
            'templates' => 'mutabaah.template.'.($action === 'restore' ? 'update' : $action),
            'template-assignments' => $action === 'view' ? 'mutabaah.template.view' : 'mutabaah.template.assign',
            'supervisor-assignments' => 'mutabaah.supervisor.'.($action === 'restore' ? 'update' : $action),
            default => null,
        };

        $user = $request->user();
        $hasRoleAccess = $action === 'view' && $user->hasRole([
            'Super Admin', 'Admin', 'Yayasan', 'Kepala Sekolah', 'Divisi Pendidikan',
            'Waka Kesiswaan', 'Waka Kurikulum', 'Tata Usaha', 'TU', 'Operator',
            'Wali Kelas', 'Guru', 'Musyrif', 'Musyrifah', 'Pembimbing', 'Pengurus Yayasan',
        ]);

        abort_unless($hasRoleAccess || ($permission && ($user->hasRole('Super Admin') || $user->can($permission))), 403);
    }

    private function scopedModel(Request $request, string $resource, string $id, bool $trashed = false)
    {
        $model = $this->service->find($resource, $id, $trashed);
        $this->dataScope->assertEnterpriseModel($request->user(), $model);

        return $model;
    }

    private function assertPayloadScope(Request $request, string $resource, array $data): void
    {
        if ($this->dataScope->isFoundationWide($request->user()) || in_array($resource, ['categories', 'agendas'], true)) {
            return;
        }
        abort_if(empty($data['education_unit_id']), 422, 'Unit pendidikan wajib diisi sesuai kewenangan pengguna.');
        abort_unless($this->dataScope->employeeUnitId($request->user()) === $data['education_unit_id'], 403, 'Tidak dapat menyimpan data untuk unit lain.');
    }
}
