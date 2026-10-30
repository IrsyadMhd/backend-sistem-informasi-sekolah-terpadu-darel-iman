<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class MutabaahAnalyticsService
{
    public function __construct(private readonly MutabaahDataScope $dataScope) {}

    public function dashboard(array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $headers = $this->headers($filters, $from, $to);
        $today = $to->copy()->min(Carbon::today())->toDateString();
        $todayHeaders = $this->headers($filters, Carbon::parse($today), Carbon::parse($today));
        $studentQuery = Student::query()->where('is_active', true);
        if (($filters['_user'] ?? null) instanceof User) {
            $this->dataScope->applyStudents($studentQuery, $filters['_user'], $to->toDateString());
        }
        $targetKelasId = $filters['kelas_id'] ?? $filters['class_id'] ?? null;
        $studentCount = $studentQuery
            ->when($filters['education_unit_id'] ?? null, fn ($q, $id) => $q->where('unit_id', $id))
            ->when($targetKelasId, fn ($q, $id) => $q->byClass($id))->count();
        $todayStats = (clone $todayHeaders)->selectRaw("
            COUNT(*) total,
            COUNT(*) FILTER (WHERE h.status IN ('finalized','parent_reviewed','parent_signed')) finalized,
            COUNT(*) FILTER (WHERE EXISTS (SELECT 1 FROM mutabaah_parent_signatures ps WHERE ps.daily_header_id = h.id)) signed
        ")->first();
        $filled = (int) ($todayStats->total ?? 0);

        return [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'kpis' => [
                'total_students' => $studentCount, 'filled' => $filled, 'not_filled' => max(0, $studentCount - $filled),
                'finalized' => (int) ($todayStats->finalized ?? 0), 'parent_signed' => (int) ($todayStats->signed ?? 0),
                'not_signed' => max(0, (int) ($todayStats->finalized ?? 0) - (int) ($todayStats->signed ?? 0)),
                'active_supervisors' => $this->activeSupervisors($filters, $to),
                'active_templates' => $this->activeTemplates($filters, $to),
            ],
            'charts' => [
                'weekly_progress' => $this->weeklyProgress($headers),
                'target_realization' => $this->targetRealization($headers),
                'status_distribution' => $this->statusDistribution($headers),
                'unit_ranking' => $this->ranking($headers, 'u.id', 'u.name'),
                'class_ranking' => $this->ranking($headers, 'c.id', 'c.name'),
                'supervisor_ranking' => $this->ranking($headers, 'e.id', 'e.nama_lengkap'),
                'signature_trend' => $this->signatureTrend($headers),
            ],
            'widgets' => [
                'today_agendas' => DB::table('mutabaah_template_items as ti')->join('mutabaah_templates as t', 't.id', '=', 'ti.template_id')->where('ti.is_active', true)->whereNull('t.deleted_at')->where('t.status', 'active')->count(),
                'not_filled' => max(0, $studentCount - $filled),
                'follow_up' => (clone $headers)->where('h.status', 'follow_up')->count(),
                'waiting_signature' => max(0, (int) ($todayStats->finalized ?? 0) - (int) ($todayStats->signed ?? 0)),
                'notifications' => DB::table('mutabaah_activity_logs as l')->leftJoin('users as us', 'us.id', '=', 'l.user_id')
                    ->latest('l.created_at')->limit(5)->get(['l.id', 'l.event', 'l.created_at', 'us.name as user_name']),
            ],
        ];
    }

    public function recap(array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $headers = $this->headers($filters, $from, $to);
        $hasAgendaFilter = ! empty($filters['agenda_item_id']);

        if (! $hasAgendaFilter) {
            $summary = (clone $headers)
                ->selectRaw("COALESCE(SUM(h.total_items), 0) total,
                    COALESCE(SUM(h.good_count), 0) good,
                    COALESCE(SUM(h.less_count), 0) less,
                    COALESCE(SUM(h.not_done_count), 0) not_done,
                    COALESCE(SUM(h.na_count), 0) na")->first();
        } else {
            $summary = (clone $headers)->join('mutabaah_daily_details as d', 'd.daily_header_id', '=', 'h.id')
                ->where('d.agenda_item_id', $filters['agenda_item_id'])
                ->selectRaw("COUNT(*) total,
                    COUNT(*) FILTER (WHERE d.status_value='good') good,
                    COUNT(*) FILTER (WHERE d.status_value='less') less,
                    COUNT(*) FILTER (WHERE d.status_value='not_done') not_done,
                    COUNT(*) FILTER (WHERE d.status_value='na') na")->first();
        }
        $total = max(1, (int) ($summary->total ?? 0));
        $finalHeaders = (clone $headers)->whereIn('h.status', ['finalized', 'parent_reviewed', 'parent_signed'])->count();
        $signedHeaders = (clone $headers)->where('h.status', 'parent_signed')->count();

        // Mulai dari master siswa agar siswa yang belum memiliki input Mutabaah
        // pada periode ini tetap muncul di rekap dengan nilai nol.
        $eligibleStudents = Student::query()->where('is_active', true);
        if (($filters['_user'] ?? null) instanceof User) {
            $this->dataScope->applyStudents($eligibleStudents, $filters['_user'], $to->toDateString());
        }
        $targetKelasId = $filters['kelas_id'] ?? $filters['class_id'] ?? null;
        $eligibleStudents
            ->when($filters['education_unit_id'] ?? null, fn ($q, $id) => $q->where(fn ($student) => $student
                ->where('unit_id', $id)
                ->orWhereHas('kelas', fn ($kelas) => $kelas->where('unit_pendidikan_id', $id))))
            ->when($targetKelasId, fn ($q, $id) => $q->byClass($id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($x) => $x
                ->where('full_name', 'ilike', "%{$search}%")
                ->orWhere('nis', 'ilike', "%{$search}%")));

        $studentQuery = DB::table('students as s')
            ->leftJoin('classes as c', 'c.id', '=', 's.class_id')
            ->leftJoin('tbl_kelas as tk', 'tk.id', '=', 's.kelas_id')
            ->leftJoin('education_units as u', function ($join) {
                $join->on('u.id', '=', DB::raw('COALESCE(s.unit_id, tk.unit_pendidikan_id)'));
            })
            ->leftJoin('mutabaah_daily_headers as h', function ($join) use ($filters, $from, $to) {
                $join->on('h.student_id', '=', 's.id')
                    ->whereNull('h.deleted_at')
                    ->whereBetween('h.activity_date', [$from->toDateString(), $to->toDateString()]);
                foreach (['academic_year_id', 'semester_id', 'rombel_id', 'supervisor_assignment_id'] as $column) {
                    if (! empty($filters[$column])) {
                        $join->where("h.{$column}", $filters[$column]);
                    }
                }
            })
            ->whereIn('s.id', $eligibleStudents->select('students.id'))
            ->when(($filters['signature_status'] ?? '') === 'signed', fn ($q) => $q->where('h.status', 'parent_signed'))
            ->when(($filters['signature_status'] ?? '') === 'unsigned', fn ($q) => $q->where('h.status', '!=', 'parent_signed'));

        if (! $hasAgendaFilter) {
            $studentRows = $studentQuery
                ->selectRaw("s.id, s.full_name, s.nis, COALESCE(tk.nama_kelas, c.name, '-') class_name, COALESCE(u.name, 'Unit belum ditentukan') unit_name,
                    COALESCE(SUM(h.good_count), 0) good,
                    COALESCE(SUM(h.less_count), 0) less,
                    COALESCE(SUM(h.not_done_count), 0) not_done,
                    COALESCE(SUM(h.na_count), 0) na,
                    COALESCE(ROUND(AVG(h.score),2), 0) progress,
                    CASE WHEN COUNT(DISTINCT h.id) > 0
                        AND MIN(CASE WHEN h.status IN ('finalized','parent_reviewed','parent_signed') THEN 1 ELSE 0 END) = 1
                        THEN 1 ELSE 0 END finalized,
                    COUNT(DISTINCT CASE WHEN h.status = 'parent_signed' THEN h.id END) > 0 parent_signed")
                ->groupBy('s.id', 's.full_name', 's.nis', 'tk.nama_kelas', 'c.name', 'u.name')
                ->orderBy($filters['sort'] ?? 's.full_name', ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc')
                ->paginate(min((int) ($filters['per_page'] ?? 15), 100));
        } else {
            $studentRows = $studentQuery
                ->leftJoin('mutabaah_daily_details as d', function ($join) use ($filters) {
                    $join->on('d.daily_header_id', '=', 'h.id')
                        ->where('d.agenda_item_id', $filters['agenda_item_id']);
                })
                ->selectRaw("s.id, s.full_name, s.nis, COALESCE(tk.nama_kelas, c.name, '-') class_name, COALESCE(u.name, 'Unit belum ditentukan') unit_name,
                    COUNT(DISTINCT d.id) FILTER (WHERE d.status_value='good') good,
                    COUNT(DISTINCT d.id) FILTER (WHERE d.status_value='less') less,
                    COUNT(DISTINCT d.id) FILTER (WHERE d.status_value='not_done') not_done,
                    COUNT(DISTINCT d.id) FILTER (WHERE d.status_value='na') na,
                    COALESCE(ROUND(AVG(h.score),2), 0) progress,
                    CASE WHEN COUNT(DISTINCT h.id) > 0
                        AND MIN(CASE WHEN h.status IN ('finalized','parent_reviewed','parent_signed') THEN 1 ELSE 0 END) = 1
                        THEN 1 ELSE 0 END finalized,
                    COUNT(DISTINCT CASE WHEN h.status = 'parent_signed' THEN h.id END) > 0 parent_signed")
                ->groupBy('s.id', 's.full_name', 's.nis', 'tk.nama_kelas', 'c.name', 'u.name')
                ->orderBy($filters['sort'] ?? 's.full_name', ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc')
                ->paginate(min((int) ($filters['per_page'] ?? 15), 100));
        }

        return [
            'summary' => [
                'good' => round(((int) ($summary->good ?? 0) / $total) * 100, 1),
                'less' => round(((int) ($summary->less ?? 0) / $total) * 100, 1),
                'not_done' => round(((int) ($summary->not_done ?? 0) / $total) * 100, 1),
                'na' => round(((int) ($summary->na ?? 0) / $total) * 100, 1),
                'parent_signature' => $finalHeaders ? round(($signedHeaders / $finalHeaders) * 100, 1) : 0,
            ],
            'heatmap' => (clone $headers)->selectRaw('h.activity_date date, ROUND(AVG(h.score),2) value, COUNT(*) total')->groupBy('h.activity_date')->orderBy('h.activity_date')->get(),
            'drilldown' => [
                'units' => $this->ranking($headers, 'u.id', 'u.name', 50),
                'classes' => $this->ranking($headers, 'c.id', 'c.name', 100),
            ],
            'rows' => $studentRows,
        ];
    }

    public function exportRows(array $filters)
    {
        $filters['per_page'] = 10000;

        return collect($this->recap($filters)['rows']->items())->map(fn ($row) => [
            'nama_siswa' => $row->full_name, 'nis' => $row->nis, 'unit' => $row->unit_name,
            'kelas' => $row->class_name, 'baik' => $row->good, 'kurang' => $row->less,
            'belum' => $row->not_done, 'na' => $row->na, 'progress' => $row->progress,
            'finalisasi' => $row->finalized ? 'Final' : 'Draft', 'paraf_orang_tua' => $row->parent_signed ? 'Sudah' : 'Belum',
        ]);
    }

    private function headers(array $filters, Carbon $from, Carbon $to): Builder
    {
        $query = DB::table('mutabaah_daily_headers as h')
            ->whereNull('h.deleted_at')
            ->whereBetween('h.activity_date', [$from->toDateString(), $to->toDateString()]);

        if (! empty($filters['education_unit_id'])) {
            $query->where('h.education_unit_id', $filters['education_unit_id']);
        }
        if (! empty($filters['academic_year_id'])) {
            $query->where('h.academic_year_id', $filters['academic_year_id']);
        }
        if (! empty($filters['semester_id'])) {
            $query->where('h.semester_id', $filters['semester_id']);
        }
        if (! empty($filters['rombel_id'])) {
            $query->where('h.rombel_id', $filters['rombel_id']);
        }
        if (! empty($filters['supervisor_assignment_id'])) {
            $query->where('h.supervisor_assignment_id', $filters['supervisor_assignment_id']);
        }
        if (! empty($filters['kelas_id']) || ! empty($filters['class_id'])) {
            $kid = $filters['kelas_id'] ?? $filters['class_id'];
            $query->where(fn ($sub) => $sub->where('h.kelas_id', $kid)->orWhere('h.rombel_id', $kid));
        }

        if (! empty($filters['education_level'])) {
            $query->join('classes as c', 'c.id', '=', 'h.kelas_id')
                ->where('c.level', $filters['education_level']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->join('students as s', 's.id', '=', 'h.student_id')
                ->where(fn ($x) => $x->where('s.full_name', 'ilike', "%{$search}%")->orWhere('s.nis', 'ilike', "%{$search}%"));
        }

        if (($filters['_user'] ?? null) instanceof User) {
            $this->dataScope->applyHeaders($query, $filters['_user']);
        }

        return $query;
    }

    private function weeklyProgress(Builder $headers)
    {
        return (clone $headers)->selectRaw('h.activity_date date, ROUND(AVG(h.score),2) progress, COUNT(*) filled')->groupBy('h.activity_date')->orderBy('h.activity_date')->get();
    }

    private function targetRealization(Builder $headers)
    {
        $headerSub = (clone $headers)->select('h.id')->orderByDesc('h.activity_date')->limit(1000);

        return DB::table('mutabaah_daily_details as d')
            ->joinSub($headerSub, 'sub_h', 'sub_h.id', '=', 'd.daily_header_id')
            ->join('mutabaah_agenda_items as a', 'a.id', '=', 'd.agenda_item_id')
            ->selectRaw("a.id, a.name, ROUND(AVG(a.weight),2) target, ROUND(AVG(CASE d.status_value WHEN 'good' THEN 100 WHEN 'less' THEN 50 WHEN 'not_done' THEN 0 ELSE NULL END),2) realization")
            ->groupBy('a.id', 'a.name')
            ->orderByDesc('realization')
            ->limit(8)
            ->get();
    }

    private function statusDistribution(Builder $headers)
    {
        $agg = (clone $headers)->selectRaw('
            COALESCE(SUM(h.good_count), 0) as good,
            COALESCE(SUM(h.less_count), 0) as less,
            COALESCE(SUM(h.not_done_count), 0) as not_done,
            COALESCE(SUM(h.na_count), 0) as na
        ')->first();

        return collect([
            (object) ['status' => 'good', 'total' => (int) ($agg->good ?? 0)],
            (object) ['status' => 'less', 'total' => (int) ($agg->less ?? 0)],
            (object) ['status' => 'not_done', 'total' => (int) ($agg->not_done ?? 0)],
            (object) ['status' => 'na', 'total' => (int) ($agg->na ?? 0)],
        ]);
    }

    private function ranking(Builder $headers, string $id, string $name, int $limit = 10)
    {
        $q = clone $headers;
        if (str_starts_with($id, 'u.')) {
            $q->join('education_units as u', 'u.id', '=', 'h.education_unit_id');
        } elseif (str_starts_with($id, 'c.')) {
            $q->join('classes as c', 'c.id', '=', 'h.kelas_id');
        } elseif (str_starts_with($id, 'e.')) {
            $q->join('mutabaah_supervisor_assignments as sa', 'sa.id', '=', 'h.supervisor_assignment_id')
              ->join('employees as e', 'e.id', '=', 'sa.employee_id');
        }

        return $q->whereNotNull($id)->selectRaw("{$id} id, {$name} name, ROUND(AVG(h.score),2) score, COUNT(*) total")->groupBy($id, $name)->orderByDesc('score')->limit($limit)->get();
    }

    private function signatureTrend(Builder $headers)
    {
        return (clone $headers)->selectRaw('h.activity_date as date, COUNT(*) as finalized, COUNT(CASE WHEN h.status = \'parent_signed\' THEN 1 END) as signed')->whereIn('h.status', ['finalized', 'parent_reviewed', 'parent_signed'])->groupBy('h.activity_date')->orderBy('h.activity_date')->get();
    }

    private function activeSupervisors(array $filters, Carbon $to): int
    {
        $query = DB::table('mutabaah_supervisor_assignments')->whereNull('deleted_at')->where('status', 'active')->whereDate('start_date', '<=', $to)->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $to))->when($filters['education_unit_id'] ?? null, fn ($q, $id) => $q->where('education_unit_id', $id));
        if (($filters['_user'] ?? null) instanceof User && ! $this->dataScope->isFoundationWide($filters['_user'])) {
            $unitId = $this->dataScope->employeeUnitId($filters['_user']);
            $query->where('education_unit_id', $unitId);
        }

return $query->distinct('employee_id')->count('employee_id');
    }

    private function activeTemplates(array $filters, Carbon $to): int
    {
        $query = DB::table('mutabaah_templates')->whereNull('deleted_at')->where('status', 'active')->whereDate('start_date', '<=', $to)->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $to))->when($filters['education_unit_id'] ?? null, fn ($q, $id) => $q->where('education_unit_id', $id));
        if (($filters['_user'] ?? null) instanceof User && ! $this->dataScope->isFoundationWide($filters['_user'])) {
            $query->where('education_unit_id', $this->dataScope->employeeUnitId($filters['_user']));
        }

return $query->count();
    }

    private function period(array $filters): array
    {
        $to = Carbon::parse($filters['date_to'] ?? now())->endOfDay();
        $from = Carbon::parse($filters['date_from'] ?? $to->copy()->startOfMonth())->startOfDay();
        abort_if($from->gt($to), 422, 'Periode tidak valid.');

        return [$from, $to];
    }
}
