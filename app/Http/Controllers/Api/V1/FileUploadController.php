<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $file = $request->file('file')
            ?? $request->file('photo')
            ?? $request->file('foto')
            ?? $request->file('image');

        if (! $file || ! $file->isValid()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Berkas tidak ditemukan atau tidak valid.',
            ], 422);
        }

        $folder = $request->input('folder', 'students/photos');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = 'upload_' . Str::uuid()->toString() . '.' . $extension;

        $path = $file->storeAs($folder, $filename, 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Berkas berhasil diunggah.',
            'path' => $path,
            'url' => $url,
            'data' => [
                'path' => $path,
                'url' => $url,
                'filename' => $filename,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ],
        ], 200);
    }
}
