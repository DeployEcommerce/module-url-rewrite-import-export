<?php

declare(strict_types=1);

namespace DeployEcommerce\UrlRewriteImportExport\Block\Adminhtml;

use DeployEcommerce\UrlRewriteImportExport\Controller\Adminhtml\Manage\Import;
use DeployEcommerce\UrlRewriteImportExport\Model\Csv\Importer;
use DeployEcommerce\UrlRewriteImportExport\Model\Schema\ColumnProvider;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Session;

class Manage extends Template
{
    public function __construct(
        Context $context,
        private readonly ColumnProvider $columnProvider,
        private readonly Session $session,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getExportUrl(): string
    {
        return $this->getUrl('*/*/export');
    }

    public function getImportUrl(): string
    {
        return $this->getUrl('*/*/import');
    }

    /**
     * @return string[]
     */
    public function getColumns(): array
    {
        return $this->columnProvider->getColumns();
    }

    /**
     * @return array<string,string>
     */
    public function getImportModes(): array
    {
        return [
            Importer::MODE_UPSERT => (string) __('Upsert — update existing rows, insert new ones'),
            Importer::MODE_SKIP => (string) __('Skip duplicates — keep existing rows, report skips'),
            Importer::MODE_FAIL => (string) __('Fail on duplicate — abort if a row collides'),
        ];
    }

    /**
     * @return array<string,string>
     */
    public function getIdHandlingOptions(): array
    {
        return [
            Importer::ID_PRESERVE => (string) __('Preserve IDs from the CSV (exact round-trip)'),
            Importer::ID_AUTO => (string) __('Auto-assign new IDs (drop url_rewrite_id)'),
        ];
    }

    /**
     * The summary of the most recent import (consumed once, then cleared).
     *
     * @return array<string,mixed>|null
     */
    public function getLastResult(): ?array
    {
        $result = $this->session->getData(Import::SESSION_RESULT_KEY);
        if ($result) {
            $this->session->unsetData(Import::SESSION_RESULT_KEY);
        }

        return $result ?: null;
    }
}
