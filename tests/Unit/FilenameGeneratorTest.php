<?php

declare(strict_types=1);

use DeployEcommerce\UrlRewriteImportExport\Model\Csv\FilenameGenerator;

it('builds the timestamped filename in the required format', function () {
    $generator = new FilenameGenerator();
    $when = new DateTimeImmutable('2026-06-19 14:07:09');

    expect($generator->generate($when))->toBe('url_rewrite_2026_06_19_140709.csv');
});

it('zero-pads single-digit date and time parts', function () {
    $generator = new FilenameGenerator();
    $when = new DateTimeImmutable('2026-01-02 03:04:05');

    expect($generator->generate($when))->toBe('url_rewrite_2026_01_02_030405.csv');
});

it('is deterministic for a fixed clock', function () {
    $generator = new FilenameGenerator();
    $when = new DateTimeImmutable('2026-12-31 23:59:59');

    expect($generator->generate($when))->toBe($generator->generate($when));
});
