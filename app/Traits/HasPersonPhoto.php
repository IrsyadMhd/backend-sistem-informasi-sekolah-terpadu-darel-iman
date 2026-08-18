<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait HasPersonPhoto
{
    public function getPhotoUrlAttribute(): ?string
    {
        $candidates = array_filter([
            data_get($this->attributes, 'photo_url'),
            data_get($this->attributes, 'avatar_url'),
            data_get($this->attributes, 'profile_photo_url'),
            $this->getAttribute('photo'),
            $this->getAttribute('foto'),
            $this->getAttribute('avatar'),
            $this->getAttribute('profile_photo'),
            data_get($this->attributes, 'photo'),
            data_get($this->attributes, 'foto'),
            data_get($this->attributes, 'avatar'),
            data_get($this->attributes, 'profile_photo'),
            data_get($this->metadata, 'photo_url'),
            data_get($this->metadata, 'avatar_url'),
            data_get($this->metadata, 'profile_photo_url'),
            data_get($this->metadata, 'photo'),
            data_get($this->metadata, 'foto'),
            data_get($this->metadata, 'avatar'),
            data_get($this->metadata, 'profile_photo'),
        ], static fn ($value) => is_string($value) && trim($value) !== '');

        foreach ($candidates as $candidate) {
            $resolved = $this->resolvePublicMediaUrl($candidate);
            if ($resolved) {
                return $resolved;
            }
        }

        return null;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->getPhotoUrlAttribute();
    }

    protected function resolvePublicMediaUrl(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        $normalized = ltrim($value, '/');

        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        } elseif (str_starts_with($normalized, 'public/')) {
            $normalized = substr($normalized, strlen('public/'));
        }

        if ($normalized === '') {
            return null;
        }

        return Storage::disk('public')->url($normalized);
    }
}
