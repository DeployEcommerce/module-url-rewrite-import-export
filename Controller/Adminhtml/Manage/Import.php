<?php

declare(strict_types=1);

namespace DeployEcommerce\UrlRewriteImportExport\Controller\Adminhtml\Manage;

use DeployEcommerce\UrlRewriteImportExport\Model\Backup\BackupWriter;
use DeployEcommerce\UrlRewriteImportExport\Model\Csv\Importer;
use DeployEcommerce\UrlRewriteImportExport\Model\Session\BackupTracker;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem;

/**
 * Handles the import form submission:
 *   1. validate the upload,
 *   2. warn if no backup was taken via this tool,
 *   3. always take a safety backup of the current table to var/rewrites/,
 *   4. run the chunked import,
 *   5. stash a result summary for the on-screen table.
 */
class Import extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'DeployEcommerce_UrlRewriteImportExport::manage';

    public const SESSION_RESULT_KEY = 'deployecommerce_urlrewriteimportexport_last_result';

    private const UPLOAD_DIR = 'rewrites/import';
    private const FILE_FIELD = 'import_file';

    public function __construct(
        Context $context,
        private readonly UploaderFactory $uploaderFactory,
        private readonly Filesystem $filesystem,
        private readonly Importer $importer,
        private readonly BackupWriter $backupWriter,
        private readonly BackupTracker $backupTracker,
        private readonly TypeListInterface $cacheTypeList,
        private readonly Session $session
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/index');
        $truncate = (bool) $this->getRequest()->getParam('truncate_existing');
        $mode = (string) $this->getRequest()->getParam('import_mode', Importer::MODE_UPSERT);
        $idHandling = (string) $this->getRequest()->getParam('id_handling', Importer::ID_PRESERVE);

        if (!$this->validateOptions($mode, $idHandling)) {
            $this->messageManager->addErrorMessage(__('Invalid import options submitted.'));
            return $redirect;
        }

        // Warn (do not block) when the operator has not exported a backup via
        // this tool first — a safety backup is still taken below regardless.
        if (!$this->backupTracker->hasExported()) {
            $this->messageManager->addWarningMessage(__(
                'It does not look like you exported a backup through this tool first. '
                . 'A safety backup was still written to var/rewrites/, but please export '
                . 'a full backup before importing.'
            ));
        }

        try {
            $absolutePath = $this->uploadCsv();
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Upload failed: %1', $e->getMessage()));
            return $redirect;
        }

        try {
            $backupPath = $this->backupWriter->write(new \DateTimeImmutable('now'));
            $this->messageManager->addNoticeMessage(
                __('Safety backup written to: %1', $backupPath)
            );

            $result = $this->importer->import($absolutePath, $truncate, $mode, $idHandling);

            // Stash the full summary so the index page can render the result table.
            $this->session->setData(self::SESSION_RESULT_KEY, $result->toArray());

            $this->cacheTypeList->cleanType('full_page');
            $this->cacheTypeList->cleanType('block_html');

            if ($result->hasErrors()) {
                $this->messageManager->addWarningMessage(__(
                    'Import finished with issues: %1 imported, %2 skipped, %3 failed of %4 rows. '
                    . 'See the result table below.',
                    [$result->getInserted(), $result->getSkipped(), $result->getFailed(), $result->getTotal()]
                ));
            } else {
                $this->messageManager->addSuccessMessage(__(
                    'Import complete: %1 of %2 rows imported.',
                    [$result->getInserted(), $result->getTotal()]
                ));
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Import failed: %1', $e->getMessage()));
        }

        return $redirect;
    }

    private function validateOptions(string $mode, string $idHandling): bool
    {
        $validModes = [Importer::MODE_UPSERT, Importer::MODE_SKIP, Importer::MODE_FAIL];
        $validId = [Importer::ID_PRESERVE, Importer::ID_AUTO];

        return in_array($mode, $validModes, true) && in_array($idHandling, $validId, true);
    }

    private function uploadCsv(): string
    {
        $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $varDir->create(self::UPLOAD_DIR);
        $destination = $varDir->getAbsolutePath(self::UPLOAD_DIR);

        $uploader = $this->uploaderFactory->create(['fileId' => self::FILE_FIELD]);
        $uploader->setAllowedExtensions(['csv']);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);

        $saved = $uploader->save($destination);

        return rtrim($saved['path'], '/') . '/' . $saved['file'];
    }
}
