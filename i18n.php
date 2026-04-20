<?php
declare(strict_types=1);

/**
 * ==========================================
 * i18n SYSTEM - ENTERPRISE VERSION
 * ==========================================
 */

final class I18n
{
    private static array $cache = [];
    private static string $defaultLang = 'de';
    private static array $allowedLangs = [
        'de','en','fr','es','it','ru','zh','ja','ko','pt','pl','tr'
    ];

    /**
     * Init language from request/session/browser
     */
    public static function init(): string
    {
        $lang = $_GET['lang']
            ?? $_SESSION['lang']
            ?? self::getBrowserLang()
            ?? self::$defaultLang;

        $lang = self::sanitizeLang($lang);

        $_SESSION['lang'] = $lang;

        return $lang;
    }

    /**
     * Translate key
     */
    public static function t(string $key, ?string $lang = null): string
    {
        $lang = $lang ?? ($_SESSION['lang'] ?? self::$defaultLang);

        $translations = self::load($lang);

        return $translations[$key]
            ?? self::loadFallback($key);
    }

    /**
     * Load language file with caching
     */
    private static function load(string $lang): array
    {
        if (isset(self::$cache[$lang])) {
            return self::$cache[$lang];
        }

        $file = __DIR__ . "/locales/{$lang}.json";

        if (!is_file($file)) {
            self::$cache[$lang] = [];
            return [];
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (!is_array($data)) {
            $data = [];
        }

        self::$cache[$lang] = $data;
        return $data;
    }

    /**
     * Fallback translation (optional fallback file)
     */
    private static function loadFallback(string $key): string
    {
        $fallback = self::load(self::$defaultLang);
        return $fallback[$key] ?? $key;
    }

    /**
     * Safe language filter
     */
    private static function sanitizeLang(string $lang): string
    {
        $lang = strtolower(trim($lang));

        return in_array($lang, self::$allowedLangs, true)
            ? $lang
            : self::$defaultLang;
    }

    /**
     * Detect browser language
     */
    private static function getBrowserLang(): ?string
    {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return null;
        }

        $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);

        return $lang ?: null;
    }

    /**
     * Get current language
     */
    public static function getLang(): string
    {
        return $_SESSION['lang'] ?? self::$defaultLang;
    }

    /**
     * Clear cache (useful for admin panel)
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}