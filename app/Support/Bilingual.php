<?php

namespace App\Support;

/**
 * The server-side twin of Alpine's $store.lang.t(en, fr).
 *
 * Pages that switch language in the browser put their text in x-text, which
 * leaves the HTML empty until JavaScript runs. Rendering the same pair here as
 * the element's content means a reader that doesn't run scripts (App Review's
 * fetch of the privacy policy, search engines, link previews) still gets the
 * words. Once Alpine starts, x-text takes over exactly as before.
 */
final class Bilingual
{
    public static function t(string $en, string $fr = ''): string
    {
        return app()->getLocale() === 'fr' ? ($fr !== '' ? $fr : $en) : $en;
    }
}
