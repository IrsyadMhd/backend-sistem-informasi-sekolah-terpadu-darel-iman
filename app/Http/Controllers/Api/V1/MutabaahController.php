<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\JenisUnitPendidikan;
use App\Models\MutabaahAgenda;
use App\Models\MutabaahDailyNote;
use App\Models\MutabaahEntry;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MutabaahController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        $students = Student::query()
            ->with(['educationUnit.jenisUnit', 'schoolClass'])
            ->when($request->unit_id, fn ($q, $id) => $q->where('unit_id', $id))
            ->where('is_active', true)->orderBy('full_name')->get()
            ->map(fn ($student) => [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->full_name,
                'unit_id' => $student->unit_id,
                'unit_name' => $student->educationUnit?->name,
                'jenis_unit_id' => $student->educationUnit?->jenis_unit_id,
                'jenis_unit' => $student->educationUnit?->jenisUnit?->singkatan,
                'class_name' => $student->schoolClass?->name ?? data_get($student->metadata, 'kelas'),
                'photo' => data_get($student->metadata, 'photo'),
            ]);

        $mentors = Employee::query()->with('position')->where('status', 'Aktif')
            ->orderBy('nama_lengkap')->get()->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => $employee->nama_lengkap,
                'position' => $employee->position?->name,
                'unit_id' => $employee->unit_id,
                'photo' => $employee->foto,
            ]);

        return response()->json([
            'data' => [
                'students' => $students,
                'mentors' => $mentors,
                'jenis_units' => JenisUnitPendidikan::query()->where('status', true)->orderBy('urutan')->get(),
                'units' => EducationUnit::query()->where('is_active', true)->orderBy('name')->get(),
                'can_manage_agenda' => $this->canManageAgenda($request),
            ],
        ]);
    }

    public function agendas(Request $request): JsonResponse
    {
        $query = MutabaahAgenda::query()->with(['jenisUnit', 'unit'])
            ->when($request->jenis_unit_id, fn ($q, $id) => $q->where('jenis_unit_id', $id))
            ->when($request->unit_id, fn ($q, $id) => $q->where(fn ($scope) => $scope->whereNull('unit_id')->orWhere('unit_id', $id)))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('category')->orderBy('sort_order')->orderBy('name');

        return response()->json(['data' => $query->get()]);
    }

    public function storeAgenda(Request $request): JsonResponse
    {
        $this->ensureTu($request);
        $data = $this->validateAgenda($request);
        $agenda = MutabaahAgenda::create($data + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Rincian agenda berhasil ditambahkan.', 'data' => $agenda->load(['jenisUnit', 'unit'])], 201);
    }

    public function updateAgenda(Request $request, MutabaahAgenda $agenda): JsonResponse
    {
        $this->ensureTu($request);
        $agenda->update($this->validateAgenda($request) + ['updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Rincian agenda berhasil diperbarui.', 'data' => $agenda->fresh(['jenisUnit', 'unit'])]);
    }

    public function destroyAgenda(Request $request, MutabaahAgenda $agenda): JsonResponse
    {
        $this->ensureTu($request);
        if (MutabaahEntry::query()->where('agenda_id', $agenda->id)->exists()) {
            $agenda->update(['is_active' => false, 'updated_by' => $request->user()->id]);

            return response()->json(['message' => 'Agenda sudah memiliki riwayat sehingga dinonaktifkan, bukan dihapus.']);
        }
        $agenda->delete();

        return response()->json(['message' => 'Rincian agenda berhasil dihapus.']);
    }

    public function daily(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'date' => ['nullable', 'date'],
        ]);
        $date = Carbon::parse($validated['date'] ?? now())->toDateString();
        $student = Student::with(['educationUnit.jenisUnit', 'schoolClass'])->findOrFail($validated['student_id']);
        abort_unless($student->educationUnit?->jenis_unit_id, 422, 'Jenis unit siswa belum terhubung pada master unit pendidikan.');

        $agendas = MutabaahAgenda::query()
            ->where('jenis_unit_id', $student->educationUnit->jenis_unit_id)
            ->where(fn ($q) => $q->whereNull('unit_id')->orWhere('unit_id', $student->unit_id))
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $date))
            ->orderBy('category')->orderBy('sort_order')->get();

        $entries = MutabaahEntry::query()->with('mentor')->where('student_id', $student->id)
            ->whereDate('entry_date', $date)->get()->keyBy('agenda_id');
        $note = MutabaahDailyNote::query()->with('mentor')->where('student_id', $student->id)
            ->whereDate('entry_date', $date)->first();

        return response()->json(['data' => [
            'student' => $student,
            'date' => $date,
            'agendas' => $agendas,
            'entries' => $entries,
            'daily_note' => $note,
            'summary' => $entries->groupBy('status')->map->count(),
        ]]);
    }

    public function saveDaily(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'date' => ['required', 'date'],
            'mentor_id' => ['nullable', 'uuid', 'exists:employees,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'entries' => ['required', 'array'],
            'entries.*.agenda_id' => ['required', 'uuid', 'exists:mutabaah_agendas,id'],
            'entries.*.status' => ['required', Rule::in(['baik', 'kurang', 'belum', 'na'])],
        ]);
        $ownEmployeeId = Employee::query()->where('user_id', $request->user()->id)->value('id');
        $mentorId = $request->user()->hasRole('Super Admin')
            ? ($data['mentor_id'] ?? $ownEmployeeId)
            : $ownEmployeeId;
        abort_unless($mentorId, 422, 'Akun pembimbing belum terhubung ke data pegawai.');

        $student = Student::with('educationUnit')->findOrFail($data['student_id']);
        $agendaIds = collect($data['entries'])->pluck('agenda_id');
        $validAgendaIds = MutabaahAgenda::query()
            ->whereIn('id', $agendaIds)
            ->where('jenis_unit_id', $student->educationUnit?->jenis_unit_id)
            ->where(fn ($q) => $q->whereNull('unit_id')->orWhere('unit_id', $student->unit_id))
            ->pluck('id');
        abort_unless($validAgendaIds->count() === $agendaIds->unique()->count(), 422, 'Terdapat agenda yang tidak sesuai dengan jenis unit siswa.');

        DB::transaction(function () use ($data, $mentorId) {
            foreach ($data['entries'] as $entry) {
                MutabaahEntry::updateOrCreate(
                    ['student_id' => $data['student_id'], 'agenda_id' => $entry['agenda_id'], 'entry_date' => $data['date']],
                    ['status' => $entry['status'], 'mentor_id' => $mentorId]
                );
            }
            MutabaahDailyNote::updateOrCreate(
                ['student_id' => $data['student_id'], 'entry_date' => $data['date']],
                ['mentor_id' => $mentorId, 'note' => $data['note'] ?? null]
            );
        });

        return response()->json(['message' => 'Mutaba’ah harian berhasil disimpan.']);
    }

    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'from' => ['nullable', 'date'], 'until' => ['nullable', 'date'],
        ]);
        $entries = MutabaahEntry::query()->with(['agenda', 'mentor'])
            ->where('student_id', $data['student_id'])
            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('entry_date', '>=', $date))
            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('entry_date', '<=', $date))
            ->orderByDesc('entry_date')->get()->groupBy(fn ($entry) => $entry->entry_date->toDateString());

        return response()->json(['data' => $entries]);
    }

    private function validateAgenda(Request $request): array
    {
        return $request->validate([
            'jenis_unit_id' => ['required', 'uuid', 'exists:master_jenis_unit_pendidikan,uuid'],
            'unit_id' => ['nullable', 'uuid', 'exists:education_units,id'],
            'category' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
    }

    private function ensureTu(Request $request): void
    {
        abort_unless($this->canManageAgenda($request), 403, 'Hanya Tata Usaha yang dapat mengatur rincian agenda.');
    }

    private function canManageAgenda(Request $request): bool
    {
        $user = $request->user();
        if ($user->hasRole('Super Admin') || $user->can('mutabaah.agenda.manage')) {
            return true;
        }

        return Employee::query()->where('user_id', $user->id)
            ->whereHas('position', fn ($q) => $q
                ->whereRaw('LOWER(name) LIKE ?', ['%tata usaha%'])
                ->orWhere('code', 'JAB-005'))
            ->exists();
    }
}
