<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PortalMessageAttachment extends Model
{
    protected $table = 'portal_message_attachments';

    protected $fillable = [
        'message_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'file_size',
        'width',
        'height',
    ];

    protected $appends = ['url', 'file_type', 'formatted_size'];

    public function message()
    {
        return $this->belongsTo(PortalMessage::class, 'message_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk ?? 'public')->url($this->path);
    }

    public function getFileTypeAttribute(): string
    {
        $mime = strtolower((string) $this->mime_type);
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if ($mime === 'application/pdf') {
            return 'pdf';
        }
        if (str_contains($mime, 'word') || str_contains($mime, 'document')) {
            return 'doc';
        }
        if (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet') || str_contains($mime, 'sheet')) {
            return 'sheet';
        }
        return 'file';
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 0) . ' KB';
        }
        return $bytes . ' B';
    }
}
