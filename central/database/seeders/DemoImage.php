<?php

namespace Database\Seeders;

use App\Enums\SchoolTheme;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Placeholder pictures for the demo data, drawn with GD so the repository
 * holds no image files and seeding needs no network. Each picture is a simple
 * landscape (sky gradient, sun, hills) in colors that depend on the seed.
 */
class DemoImage
{
    /**
     * Width of generated photos; tests lower it to keep seeding fast.
     */
    public static int $width = 1600;

    /**
     * Sky gradients: top and bottom color.
     *
     * @var list<array{0: string, 1: string}>
     */
    protected const SKIES = [
        ['#2f6fb0', '#a9d3f5'],
        ['#e0703a', '#f7d38c'],
        ['#5b4b9a', '#f0a6a0'],
        ['#1d7f8c', '#b9ecdf'],
        ['#3a5a98', '#f4e3b5'],
    ];

    /**
     * Theme colors used for the hills and the logo.
     *
     * @var array<string, array{primary: string, accent: string}>
     */
    protected const THEME_COLORS = [
        'blue' => ['primary' => '#1e3a6b', 'accent' => '#a35200'],
        'green' => ['primary' => '#22543d', 'accent' => '#8a4b08'],
        'red' => ['primary' => '#7a1f2b', 'accent' => '#1f5f7a'],
    ];

    /**
     * Draw a landscape JPEG and return the path of a temporary file. The
     * media library moves the file into storage.
     */
    public static function photo(SchoolTheme $theme, int $seed, bool $wide = false): string
    {
        $random = new Randomizer(new Mt19937($seed));
        $width = self::$width;
        $height = (int) round($width * ($wide ? 9 / 16 : 3 / 4));
        $image = imagecreatetruecolor($width, $height);

        [$top, $bottom] = self::SKIES[$random->getInt(0, count(self::SKIES) - 1)];
        self::gradient($image, self::rgb($top), self::rgb($bottom));

        // Sun.
        $sun = imagecolorallocatealpha($image, 255, 244, 200, 20);
        $radius = (int) ($height * $random->getInt(10, 18) / 100);
        imagefilledellipse($image, (int) ($width * $random->getInt(15, 85) / 100), (int) ($height * $random->getInt(18, 40) / 100), $radius * 2, $radius * 2, $sun);

        // Three layers of hills, darkest in front.
        $colors = self::THEME_COLORS[$theme->value];
        $base = self::rgb($random->getInt(0, 2) === 0 ? $colors['accent'] : $colors['primary']);

        foreach ([0.55, 0.68, 0.8] as $layer => $horizon) {
            $shade = 0.45 + 0.25 * (2 - $layer);
            $color = imagecolorallocate($image, ...array_map(fn (int $channel): int => (int) min(255, $channel + (255 - $channel) * $shade * 0.6), $base));

            for ($hill = 0; $hill < 4; $hill++) {
                $hillWidth = (int) ($width * $random->getInt(45, 90) / 100);
                imagefilledellipse(
                    $image,
                    (int) ($width * $random->getInt(-10, 110) / 100),
                    (int) ($height * $horizon) + (int) ($height * 0.35),
                    $hillWidth,
                    (int) ($height * 0.75),
                    $color,
                );
            }
        }

        $path = self::temporaryPath('jpg');
        imagejpeg($image, $path, 88);
        imagedestroy($image);

        return $path;
    }

    /**
     * Draw a square PNG logo (a house in a circle) in the theme's colors.
     */
    public static function logo(SchoolTheme $theme): string
    {
        $size = 600;
        $image = imagecreatetruecolor($size, $size);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        $colors = self::THEME_COLORS[$theme->value];
        $primary = imagecolorallocate($image, ...self::rgb($colors['primary']));
        $accent = imagecolorallocate($image, ...self::rgb($colors['accent']));
        $white = imagecolorallocate($image, 255, 255, 255);

        imagefilledellipse($image, 300, 300, 590, 590, $primary);
        imagefilledpolygon($image, [130, 300, 300, 140, 470, 300], $accent);
        imagefilledrectangle($image, 170, 300, 430, 460, $white);
        imagefilledrectangle($image, 270, 360, 330, 460, $primary);
        imagefilledrectangle($image, 195, 330, 245, 380, $accent);
        imagefilledrectangle($image, 355, 330, 405, 380, $accent);

        $path = self::temporaryPath('png');
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $from
     * @param  array{0: int, 1: int, 2: int}  $to
     */
    protected static function gradient(\GdImage $image, array $from, array $to): void
    {
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            $t = $y / max(1, $height - 1);
            $color = imagecolorallocate(
                $image,
                (int) ($from[0] + ($to[0] - $from[0]) * $t),
                (int) ($from[1] + ($to[1] - $from[1]) * $t),
                (int) ($from[2] + ($to[2] - $from[2]) * $t),
            );
            imageline($image, 0, $y, imagesx($image) - 1, $y, $color);
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected static function rgb(string $hex): array
    {
        return array_map(fn (string $pair): int => (int) hexdec($pair), str_split(ltrim($hex, '#'), 2));
    }

    protected static function temporaryPath(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'demo-');
        $path = $base.'.'.$extension;
        rename($base, $path);

        return $path;
    }
}
