<?php

declare(strict_types=1);

namespace DeployEcommerce\UrlRewriteImportExport\Model\Session;

use Magento\Backend\Model\Session;

/**
 * Tracks, in the admin user's backend session, whether they have exported a
 * backup through this tool during the current session.
 *
 * Used to warn before an import when no backup appears to have been taken.
 */
class BackupTracker
{
    private const KEY = 'deployecommerce_urlrewriteimportexport_exported';

    public function __construct(
        private readonly Session $session
    ) {
    }

    public function markExported(): void
    {
        $this->session->setData(self::KEY, true);
    }

    public function hasExported(): bool
    {
        return (bool) $this->session->getData(self::KEY);
    }
}
