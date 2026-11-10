<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class PersonPhotos
{
    public function exists(?string $path): bool
    {
        return $this->validPath($path) && $this->operation(fn () => Storage::disk($this->disk())->exists($path));
    }

    public function response(?string $path, bool $verification = false): StreamedResponse
    {
        abort_unless($this->validPath($path), 404);
        $disk = Storage::disk($this->disk());
        abort_unless($this->operation(fn () => $disk->exists($path)), 404);
        $stream = $this->operation(fn () => $disk->readStream($path));
        abort_unless(is_resource($stream), 503, 'The photograph is temporarily unavailable.');

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, ['Content-Type' => 'image/jpeg', 'Content-Disposition' => 'inline; filename="'.($verification ? 'verification-photo.jpg' : 'person-photo.jpg').'"', 'Cache-Control' => 'no-store, private']);
    }

    public function validPath(?string $path): bool
    {
        return is_string($path) && preg_match('#^person-photos/[a-f0-9-]{36}\.jpg$#D', $path) === 1;
    }

    private function disk(): string
    {
        $disk = config('nfcheck.photo_disk', 'local');
        if (! in_array($disk, ['local', 's3'], true)) {
            throw new \LogicException('Person photographs require a private local or S3 disk.');
        }

        return $disk;
    }

    private function operation(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (FilesystemException) {
            // Provider exceptions can contain signed requests or private object keys.
            throw new ServiceUnavailableHttpException(5, 'Private photo storage is temporarily unavailable.');
        }
    }

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
                if (! $this->operation(fn () => Storage::disk($this->disk())->put($path, $encoded, ['visibility' => 'private', 'ContentType' => 'image/jpeg', 'CacheControl' => 'private, no-store']))) {
                    throw new \RuntimeException('The photograph could not be stored.');
                }

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
        if ($this->validPath($path)) {
            $this->operation(fn () => Storage::disk($this->disk())->delete($path));
        }
    }
}
