<?php

declare(strict_types=1);

namespace DeployEcommerce\UrlRewriteImportExport\Model\Schema;

use Magento\Framework\App\ResourceConnection;

/**
 * Single source of truth for the url_rewrite table's columns.
 *
 * Reads the live schema via DESCRIBE so the export header and import validation
 * always mirror the actual database — no hardcoded column list to drift.
 */
class ColumnProvider
{
    public const TABLE = 'url_rewrite';

    /** @var string[]|null */
    private ?array $columns = null;

    private ?string $identityColumn = null;

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function getTableName(): string
    {
        return $this->resourceConnection->getTableName(self::TABLE);
    }

    /**
     * Ordered list of column names exactly as the database declares them.
     *
     * @return string[]
     */
    public function getColumns(): array
    {
        $this->load();

        return $this->columns;
    }

    /**
     * The auto-increment / primary key column (url_rewrite_id), used when the
     * admin chooses to auto-assign IDs on import.
     */
    public function getIdentityColumn(): ?string
    {
        $this->load();

        return $this->identityColumn;
    }

    private function load(): void
    {
        if ($this->columns !== null) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $describe = $connection->describeTable($this->getTableName());

        $this->columns = array_keys($describe);
        foreach ($describe as $name => $meta) {
            if (!empty($meta['IDENTITY'])) {
                $this->identityColumn = $name;
                break;
            }
        }
    }
}
