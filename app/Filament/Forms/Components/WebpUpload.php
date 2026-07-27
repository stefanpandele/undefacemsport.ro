<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * A file upload that crops and downsizes images in the browser, then stores
 * them as WebP. Keeps galleries and avatars small whatever the user uploads,
 * without depending on a server-side image pipeline.
 */
class WebpUpload extends FileUpload
{
    /**
     * WebP quality: visually lossless for photos at roughly a third of the
     * size of the equivalent JPEG.
     */
    protected const QUALITY = 82;

    protected function setUp(): void
    {
        parent::setUp();

        $this->image()
            ->imageEditor()
            ->saveUploadedFileUsing(fn (UploadedFile $file): ?string => $this->storeAsWebp($file));
    }

    /**
     * Crop and resize to a square of $size pixels, in the browser, before the
     * file is uploaded. Smaller images are left at their own size.
     */
    public function square(int $size): static
    {
        $this->imageAspectRatio('1:1');
        $this->imageEditorAspectRatios(['1:1']);
        $this->automaticallyCropImagesToAspectRatio();
        $this->automaticallyResizeImagesMode('cover');
        $this->automaticallyResizeImagesToWidth((string) $size);
        $this->automaticallyResizeImagesToHeight((string) $size);
        $this->automaticallyUpscaleImagesWhenResizing(false);

        return $this;
    }

    /**
     * Convert the upload to WebP and store it. Anything GD cannot read (an
     * SVG, a corrupt file) is stored untouched so the upload never fails here.
     */
    public function storeAsWebp(UploadedFile $file): ?string
    {
        $contents = $this->toWebp($file);

        if ($contents === null) {
            return $file->storeAs($this->getDirectory(), $file->hashName(), $this->getDiskName()) ?: null;
        }

        $path = trim($this->getDirectory().'/'.Str::uuid()->toString().'.webp', '/');

        $this->getDisk()->put($path, $contents);

        // R2 rejects per-object ACLs, so treat visibility as best-effort —
        // the same way Filament's own upload handler does.
        if ($this->getVisibility() === 'public') {
            rescue(fn () => $this->getDisk()->setVisibility($path, 'public'), report: false);
        }

        return $path;
    }

    protected function toWebp(UploadedFile $file): ?string
    {
        $image = @imagecreatefromstring((string) $file->get());

        if ($image === false) {
            return null;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $converted = imagewebp($image, null, static::QUALITY);
        $contents = (string) ob_get_clean();

        imagedestroy($image);

        return $converted ? $contents : null;
    }
}
