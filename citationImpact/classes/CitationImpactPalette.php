<?php

/**
 * @file plugins/generic/citationImpact/classes/CitationImpactPalette.php
 *
 * Distributed under the GNU GPL v3.
 *
 * @class CitationImpactPalette
 * @brief Finds the accent colour of the active theme and derives the page
 *  palette (soft tints, readable accent text, chart ramp) from it.
 *
 *  The same math runs again in js/impact.js against the colours the browser
 *  actually renders, so themes without a colour option and dark themes are
 *  handled there. Keep both in step.
 */

namespace APP\plugins\generic\citationImpact\classes;

use PKP\components\forms\FieldColor;
use PKP\plugins\PluginRegistry;
use PKP\plugins\ThemePlugin;
use Throwable;

class CitationImpactPalette
{
    /** Used when neither the plugin settings nor the theme give a colour. */
    public const FALLBACK = '#2a78d6';

    /**
     * @return string|null Normalised "#rrggbb", or null when not a hex colour.
     */
    public static function normalizeHex($value): ?string
    {
        $value = trim((string) $value);
        if (preg_match('/^#?([0-9a-f]{3})$/i', $value, $m)) {
            $h = $m[1];
            return strtolower('#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2]);
        }
        if (preg_match('/^#?([0-9a-f]{6})$/i', $value, $m)) {
            return strtolower('#' . $m[1]);
        }
        return null;
    }

    /**
     * The active theme of a context (or the site), or null.
     */
    public static function getActiveTheme($contextOrSite): ?ThemePlugin
    {
        if (!$contextOrSite) {
            return null;
        }
        $path = (string) $contextOrSite->getData('themePluginPath');
        $themes = PluginRegistry::getPlugins('themes');
        if (!$themes) {
            // Not loaded yet (e.g. in the backend): load enabled themes.
            $themes = PluginRegistry::loadCategory('themes', true);
        }
        foreach ($themes as $theme) {
            if ($theme instanceof ThemePlugin && $theme->getDirName() === $path) {
                return $theme;
            }
        }
        return null;
    }

    /**
     * The main colour chosen in the theme's options (Settings > Website >
     * Appearance), for any theme that offers a colour option. Options named
     * like a primary/brand colour win over others; background, text and
     * border colours are never used as the accent.
     *
     * @return string|null "#rrggbb"
     */
    public static function themeColour(?ThemePlugin $theme): ?string
    {
        if (!$theme) {
            return null;
        }
        try {
            $best = null;
            $bestScore = -1;
            foreach ($theme->getOptionsConfig() as $name => $option) {
                if (!$option instanceof FieldColor) {
                    continue;
                }
                $value = self::normalizeHex($theme->getOption($name));
                if ($value === null) {
                    continue;
                }
                $n = strtolower((string) $name);
                $score = 1;
                if (preg_match('/primary|base|brand|accent|main|theme|header|nav|link/', $n)) {
                    $score = 3;
                } elseif (preg_match('/background|bg|text|font|footer|border/', $n)) {
                    // Not an accent; the browser-side detection does better.
                    continue;
                }
                if ($score > $bestScore) {
                    $best = $value;
                    $bestScore = $score;
                }
            }
            return $best;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * True for a colour with a visible hue (not black, white or grey).
     */
    public static function isColourful(string $hex): bool
    {
        $c = self::rgb($hex);
        return max($c) - min($c) >= 40;
    }

    //
    // Colour math (sRGB, WCAG relative luminance)
    //

    protected static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    protected static function hex(array $c): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, (int) round($c[0]))), max(0, min(255, (int) round($c[1]))), max(0, min(255, (int) round($c[2]))));
    }

    /**
     * @param float $w Weight of $a (1 = only $a).
     */
    public static function mix(string $a, string $b, float $w): string
    {
        $x = self::rgb($a);
        $y = self::rgb($b);
        return self::hex([$x[0] * $w + $y[0] * (1 - $w), $x[1] * $w + $y[1] * (1 - $w), $x[2] * $w + $y[2] * (1 - $w)]);
    }

    public static function luminance(string $hex): float
    {
        $l = array_map(function ($v) {
            $v /= 255;
            return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        }, self::rgb($hex));
        return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Push $c away from the surface until it reaches $ratio against $bg.
     */
    protected static function ensure(string $c, string $bg, float $ratio, string $away): string
    {
        for ($i = 0; $i < 12 && self::contrast($c, $bg) < $ratio; $i++) {
            $c = self::mix($c, $away, 0.85);
        }
        return $c;
    }

    /**
     * Accent-derived CSS custom properties.
     *
     * @param string $accent "#rrggbb"
     * @param string $surface Card background, "#rrggbb".
     *
     * @return array name => colour
     */
    public static function accentVars(string $accent, string $surface = '#ffffff'): array
    {
        $dark = self::luminance($surface) < 0.2;
        $away = $dark ? '#ffffff' : '#000000';
        $a = self::ensure($accent, $surface, 3, $away);
        $soft = self::mix($a, $surface, 0.12);
        $vars = [
            '--ci-accent' => $a,
            '--ci-accent-soft' => $soft,
            '--ci-accent-softer' => self::mix($a, $surface, 0.06),
            '--ci-accent-line' => self::mix($a, $surface, 0.35),
            '--ci-accent-ink' => self::ensure($a, $soft, 4.5, $away),
            '--ci-ring-track' => self::mix($a, $surface, 0.22),
        ];
        // Chart ramp, weak (near the surface) to strong (past the accent).
        for ($i = 0; $i <= 8; $i++) {
            $c = $i <= 4 ? self::mix($a, $surface, 0.45 + 0.55 * $i / 4) : self::mix($a, $away, 1 - 0.45 * ($i - 4) / 4);
            $vars['--ci-r' . $i] = $i === 0 ? self::ensure($c, $surface, 2, $away) : $c;
        }
        return $vars;
    }

    /**
     * @return string Inline style attribute value.
     */
    public static function style(array $vars): string
    {
        $out = [];
        foreach ($vars as $k => $v) {
            $out[] = $k . ':' . $v;
        }
        return implode(';', $out);
    }
}
