<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Tests\Walkthrough;

use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportServiceInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The rows of a step as a file, uploaded and started the way Studio does it.
 */
final readonly class AdHocImportWalkthroughFile
{
    private const string HEADER = "sku;name;title;quantity\n";

    private const string FILE_NAME = 'products.csv';

    public function __construct(
        private AdHocImportServiceInterface $uploads,
        private AdHocImportRunServiceInterface $service,
    ) {
    }

    /**
     * @param list<string> $rows the lines of the file below its header
     *
     * @return array{string, AdHocImportRunResponse} the file and how its import started
     */
    public function start(array $rows, string $errorMode, AdHocImportWalkthroughFixture $fixture): array
    {
        $fileId = $this->upload(self::HEADER . implode("\n", $rows) . "\n", $fixture->folderId);

        return [$fileId, $this->service->start($fileId, $this->parameters($errorMode, $fixture))];
    }

    private function upload(string $csv, int $folderId): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('ad-hoc-walkthrough-', true);
        file_put_contents($path, $csv);

        try {
            $fileId = $this->uploads
                ->upload(new UploadedFile($path, self::FILE_NAME, null, null, true), $folderId)
                ->getFileId();
        } finally {
            @unlink($path);
        }

        return $fileId ?? throw new AdHocImportWalkthroughFailure('The upload was refused.');
    }

    private function parameters(string $errorMode, AdHocImportWalkthroughFixture $fixture): AdHocImportRunParameters
    {
        return new AdHocImportRunParameters(
            new AdHocImportDryRunParameters(
                $fixture->folderId,
                (string) $fixture->class->getId(),
                AdHocImportDryRunParameters::MATCH_BY_FIELD,
                'sku',
                'sku',
                columns: [
                    ['column' => 'sku', 'field' => 'sku'],
                    ['column' => 'name', 'field' => 'name'],
                    ['column' => 'title', 'field' => 'title', 'language' => $fixture->language],
                    ['column' => 'quantity', 'field' => 'quantity'],
                ]
            ),
            $errorMode,
            self::FILE_NAME
        );
    }
}
