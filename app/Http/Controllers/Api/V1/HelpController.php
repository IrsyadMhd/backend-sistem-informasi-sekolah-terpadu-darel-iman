<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpFaq;
use App\Models\HelpTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HelpController extends Controller
{
    /**
     * Dapatkan daftar FAQ dan kategori dari database.
     */
    public function faqs(Request $request): JsonResponse
    {
        $query = HelpFaq::where('is_active', true)->orderBy('sort_order');

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', $search)
                  ->orWhere('answer', 'like', $search);
            });
        }

        $faqs = $query->get();

        $categories = HelpFaq::where('is_active', true)
            ->select('category')
            ->selectRaw('count(*) as count')
            ->groupBy('category')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'faqs' => $faqs,
                'categories' => $categories,
                'total' => $faqs->count(),
            ],
        ]);
    }

    /**
     * Berikan feedback/voting pada panduan FAQ (up / down).
     */
    public function voteFaq(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'vote' => 'required|in:up,down',
        ]);

        $faq = HelpFaq::findOrFail($id);

        if ($request->vote === 'up') {
            $faq->increment('helpful_votes');
        } else {
            $faq->increment('unhelpful_votes');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Feedback berhasil disimpan.',
            'data' => [
                'helpful_votes' => $faq->helpful_votes,
                'unhelpful_votes' => $faq->unhelpful_votes,
            ],
        ]);
    }

    /**
     * Buat tiket bantuan baru dari formulir helpdesk.
     */
    public function submitTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'role' => 'nullable|string|max:50',
            'category' => 'required|string|max:100',
            'priority' => 'nullable|string|in:Rendah,Sedang,Tinggi,Kritis',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        $ticketNumber = 'TKT-' . date('Ymd') . '-' . strtoupper(Str::random(5));

        $ticket = HelpTicket::create([
            'ticket_number' => $ticketNumber,
            'user_id' => $request->user()?->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'] ?? 'Pengguna',
            'category' => $validated['category'],
            'priority' => $validated['priority'] ?? 'Sedang',
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'status' => 'open',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tiket bantuan berhasil dikirimkan.',
            'data' => [
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at,
            ],
        ], 201);
    }

    /**
     * Daftar dokumen buku panduan sistem.
     */
    public function manuals(): JsonResponse
    {
        $manuals = [
            [
                'id' => 'man-1',
                'title' => 'Panduan Operasional Guru & Modul LMS',
                'category' => 'Akademik & LMS',
                'file_size' => '2.4 MB',
                'format' => 'PDF',
                'download_url' => '/api/help/manuals/guru-lms.pdf',
            ],
            [
                'id' => 'man-2',
                'title' => 'Buku Petunjuk Presensi Tap Gerbang & NFC',
                'category' => 'Absensi Digital',
                'file_size' => '1.8 MB',
                'format' => 'PDF',
                'download_url' => '/api/help/manuals/absensi-gerbang.pdf',
            ],
            [
                'id' => 'man-3',
                'title' => 'Pedoman Penilaian & Setoran Tahfizh Al-Quran',
                'category' => 'Tahfizh & Mutabaah',
                'file_size' => '3.1 MB',
                'format' => 'PDF',
                'download_url' => '/api/help/manuals/tahfizh-pedoman.pdf',
            ],
            [
                'id' => 'man-4',
                'title' => 'Manual Pengelolaan Hak Akses & Tata Usaha',
                'category' => 'SDM & Kepegawaian',
                'file_size' => '1.5 MB',
                'format' => 'PDF',
                'download_url' => '/api/help/manuals/tu-hak-akses.pdf',
            ],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $manuals,
        ]);
    }
}
