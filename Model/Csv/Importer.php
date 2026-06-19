<?php

declare(strict_types=1);

namespace DeployEcommerce\UrlRewriteImportExport\Model\Csv;

use DeployEcommerce\UrlRewriteImportExport\Model\Schema\ColumnProvider;
use League\Csv\Reader;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * Imports a previously exported url_rewrite CSV.
 *
 * Reads the file lazily (one row at a time) and writes it in chunked batches,
 * so memory stays flat regardless of file size — no queue required.
 *
 * The caller is responsible for taking the safety backup BEFORE invoking this.
 */
class Importer
{
    public const MODE_UPSERT = 'upsert';
    public const MODE_SKIP = 'skip';
    public const MODE_FAIL = 'fail';

    public const ID_PRESERVE = 'preserve';
    public const ID_AUTO = 'auto';

    private const INSERT_CHUNK = 1000;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ColumnProvider $columnProvider,
        private readonly HeaderValidator $headerValidator,
        private readonly ImportResultFactory $resultFactory
    ) {
    }

    /**
     * @throws LocalizedException When the file cannot be read or its header is invalid.
     */
    public function import(
        string $absolutePath,
        bool $truncate,
        string $mode,
        string $idHandling
    ): ImportResult {
        $reader = $this->createReader($absolutePath);
        $csvHeader = $reader->getHeader();
        $dbColumns = $this->columnProvider->getColumns();

        $unknown = $this->headerValidator->unknownColumns($csvHeader, $dbColumns);
        if ($unknown !== []) {
            throw new LocalizedException(new Phrase(
                'CSV contains columns that do not exist in the url_rewrite table: %1. '
                . 'Import aborted; no rows were changed.',
                [implode(', ', $unknown)]
            ));
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->columnProvider->getTableName();
        $result = $this->resultFactory->create();
        $result->setOptions([
            'truncate' => $truncate,
            'mode' => $mode,
            'id_handling' => $idHandling,
        ]);

        $dropIdColumn = $idHandling === self::ID_AUTO ? $this->columnProvider->getIdentityColumn() : null;
        $updateColumns = array_values(array_diff($dbColumns, [$this->columnProvider->getIdentityColumn()]));

        if ($truncate) {
            // url_rewrite is referenced by a foreign key
            // (catalog_url_rewrite_product_category.url_rewrite_id), so MySQL forbids
            // TRUNCATE on it. DELETE works and, because that FK is ON DELETE CASCADE,
            // the dependent rows are removed automatically without a constraint error.
            $connection->delete($table);
        }

        $batch = [];
        foreach ($reader->getRecords() as $record) {
            $row = $this->prepareRow($record, $dropIdColumn);
            if ($row === []) {
                continue;
            }
            $result->addTotal(1);
            $batch[] = $row;
            if (count($batch) >= self::INSERT_CHUNK) {
                $this->flush($connection, $table, $batch, $mode, $updateColumns, $result);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $this->flush($connection, $table, $batch, $mode, $updateColumns, $result);
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    private function prepareRow(array $record, ?string $dropIdColumn): array
    {
        if ($dropIdColumn !== null) {
            unset($record[$dropIdColumn]);
        }
        // Treat empty strings on nullable columns as NULL so the UNIQUE index
        // and round-tripped exports behave identically.
        foreach ($record as $key => $value) {
            if ($value === '') {
                $record[$key] = null;
            }
        }

        return $record;
    }

    /**
     * @param array<int,array<string,mixed>> $batch
     * @param string[] $updateColumns
     */
    private function flush(
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        string $table,
        array $batch,
        string $mode,
        array $updateColumns,
        ImportResult $result
    ): void {
        try {
            if ($mode === self::MODE_UPSERT) {
                $connection->insertOnDuplicate($table, $batch, $updateColumns);
                $result->addInserted(count($batch));
                return;
            }

            if ($mode === self::MODE_FAIL) {
                $connection->insertMultiple($table, $batch);
                $result->addInserted(count($batch));
                return;
            }

            // MODE_SKIP — insert row by row; a duplicate is skipped, anything
            // else is counted as a hard failure (both reported).
            foreach ($batch as $row) {
                try {
                    $connection->insert($table, $row);
                    $result->addInserted(1);
                } catch (\Throwable $rowError) {
                    $isDuplicate = $this->isDuplicateError($rowError);
                    $isDuplicate ? $result->addSkipped(1) : $result->addFailed(1);
                    if (count($result->getErrors()) < 20) {
                        $result->addError(sprintf(
                            '%s (request_path "%s", store_id "%s"): %s',
                            $isDuplicate ? 'Skipped duplicate' : 'Failed',
                            (string) ($row['request_path'] ?? ''),
                            (string) ($row['store_id'] ?? ''),
                            $rowError->getMessage()
                        ));
                    }
                }
            }
        } catch (\Throwable $batchError) {
            if ($mode === self::MODE_FAIL) {
                throw new LocalizedException(
                    new Phrase('Import failed on a batch: %1', [$batchError->getMessage()]),
                    $batchError
                );
            }
            // Upsert batch failure — the whole batch did not land.
            $result->addFailed(count($batch));
            $result->addError($batchError->getMessage());
        }
    }

    private function isDuplicateError(\Throwable $error): bool
    {
        return str_contains($error->getMessage(), 'Duplicate entry')
            || (int) $error->getCode() === 1062;
    }

    private function createReader(string $absolutePath): Reader
    {
        try {
            $reader = Reader::createFromPath($absolutePath, 'r');
            $reader->setHeaderOffset(0);
        } catch (\Throwable $e) {
            throw new LocalizedException(
                new Phrase('Could not read the uploaded CSV: %1', [$e->getMessage()]),
                $e
            );
        }

        return $reader;
    }
}
