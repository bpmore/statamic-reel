<?php

declare(strict_types=1);

namespace Bpmore\Reel\Media;

/**
 * The filename convention for a caption track kept beside its video.
 *
 * `talk.mp4` is matched by `talk.vtt` and `talk.en.vtt`, and the code
 * between the dots is the language. Pure string work, so it is tested
 * without a disk.
 */
final class Sidecar
{
    /**
     * @param  array<int, string>  $patterns  with `{name}` for the media file's name without extension
     */
    public function __construct(private readonly array $patterns) {}

    /**
     * The candidate paths among `$files`, best first: an exact `{name}.vtt`
     * before a language-coded one, and language codes in alphabetical
     * order after that, so the answer does not depend on disk order.
     *
     * @param  string  $mediaPath  the media file's path inside its container
     * @param  iterable<int, string>  $files  paths in the same container
     * @return array<int, string>
     */
    public function matches(string $mediaPath, iterable $files): array
    {
        $folder = self::folder($mediaPath);
        $name = pathinfo($mediaPath, PATHINFO_FILENAME);
        $globs = array_map(fn (string $p) => str_replace('{name}', $name, $p), $this->patterns);

        $found = [];

        foreach ($files as $file) {
            $file = (string) $file;

            if (self::folder($file) !== $folder || $file === $mediaPath) {
                continue;
            }

            $basename = basename($file);

            foreach ($globs as $rank => $glob) {
                if (fnmatch($glob, $basename)) {
                    $found[] = [$rank, $basename, $file];

                    break;
                }
            }
        }

        usort($found, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_values(array_map(fn ($f) => $f[2], $found));
    }

    /**
     * The language code in `talk.en.vtt`, or null for `talk.vtt`. Only a
     * short alphabetic code with an optional region (`pt-BR`) counts, so a
     * file called `talk.final.vtt` is not tagged as language "final".
     */
    public static function language(string $trackPath, string $mediaPath): ?string
    {
        $name = preg_quote(pathinfo($mediaPath, PATHINFO_FILENAME), '/');

        if (preg_match('/^'.$name.'\.([a-z]{2,3}(?:-[A-Za-z]{2,4})?)\.vtt$/i', basename($trackPath), $m)) {
            return $m[1];
        }

        return null;
    }

    private static function folder(string $path): string
    {
        $dir = dirname($path);

        return $dir === '.' ? '' : ltrim($dir, '/');
    }
}
