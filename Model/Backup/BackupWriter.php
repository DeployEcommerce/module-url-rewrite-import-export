<?php

declare(strict_types=1);

namespace DeployEcommerce\UrlRewriteImportExport\Model\Backup;

use DeployEcommerce\UrlRewriteImportExport\Model\Csv\Exporter;
use DeployEcommerce\UrlRewriteImportExport\Model\Csv\FilenameGenerator;
use League\Csv\Writer;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

/**
 * Writes a safety backup of the current url_rewrite table to
 * var/rewrites/url_rewrite_YYYY_MM_DD_HHmmss.csv before any import runs.
 */
class BackupWriter
{
    private const BACKUP_DIR = 'rewrites';

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly Exporter $exporter,
        private readonly FilenameGenerator $filenameGenerator
    ) {
    }

    /**
     * @return string Absolute path of the written backup file.
     */
    public function write(\DateTimeInterface $when): string
    {
        $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $varDir->create(self::BACKUP_DIR);

        $relativePath = self::BACKUP_DIR . '/' . $this->filenameGenerator->generate($when);
        $absolutePath = $varDir->getAbsolutePath($relativePath);

        $writer = Writer::createFromPath($absolutePath, 'w+');
        $this->exporter->export($writer);

        return $absolutePath;
    }
}
