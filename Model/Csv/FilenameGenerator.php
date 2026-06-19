<?php

declare(strict_types=1);

namespace DeployEcommerce\UrlRewriteImportExport\Model\Csv;

/**
 * Generates timestamped CSV filenames: url_rewrite_YYYY_MM_DD_HHmmss.csv
 *
 * Pure logic — the clock is injected so the unit tests stay deterministic.
 */
class FilenameGenerator
{
    private const PREFIX = 'url_rewrite_';
    private const FORMAT = 'Y_m_d_His';

    public function generate(\DateTimeInterface $when): string
    {
        return self::PREFIX . $when->format(self::FORMAT) . '.csv';
    }
}
