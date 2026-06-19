<?php

declare(strict_types=1);

namespace DeployEcommerce\UrlRewriteImportExport\Model\Csv;

use DeployEcommerce\UrlRewriteImportExport\Model\Schema\ColumnProvider;
use League\Csv\Writer;
use Magento\Framework\App\ResourceConnection;

/**
 * Streams the url_rewrite table to a League\Csv Writer one row at a time.
 *
 * Uses an unbuffered query so the full result set is never held in memory;
 * the Writer is given a stream (php://output for download, or a var/ file for
 * the pre-import backup), so only a single row is resident at any moment.
 */
class Exporter
{
    private const FETCH_CHUNK = 1000;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ColumnProvider $columnProvider
    ) {
    }

    /**
     * @return int Number of data rows written (excludes the header row).
     */
    public function export(Writer $writer): int
    {
        $columns = $this->columnProvider->getColumns();
        $writer->insertOne($columns);

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->columnProvider->getTableName(), $columns);

        $written = 0;
        // fetchAll with a page offset would re-scan; instead iterate the
        // statement so PDO streams rows without materialising the whole set.
        $statement = $connection->query($select);
        $batch = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $batch[] = $this->orderRow($row, $columns);
            if (count($batch) >= self::FETCH_CHUNK) {
                $writer->insertAll($batch);
                $written += count($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $writer->insertAll($batch);
            $written += count($batch);
        }

        return $written;
    }

    /**
     * Guarantee column order matches the header regardless of driver row order.
     *
     * @param array<string,mixed> $row
     * @param string[] $columns
     * @return array<int,mixed>
     */
    private function orderRow(array $row, array $columns): array
    {
        $ordered = [];
        foreach ($columns as $column) {
            $ordered[] = $row[$column] ?? null;
        }

        return $ordered;
    }
}
