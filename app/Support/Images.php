<?php

namespace App\Support;

use App\Import\Importer;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\UploadedFile;

/**
 * Upload de imagini din admin: doar PNG / JPG / WebP / GIF (fără SVG, care poate conține JavaScript),
 * redimensionate și salvate WebP pe discul public.
 */
final class Images
{
    public const MIME = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    public static function fromUpload(UploadedFile $file, string $dir, string $field, int $maxW, int $maxH): string
    {
        $raw = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($raw);
        $img = $info && in_array($info['mime'], self::MIME, true) ? @imagecreatefromstring($raw) : false;
        if (! $img) {
            throw ValidationException::withMessages([$field => 'Fișierul trebuie să fie o imagine PNG, JPG, WebP sau GIF.']);
        }

        return Importer::saveWebp($img, $dir.'/'.Str::lower(Str::random(16)), $maxW, $maxH);
    }
}
