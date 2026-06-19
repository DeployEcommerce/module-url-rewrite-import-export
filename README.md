# DeployEcommerce_UrlRewriteImportExport

Export and import the Magento `url_rewrite` table as CSV from the admin, with safety backups.

## What it does

- **Export** — one click downloads the entire `url_rewrite` table as `url_rewrite_YYYY_MM_DD_HHmmss.csv`. CSV columns mirror the database columns exactly (read live from the schema, never hardcoded). Streamed row-by-row, so memory stays flat on large tables. No queues.
- **Import** — upload a CSV previously exported by this tool. Before any row is written:
  - the CSV header is validated against the live database columns; an unknown column aborts the import;
  - a safety backup of the current table is always written to `var/rewrites/url_rewrite_<timestamp>.csv`;
  - if you did not export a backup through this tool first, you are warned.
- **Import options** (admin-selectable, echoed back in the result for debug reports):
  - *Import mode* — Upsert / Skip duplicates / Fail on duplicate.
  - *ID handling* — Preserve `url_rewrite_id` from the CSV, or auto-assign new IDs.
  - *Truncate* — optional checkbox to empty the table first; requires an extra JS confirmation.
- **Result table** — after import, shows rows in file / imported / skipped (duplicates) / failed, plus the exact options used.

Admin page: **System → Tools → URL Rewrite Import / Export**.

## Install

This module ships as a standalone Composer package, `deployecommerce/module-url-rewrite-import-export`.

### Via a VCS / Packagist repository (deploy)

```bash
composer require deployecommerce/module-url-rewrite-import-export:^1.0
bin/magento module:enable DeployEcommerce_UrlRewriteImportExport
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Via a local path repository (development)

Add a `path` repository pointing at this checkout and require it as `@dev`:

```jsonc
"repositories": {
    "local-url-rewrite-import-export": {
        "type": "path",
        "url": "packages/module-url-rewrite-import-export",
        "options": { "symlink": true }
    }
}
```

```bash
composer require deployecommerce/module-url-rewrite-import-export:@dev
```

In a Docker setup whose `vendor/` is a named volume, the path-repo target must be
visible **inside the container** — mount this checkout into the project, e.g.
`/path/to/module:/var/www/html/packages/module-url-rewrite-import-export`.

`league/csv:^9.28` is a hard dependency (declared in this module's `composer.json`)
and is pulled in automatically by Composer.

## Tests (PEST)

The framework-free service classes (`HeaderValidator`, `FilenameGenerator`) are unit-tested
with [PEST](https://pestphp.com). The harness is **isolated** under `tests/` with its own
`composer.json` because Pest 2 ships PHPUnit 10, which conflicts with Magento's bundled
PHPUnit 9 — keeping it separate avoids the clash and never boots Magento.

```bash
cd tests
composer install
./vendor/bin/pest
```

Database-touching classes (`Exporter`, `Importer`, `BackupWriter`) are integration territory
and are intentionally out of the pure-unit harness.
