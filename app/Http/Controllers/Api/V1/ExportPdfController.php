<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExportPdfController extends Controller
{
    /**
     * Generate and download generic A4 PDF table document using DomPDF.
     */
    public function export(Request $request): Response
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'period' => ['nullable', 'string', 'max:255'],
            'headers' => ['required', 'array'],
            'rows' => ['required', 'array'],
            'filename' => ['nullable', 'string', 'max:255'],
            'orientation' => ['nullable', 'string', 'in:portrait,landscape'],
        ]);

        $title = $request->input('title');
        $subtitle = $request->input('subtitle', '');
        $period = $request->input('period', '');
        $headers = $request->input('headers', []);
        $rows = $request->input('rows', []);
        $orientation = $request->input('orientation') ?: (count($headers) > 7 ? 'landscape' : 'portrait');

        $filename = $request->input('filename') ?: 'laporan_' . date('Ymd_His') . '.pdf';
        if (! str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        // Retrieve institutional identity directly from database (Zero Hardcode)
        $setting = SiteSetting::current();
        $schoolName = $setting->school_name ?: 'YAYASAN PENDIDIKAN';
        $address = $setting->address ?: $setting->footer_text ?: '';
        $phone = $setting->phone ?: '';
        $skPendirian = $setting->sk_pendirian ?: '';
        $motto = $setting->motto ?: '';
        $logoText = $setting->logo_text ?: 'SIT';

        $pdf = Pdf::loadView('exports.generic_table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'period' => $period,
            'headers' => $headers,
            'rows' => $rows,
            'schoolName' => $schoolName,
            'address' => $address,
            'phone' => $phone,
            'skPendirian' => $skPendirian,
            'motto' => $motto,
            'logoText' => $logoText,
            'printDate' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', $orientation);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
