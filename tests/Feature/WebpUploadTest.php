<?php

use App\Filament\Forms\Components\WebpUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function webpUpload(string $directory = 'club-sports/gallery'): WebpUpload
{
    return WebpUpload::make('path')
        ->disk('s3')
        ->directory($directory);
}

test('an uploaded image is converted to webp', function () {
    Storage::fake('s3');

    $path = webpUpload()->storeAsWebp(UploadedFile::fake()->image('poza.jpg', 400, 400));

    expect($path)->toEndWith('.webp')
        ->and($path)->toStartWith('club-sports/gallery/')
        ->and(Storage::disk('s3')->exists($path))->toBeTrue();

    $stored = Storage::disk('s3')->get($path);

    expect(substr($stored, 0, 4))->toBe('RIFF')
        ->and(substr($stored, 8, 4))->toBe('WEBP');
});

test('a converted image keeps its dimensions', function () {
    Storage::fake('s3');

    $path = webpUpload()->storeAsWebp(UploadedFile::fake()->image('poza.png', 300, 200));

    $size = getimagesizefromstring(Storage::disk('s3')->get($path));

    expect($size[0])->toBe(300)
        ->and($size[1])->toBe(200);
});

test('a file GD cannot read is stored untouched', function () {
    Storage::fake('s3');

    $path = webpUpload('coaches')->storeAsWebp(UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'));

    expect($path)->toEndWith('.svg')
        ->and(Storage::disk('s3')->exists($path))->toBeTrue();
});

test('the gallery upload crops to a 1200px square in the browser', function () {
    $upload = webpUpload()->square(1200);

    expect($upload->getImageAspectRatio())->toBe('1:1')
        ->and($upload->getAutomaticallyResizeImagesWidth())->toBe('1200')
        ->and($upload->getAutomaticallyResizeImagesHeight())->toBe('1200')
        ->and($upload->getAutomaticallyResizeImagesMode())->toBe('cover')
        ->and($upload->shouldAutomaticallyUpscaleImagesWhenResizing())->toBeFalse();
});
