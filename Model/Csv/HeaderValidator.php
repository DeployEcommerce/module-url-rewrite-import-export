<?php

declare(strict_types=1);

namespace DeployEcommerce\UrlRewriteImportExport\Model\Csv;

/**
 * Validates a CSV header against the live database columns of the url_rewrite table.
 *
 * Pure logic — both the CSV header and the DB column list are passed in, so this
 * class has no Magento dependencies and is fully unit-testable.
 */
class HeaderValidator
{
    /**
     * Columns present in the CSV header but NOT in the database.
     *
     * Any non-empty result must abort the import — we never write to a column
     * the table does not have.
     *
     * @param string[] $csvHeader
     * @param string[] $dbColumns
     * @return string[]
     */
    public function unknownColumns(array $csvHeader, array $dbColumns): array
    {
        return array_values(array_diff(
            $this->normalize($csvHeader),
            $this->normalize($dbColumns)
        ));
    }

    /**
     * Database columns the CSV does not provide (informational — not fatal,
     * since nullable/defaulted columns can be omitted).
     *
     * @param string[] $csvHeader
     * @param string[] $dbColumns
     * @return string[]
     */
    public function missingColumns(array $csvHeader, array $dbColumns): array
    {
        return array_values(array_diff(
            $this->normalize($dbColumns),
            $this->normalize($csvHeader)
        ));
    }

    /**
     * Import is allowed only when the CSV introduces no unknown columns and
     * carries at least one column.
     *
     * @param string[] $csvHeader
     * @param string[] $dbColumns
     */
    public function isImportable(array $csvHeader, array $dbColumns): bool
    {
        return $csvHeader !== [] && $this->unknownColumns($csvHeader, $dbColumns) === [];
    }

    /**
     * @param string[] $columns
     * @return string[]
     */
    private function normalize(array $columns): array
    {
        return array_map(static fn ($c): string => trim((string) $c), $columns);
    }
}
