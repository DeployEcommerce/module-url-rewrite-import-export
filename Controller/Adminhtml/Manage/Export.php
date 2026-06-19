<?php

declare(strict_types=1);

namespace DeployEcommerce\UrlRewriteImportExport\Controller\Adminhtml\Manage;

use DeployEcommerce\UrlRewriteImportExport\Model\Csv\Exporter;
use DeployEcommerce\UrlRewriteImportExport\Model\Csv\FilenameGenerator;
use DeployEcommerce\UrlRewriteImportExport\Model\Session\BackupTracker;
use League\Csv\Writer;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Filesystem;

/**
 * Streams the url_rewrite table to the browser as a timestamped CSV download
 * and records in the admin session that a backup has been taken.
 */
class Export extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'DeployEcommerce_UrlRewriteImportExport::manage';

    private const EXPORT_DIR = 'rewrites/export';

    public function __construct(
        Context $context,
        private readonly Exporter $exporter,
        private readonly FilenameGenerator $filenameGenerator,
        private readonly Filesystem $filesystem,
        private readonly FileFactory $fileFactory,
        private readonly BackupTracker $backupTracker
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect|\Magento\Framework\App\ResponseInterface
    {
        try {
            $filename = $this->filenameGenerator->generate(new \DateTimeImmutable('now'));

            $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $varDir->create(self::EXPORT_DIR);
            $relativePath = self::EXPORT_DIR . '/' . $filename;

            $writer = Writer::createFromPath($varDir->getAbsolutePath($relativePath), 'w+');
            $this->exporter->export($writer);

            $this->backupTracker->markExported();

            return $this->fileFactory->create(
                $filename,
                ['type' => 'filename', 'value' => $relativePath, 'rm' => true],
                DirectoryList::VAR_DIR,
                'text/csv'
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Export failed: %1', $e->getMessage()));
            $redirect = $this->resultRedirectFactory->create();

            return $redirect->setPath('*/*/index');
        }
    }
}
