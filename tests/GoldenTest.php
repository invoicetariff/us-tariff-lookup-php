<?php

/**
 * Golden tests — zero-dependency standalone runner (no phpunit needed):
 *
 *   php tests/GoldenTest.php
 *   composer test   (same thing)
 *
 * The numbers must match the npm / PyPI / Go distributions and the web
 * calculator — they all ship the same versioned snapshot (ruleset v0.3.4,
 * as of 2026-10-07).
 */

declare(strict_types=1);

foreach ([__DIR__ . '/../vendor/autoload.php'] as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        $found = true;
        break;
    }
}
if (!isset($found)) {
    require_once __DIR__ . '/../src/TariffLookup.php';
}

use InvoiceTariff\UsTariffLookup\TariffLookup;

$failures = 0;
$checks = 0;

function check(bool $condition, string $label): void
{
    global $failures, $checks;
    $checks++;
    if ($condition) {
        echo "PASS  $label\n";
    } else {
        $failures++;
        echo "FAIL  $label\n";
    }
}

// --- meta golden numbers -------------------------------------------------
$meta = TariffLookup::meta();
check($meta['rulesetVersion'] === '0.3.4', 'meta.rulesetVersion === 0.3.4');
check($meta['dataAsOf'] === '2026-10-07', 'meta.dataAsOf === 2026-10-07');
check($meta['mfnCuratedCodes'] === 484, 'meta.mfnCuratedCodes === 484 (curated codes)');
check($meta['china301Lines'] === 25, 'meta.china301Lines === 25');
check($meta['global301Tiers'] === 60, 'meta.global301Tiers === 60');
check($meta['section232Measures'] === 8, 'meta.section232Measures === 8');
check($meta['changelogEntries'] === 57, 'meta.changelogEntries === 57 (changelog entries)');
check($meta['disclaimer'] === TariffLookup::DISCLAIMER, 'meta.disclaimer echoes DISCLAIMER');

// --- search: English ------------------------------------------------------
$hits = TariffLookup::search('t-shirt');
check($hits !== [] && $hits[0]['entry']['hts8'] === '61091000', 'search "t-shirt" first hit 61091000');
check($hits !== [] && $hits[0]['matchedBy'] === 'en', 'search "t-shirt" matchedBy en');
check($hits !== [] && $hits[0]['entry']['general'] === '16.5%', 'search "t-shirt" general 16.5%');

// --- search: Chinese ------------------------------------------------------
$hits = TariffLookup::search('保温杯');
check($hits !== [] && $hits[0]['entry']['hts8'] === '96170010', 'search "保温杯" first hit 96170010');
check($hits !== [] && $hits[0]['matchedBy'] === 'zh', 'search "保温杯" matchedBy zh');

// --- search: multi-word AND (the npm 0.1.0 substring bug, fixed here day one)
check(TariffLookup::search('cotton t-shirt') !== [], 'search "cotton t-shirt" (multi-word AND) has hits');
check(TariffLookup::search('steel screws') !== [], 'search "steel screws" (multi-word AND) has hits');

// --- search: code prefix --------------------------------------------------
$hits = TariffLookup::search('6109');
check($hits !== [] && $hits[0]['entry']['hts8'] === '61091000', 'search "6109" first hit 61091000');
check($hits !== [] && $hits[0]['matchedBy'] === 'code', 'search "6109" matchedBy code');

// --- search: limit & empty ------------------------------------------------
check(TariffLookup::search('   ') === [], 'search "   " (blank) returns []');
check(count(TariffLookup::search('steel', 1)) <= 1, 'search limit=1 caps hits');
check(count(TariffLookup::search('steel', 0)) === 10, 'search limit<=0 defaults to 10');

// --- changelog ------------------------------------------------------------
$entries = TariffLookup::changelog();
check(count($entries) === 57, 'changelog has 57 entries');
check($entries[0]['date'] >= $entries[count($entries) - 1]['date'], 'changelog sorted date-desc');
$ids = array_column($entries, 'id');
check(in_array('2026-07-24-global301-relaunch', $ids, true), 'changelog contains 2026-07-24-global301-relaunch');
$limited = TariffLookup::changelog(5);
check(count($limited) === 5 && $limited[0]['id'] === $entries[0]['id'], 'changelog(5) keeps newest order');
check(TariffLookup::changelog(0) === [], 'changelog(0) returns []');

// --- raw JSON valid -------------------------------------------------------
$seedRaw = json_decode(TariffLookup::rulesetSeedRaw(), true);
check(is_array($seedRaw) && $seedRaw['version'] === '0.3.4', 'rulesetSeedRaw decodes, version 0.3.4');
$clRaw = json_decode(TariffLookup::changelogRaw(), true);
check(is_array($clRaw) && is_array($clRaw['entries']), 'changelogRaw decodes with entries');

echo "\n$checks checks, $failures failures\n";
exit($failures === 0 ? 0 : 1);
