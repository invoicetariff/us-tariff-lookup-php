<?php

declare(strict_types=1);

namespace InvoiceTariff\UsTariffLookup;

/**
 * Offline US import-tariff reference data: HTS code search (English + Chinese),
 * the stacked ruleset overview and a machine-readable rule-change feed — the
 * same versioned snapshot that powers https://invoicetariff.com and the
 * us-tariff-lookup npm package.
 *
 * The data ships inside the package (data/*.json), so results are deterministic
 * and reproducible: every answer carries a ruleset version and an as-of date,
 * and nothing calls the network.
 *
 * v1 covers search / meta / changelog. The full landed-cost calculation
 * (stacked duty layers, specific/compound rates, MPF/HMF entry fees) lives in
 * the npm package (github.com/invoicetariff/us-tariff-lookup) and on the web
 * calculator; this package intentionally ships only the query surface.
 */
final class TariffLookup
{
    /**
     * The exact string to echo to end users of anything built on this package.
     */
    public const DISCLAIMER = 'Estimates for planning only — not legal advice. Many rate lines carry sample/unverified flags; your customs broker signs the entry, not this tool.';

    /** @var array<string,mixed>|null */
    private static $seed;

    /** @var array<string,mixed>|null */
    private static $changelog;

    /**
     * What's inside the bundled snapshot. Echo rulesetVersion/dataAsOf next to
     * any number derived from this package.
     *
     * @return array<string,mixed>
     */
    public static function meta(): array
    {
        $seed = self::seed();
        $changelog = self::changelogData();

        return [
            'rulesetVersion' => $seed['version'],
            'dataAsOf' => $seed['asOf'],
            'mfnCuratedCodes' => is_countable($seed['mfn']) ? count($seed['mfn']) : 0,
            'china301Lines' => isset($seed['china301']) && is_countable($seed['china301']) ? count($seed['china301']) : 0,
            'global301Tiers' => isset($seed['global301']['tiers']) && is_countable($seed['global301']['tiers']) ? count($seed['global301']['tiers']) : 0,
            'section232Measures' => isset($seed['section232']) && is_countable($seed['section232']) ? count($seed['section232']) : 0,
            'changelogEntries' => is_countable($changelog['entries']) ? count($changelog['entries']) : 0,
            'disclaimer' => self::DISCLAIMER,
            'homepage' => 'https://invoicetariff.com',
            'data' => [
                'ruleset' => 'https://github.com/invoicetariff/us-tariff-rates-2026',
                'fullHtsTable' => 'https://github.com/invoicetariff/hs-codes-database',
            ],
        ];
    }

    /**
     * Find codes by HTS prefix/substring, English description or Chinese
     * keyword. Multi-word queries use AND semantics: every whitespace-separated
     * token must match somewhere on the entry (code, description or 中文关键词).
     * Results keep the seed's curation order; each hit carries its entry plus
     * matchedBy ("code" | "en" | "zh"). $limit <= 0 defaults to 10.
     *
     * @return array<int,array{entry:array<string,mixed>,matchedBy:string}>
     */
    public static function search(string $query, int $limit = 10): array
    {
        $normalized = preg_replace('/\s+/u', ' ', strtolower(trim($query)));
        $normalized = $normalized === null ? '' : trim($normalized);
        if ($normalized === '') {
            return [];
        }
        if ($limit <= 0) {
            $limit = 10;
        }
        $tokens = explode(' ', $normalized);

        $hits = [];
        foreach (self::seed()['mfn'] as $entry) {
            $matched = self::classify($entry, $tokens);
            if ($matched === null) {
                continue;
            }
            $hits[] = ['entry' => $entry, 'matchedBy' => $matched];
            if (count($hits) === $limit) {
                break;
            }
        }

        return $hits;
    }

    /**
     * Rule-change entries, newest date first (published + upcoming +
     * historical). "published" entries are in force, "upcoming" entries have
     * an effective date in the future, "historical" entries are superseded
     * layers kept for refund lookups. $limit = null returns everything.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function changelog(?int $limit = null): array
    {
        $entries = self::sortedChangelog();
        if ($limit !== null) {
            $entries = array_slice($entries, 0, max($limit, 0));
        }

        return $entries;
    }

    /**
     * The full bundled ruleset JSON (fees, 232 measures, IEEPA history,
     * sources) for consumers that need layers this package does not model.
     */
    public static function rulesetSeedRaw(): string
    {
        return self::readDataFile('ruleset-seed.json');
    }

    /**
     * The full bundled changelog JSON.
     */
    public static function changelogRaw(): string
    {
        return self::readDataFile('changelog.json');
    }

    /**
     * @param array<string,mixed> $entry
     * @param array<int,string> $tokens
     *
     * @return string|null "code" | "en" | "zh", or null for no match
     */
    private static function classify(array $entry, array $tokens): ?string
    {
        $code = (string) ($entry['hts8'] ?? '');
        $desc = strtolower((string) ($entry['description'] ?? ''));
        $zh = strtolower(
            (string) ($entry['descriptionZh'] ?? '') . implode('', (array) ($entry['keywordsZh'] ?? []))
        );

        $codeOk = $enOk = $zhOk = true;
        foreach ($tokens as $token) {
            if (strpos($code, $token) === false) {
                $codeOk = false;
            }
            if (strpos($desc, $token) === false) {
                $enOk = false;
            }
            if (strpos($zh, $token) === false) {
                $zhOk = false;
            }
            if (!$codeOk && !$enOk && !$zhOk) {
                return null;
            }
        }

        if ($codeOk) {
            return 'code';
        }
        if ($enOk) {
            return 'en';
        }
        if ($zhOk) {
            return 'zh';
        }

        return null;
    }

    /**
     * Changelog sorted newest-date-first; ties keep file order (stable).
     * ISO dates (YYYY-MM-DD) compare lexically, so a byte comparison is
     * chronological.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function sortedChangelog(): array
    {
        $entries = (array) self::changelogData()['entries'];
        $indexed = [];
        $i = 0;
        foreach ($entries as $entry) {
            $indexed[] = ['i' => $i++, 'e' => $entry];
        }
        usort($indexed, static function (array $a, array $b): int {
            $da = (string) ($a['e']['date'] ?? '');
            $db = (string) ($b['e']['date'] ?? '');
            if ($da === $db) {
                return $a['i'] <=> $b['i'];
            }

            return strcmp($db, $da);
        });

        $out = [];
        foreach ($indexed as $row) {
            $out[] = $row['e'];
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private static function seed(): array
    {
        if (self::$seed === null) {
            self::$seed = self::parseDataFile('ruleset-seed.json', 'ruleset-seed');
        }

        return self::$seed;
    }

    /**
     * @return array<string,mixed>
     */
    private static function changelogData(): array
    {
        if (self::$changelog === null) {
            self::$changelog = self::parseDataFile('changelog.json', 'changelog');
        }

        return self::$changelog;
    }

    /**
     * @return array<string,mixed>
     */
    private static function parseDataFile(string $file, string $label): array
    {
        $decoded = json_decode(self::readDataFile($file), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf('us-tariff-lookup: bundled %s.json is invalid.', $label));
        }

        return $decoded;
    }

    private static function readDataFile(string $file): string
    {
        $path = __DIR__ . '/../data/' . $file;
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException(sprintf('us-tariff-lookup: cannot read bundled data file %s.', $path));
        }

        return $raw;
    }
}
