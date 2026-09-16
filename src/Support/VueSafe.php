<?php

declare(strict_types=1);

namespace Bpmore\Reel\Support;

/**
 * Text for a view that Vue will compile.
 *
 * A utility view is not printed as HTML: Statamic hands the rendered markup
 * to `DynamicHtmlRenderer`, which does `defineComponent({ template: html })`.
 * So Blade's escaping is only half the job. A value containing `{{`, which
 * a caption cue can, would be read by Vue as an interpolation and the whole
 * page would fail to compile, blank, with no exception on the server. Vue
 * finds its delimiters in the raw source before it decodes entities, so a
 * brace written as an entity is safe.
 */
final class VueSafe
{
    public static function text(mixed $value): string
    {
        return str_replace(['{', '}'], ['&#123;', '&#125;'], e((string) $value));
    }
}
