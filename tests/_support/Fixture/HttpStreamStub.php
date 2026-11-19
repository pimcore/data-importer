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

namespace Pimcore\Bundle\DataImporterBundle\Tests\Fixture;

/**
 * Stream wrapper that stands in for PHP's http and https wrappers, so a download through
 * file_get_contents() can be tested without a network: every URL answers with BODY and is
 * recorded in $requestedUrls. The method names are dictated by PHP's streamWrapper prototype.
 */
class HttpStreamStub
{
    public const BODY = 'asset body served over http';

    /**
     * @var string[]
     */
    public static array $requestedUrls = [];

    /**
     * Set by PHP when the stream is opened with a context.
     *
     * @var resource|null
     */
    public $context;

    private int $position = 0;

    public function stream_open(string $path): bool
    {
        self::$requestedUrls[] = $path;
        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr(self::BODY, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen(self::BODY);
    }

    public function stream_stat(): array
    {
        return [];
    }
}
