# us-tariff-lookup (PHP)

Offline US import-tariff reference data for PHP: HTS code search (English + 中文), the stacked ruleset overview and a machine-readable rule-change feed — **data bundled, zero dependencies, no network calls, no API key**.

This is the PHP (Composer/Packagist) distribution of the same versioned snapshot that powers [invoicetariff.com](https://invoicetariff.com) and the [`us-tariff-lookup`](https://www.npmjs.com/package/us-tariff-lookup) npm package. Results are deterministic against a pinned version: every answer carries a `rulesetVersion` and a `dataAsOf` date.

## Install

```bash
composer require invoicetariff/us-tariff-lookup
```

Or clone this repo and use it directly — there is nothing to install (no vendor dependencies, `composer install` is only needed to expose the `us-tariff` bin on your PATH).

## Use

```php
<?php
require 'vendor/autoload.php';

use InvoiceTariff\UsTariffLookup\TariffLookup;

$meta = TariffLookup::meta();
echo $meta['rulesetVersion'], ' ', $meta['dataAsOf'], PHP_EOL; // 0.3.4 2026-10-07 (monthly rebuilds)

// HTS search: code prefix, English description or Chinese keyword.
// Multi-word queries are AND-tokenized across all surfaces.
foreach (TariffLookup::search('cotton t-shirt', 5) as $hit) {
    echo $hit['entry']['hts8'], ' ', $hit['entry']['general'], ' (', $hit['matchedBy'], ')', PHP_EOL;
    // 61091000 16.5% (en)
}

// Machine-readable rule changes, newest first (published + upcoming + historical).
foreach (TariffLookup::changelog(5) as $entry) {
    echo $entry['date'], ' ', $entry['layer'], ' ', $entry['status'], ' ', $entry['titleEn'], PHP_EOL;
}
```

CLI (zero-dependency, works straight from a checkout):

```bash
php bin/us-tariff meta
php bin/us-tariff search "steel screws"
php bin/us-tariff search 保温杯
php bin/us-tariff changelog 5
```

All commands print JSON with `rulesetVersion`, `dataAsOf` and a `disclaimer` attached.

## API

| Call | Returns |
|---|---|
| `TariffLookup::search(string $query, int $limit = 10)` | `[{ entry, matchedBy }]` — matchedBy is `"code"` \| `"en"` \| `"zh"`; multi-word queries are AND-tokenized |
| `TariffLookup::meta()` | rulesetVersion / dataAsOf / curated counts / disclaimer / data links |
| `TariffLookup::changelog(?int $limit = null)` | rule-change entries, newest date first |
| `TariffLookup::rulesetSeedRaw()` | the full bundled ruleset JSON string (fees, 232 measures, IEEPA history, sources) |
| `TariffLookup::changelogRaw()` | the full bundled changelog JSON string |

## What's inside (snapshot v0.3.4, as of 2026-10-07)

| Surface | Count | Notes |
|---|---|---|
| Curated MFN codes | 484 | High-frequency HTS 8-digit codes with official USITC general rates (EN + 中文) |
| China Section 301 lines | 25 | Lists 2/3/4A + 2026 four-year-review additions |
| Global 301 tiers | 60 | The 2026 global Section 301 program tiers |
| Section 232 measures | 8 | steel / aluminium / copper / autos / furniture / pharma / semiconductors / timber |
| Changelog entries | 57 | published + upcoming + historical, each sourced officially |

Need the layers this package doesn't model? `TariffLookup::rulesetSeedRaw()` and `TariffLookup::changelogRaw()` return the full bundled JSON for you to decode directly.

## Scope

v1 ships the **query surface** (search / meta / changelog). The full landed-cost calculation — stacked duty layers, specific/compound rates, MPF/HMF entry fees — lives in the [npm package](https://www.npmjs.com/package/us-tariff-lookup) and on the [web calculator](https://invoicetariff.com/tariff-calculator).

**Honest limits:** the bundled search covers curated high-frequency codes, not the full 5,689-line HTS — the full table ships in the [hs-codes-database](https://github.com/invoicetariff/hs-codes-database) repo. Most rate lines carry `sample`/`unverified` flags: planning-grade, not certified entry data. Estimates for planning only — not legal advice; your customs broker signs the entry.

## Data & updates

The snapshot is rebuilt monthly (and ad-hoc when major actions land) from the open [us-tariff-rates-2026](https://github.com/invoicetariff/us-tariff-rates-2026) repo. This package is re-released with each snapshot; watch the repo or the [Tariff Radar](https://invoicetariff.com/radar) feed for changes.

## Test

```bash
composer test          # or: php tests/GoldenTest.php
```

Golden tests pin the snapshot numbers (484 curated codes, 57 changelog entries, `t-shirt` → 61091000, `保温杯` → 96170010, meta 0.3.4 / 2026-10-07) so every distribution stays aligned.

## License

MIT — see [LICENSE](LICENSE).
