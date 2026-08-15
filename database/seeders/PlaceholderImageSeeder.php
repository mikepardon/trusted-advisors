<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AppIcon;
use App\Models\Character;
use App\Models\Curse;
use App\Models\DiceTheme;
use App\Models\KingdomStyle;
use App\Models\MediaLibraryItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Local dev helper: drops a generated placeholder image at every image path the
 * database already references, so records whose real images live on a now-
 * unreachable S3/Minio don't render as broken icons. Safe to re-run — it only
 * writes paths that are missing on the media disk.
 *
 * Run: php artisan db:seed --class=PlaceholderImageSeeder
 */
class PlaceholderImageSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk(config('filesystems.media_disk'));
        $placeholder = $this->makePlaceholderPng();

        $paths = collect()
            ->merge(Character::query()->pluck('image_path'))
            ->merge(Curse::query()->pluck('image_path'))
            ->merge(KingdomStyle::query()->pluck('background_image_path'))
            ->merge(DiceTheme::query()->pluck('preview_image'))
            ->merge(MediaLibraryItem::query()->pluck('path'))
            ->merge($this->appIconPaths())
            ->filter(static fn ($path): bool => is_string($path)
                && ! str_starts_with($path, 'http')
                && str_contains($path, '/'))
            ->unique();

        $created = 0;
        foreach ($paths as $path) {
            if (! $disk->exists($path)) {
                $disk->put($path, $placeholder);
                $created++;
            }
        }

        $this->command->info("Placeholder images written: {$created} (disk: " . config('filesystems.media_disk') . ')');
    }

    /**
     * AppIcon stores a file path in icon_value only for file-backed icon types
     * (emoji icons hold the emoji character itself).
     */
    private function appIconPaths(): Collection
    {
        return AppIcon::query()
            ->where('icon_type', '!=', 'emoji')
            ->pluck('icon_value');
    }

    /**
     * A dark, gold-bordered placeholder tile with a "?" glyph — matches the app
     * theme and reads clearly as "missing image".
     */
    private function makePlaceholderPng(): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            // Minimal 1x1 grey PNG fallback if GD is unavailable.
            return base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
                true
            ) ?: '';
        }

        $size = 240;
        $image = imagecreatetruecolor($size, $size);
        $background = imagecolorallocate($image, 26, 34, 52);
        $gold = imagecolorallocate($image, 212, 168, 67);

        imagefilledrectangle($image, 0, 0, $size, $size, $background);
        imagesetthickness($image, 4);
        imagerectangle($image, 6, 6, $size - 7, $size - 7, $gold);

        $glyph = '?';
        $font = 5;
        $textWidth = imagefontwidth($font) * mb_strlen($glyph);
        $textHeight = imagefontheight($font);
        imagestring($image, $font, (int) (($size - $textWidth) / 2), (int) (($size - $textHeight) / 2), $glyph, $gold);

        ob_start();
        imagepng($image);
        $data = ob_get_clean();
        imagedestroy($image);

        return $data === false ? '' : $data;
    }
}
