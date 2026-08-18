<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reports\SdmReportService;
use App\Services\Reports\StudentReportService;
use App\Services\Reports\MutationReportService;
use App\Services\Reports\GraduationReportService;
use App\Services\Reports\AlumniReportService;
use App\Services\Reports\CrossUnitReportService;
use App\Services\Reports\AchievementReportService;
use App\Exports\FoundationReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FoundationReportController extends Controller
{
    protected SdmReportService $sdmService;
    protected StudentReportService $studentService;
    protected MutationReportService $mutationService;
    protected GraduationReportService $graduationService;
    protected AlumniReportService $alumniService;
    protected CrossUnitReportService $crossUnitService;
    protected AchievementReportService $achievementService;

    public function __construct(
        SdmReportService $sdmService,
        StudentReportService $studentService,
        MutationReportService $mutationService,
        GraduationReportService $graduationService,
        AlumniReportService $alumniService,
        CrossUnitReportService $crossUnitService,
        AchievementReportService $achievementService
    ) {
        $this->sdmService = $sdmService;
        $this->studentService = $studentService;
        $this->mutationService = $mutationService;
        $this->graduationService = $graduationService;
        $this->alumniService = $alumniService;
        $this->crossUnitService = $crossUnitService;
        $this->achievementService = $achievementService;
    }

    public function sdm(Request $request): JsonResponse
    {
        $data = $this->sdmService->getReport($request->all());
        return response()->json([
            'status' => 'success',
            'message' => 'Laporan SDM berhasil dimuat.',
            'data' => $data,
        ]);
    }

    public function sdmDetail(string $id): JsonResponse
    {
        $detail = $this->sdmService->getDetail($id);
        return response()->json([
            'status' => 'success',
            'data' => $detail,
        ]);
    }

    public function siswa(Request $request): JsonResponse
    {
        $data = $this->studentService->getReport($request->all());
        return response()->json([
            'status' => 'success',
            'message' => 'Laporan Siswa berhasil dimuat.',
            'data' => $data,
        ]);
    }

    public function siswaDetail(string $id): JsonResponse
    {
        $detail = $this->studentService->getDetail($id);
        return response()->json([
            'status' => 'success',
            'data' => $detail,
        ]);
    }

    public function mutasi(Request $request): JsonResponse
    {
        $data = $this->mutationService->getReport($request->all());
        return response()->json([
            'status' => 'success',
            'message' => 'Laporan Mutasi berhasil dimuat.',
            'data' => $data,
        ]);
    }

    public function mutasiDetail(string $id): JsonResponse
    {
        $detail = $this->mutationService->getDetail($id);
        return response()->json([
            'status' => 'success',
            'data' => $detail,
        ]);
    }

    public function kelulusan(Request $request): JsonResponse
    {
        $data = $this->graduationService->getReport($request->all());
        return response()->json([
            'status' => 'success',
            'message' => 'Laporan Kelulusan berhasil dimuat.',
            'data' => $data,
        ]);
    }

    public function kelulusanDetail(string $id): JsonResponse
    {
        $detail = $this->graduationService->getDetail($id);
        return response()->json([
            'status' => 'success',
            'data' => $detail,
        ]);
    }

    public function alumni(Request $request): JsonResponse
    {
        $data = $this->alumniService->getReport($request->all());
        return response()->json([
            'status' => 'success',
            'message' => 'Laporan Alumni berhasil dimuat.',
            'data' => $data,
        ]);
    }

    public function alumniDetail(string $id): JsonResponse
    {
        $detail = $this->alumniService->getDetail($id);
        return response()->json([
            'status' => 'success',
            'data' => $detail,
        ]);
    }

    public function lintasUnit(Request $request): JsonResponse
    {
        $data = $this->crossUnitService->getReport($request->all());
        return response()->json([
            'status' => 'success',
            'message' => 'Laporan Lintas Unit berhasil dimuat.',
            'data' => $data,
        ]);
    }

    public function prestasi(Request $request): JsonResponse
    {
        $data = $this->achievementService->getReport($request->all());
        return response()->json([
            'status' => 'success',
            'message' => 'Laporan Rekapitulasi Prestasi Siswa berhasil dimuat.',
            'data' => $data,
        ]);
    }

    public function prestasiDetail(string $id): JsonResponse
    {
        $detail = $this->achievementService->getDetail($id);
        return response()->json([
            'status' => 'success',
            'data' => $detail,
        ]);
    }

    public function export(Request $request, string $type)
    {
        $format = strtolower($request->query('format', 'excel'));
        $orientation = strtolower($request->query('orientation', 'landscape'));

        $reportData = match ($type) {
            'sdm' => $this->sdmService->getReport($request->all()),
            'siswa' => $this->studentService->getReport($request->all()),
            'mutasi' => $this->mutationService->getReport($request->all()),
            'kelulusan' => $this->graduationService->getReport($request->all()),
            'alumni' => $this->alumniService->getReport($request->all()),
            'lintas-unit' => $this->crossUnitService->getReport($request->all()),
            'prestasi' => $this->achievementService->getReport($request->all()),
            default => throw new \InvalidArgumentException("Jenis laporan '{$type}' tidak valid."),
        };

        $filename = "laporan-{$type}-" . date('Y-m-d');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView("reports.pdf.generic", [
                'type' => $type,
                'data' => $reportData,
                'title' => $reportData['report']['title'] ?? 'Laporan',
                'period' => $reportData['report']['period']['label'] ?? 'Tahun Ini',
                'user' => $request->user()?->name ?? 'Pengurus Yayasan',
            ])->setPaper('a4', $orientation);

            return $pdf->download("{$filename}.pdf");
        }

        // Default Excel Export
        return Excel::download(new FoundationReportExport($type, $reportData), "{$filename}.xlsx");
    }
}
