<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PersonPhotos
{
    public function store(UploadedFile $file): string
    {
        $bytes = file_get_contents($file->getRealPath());
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be decoded. Use a valid JPEG, PNG or WebP image.']);
        }
        try {
            $scale = min(1, 800 / max(imagesx($source), imagesy($source)));
            $width = max(1, (int) round(imagesx($source) * $scale));
            $height = max(1, (int) round(imagesy($source) * $scale));
            $image = imagecreatetruecolor($width, $height);
            try {
                imagefill($image, 0, 0, imagecolorallocate($image, 240, 243, 247));
                imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
                ob_start();
                try {
                    imagejpeg($image, null, 88);
                    $encoded = ob_get_contents();
                } finally {
                    ob_end_clean();
                }
                $path = 'person-photos/'.Str::uuid().'.jpg';
                Storage::disk('local')->put($path, $encoded);

                return $path;
            } finally {
                unset($image);
            }
        } finally {
            unset($source);
        }
    }

    public function remove(?string $path): void
    {
        if ($path !== null && preg_match('/^person-photos\/[a-f0-9-]{36}\.jpg$/D', $path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
