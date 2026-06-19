<?php

declare(strict_types=1);

namespace DeployEcommerce\UrlRewriteImportExport\Model\Csv;

/**
 * Mutable tally of an import run, surfaced to the admin as a summary table.
 *
 * Also records the exact options the operator chose, so a debug report tells us
 * which mode / ID handling / truncate setting produced a given outcome.
 */
class ImportResult
{
    private int $total = 0;
    private int $inserted = 0;
    private int $skipped = 0;
    private int $failed = 0;

    /** @var string[] */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $options = [];

    public function addTotal(int $count): void
    {
        $this->total += $count;
    }

    public function addInserted(int $count): void
    {
        $this->inserted += $count;
    }

    public function addSkipped(int $count): void
    {
        $this->skipped += $count;
    }

    public function addFailed(int $count): void
    {
        $this->failed += $count;
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * @param array<string,mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getInserted(): int
    {
        return $this->inserted;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<string,mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Flat, serialisable summary — stored in the session for the result table
     * and safe to copy into a debug report.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'inserted' => $this->inserted,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'errors' => $this->errors,
            'options' => $this->options,
        ];
    }
}
