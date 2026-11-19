<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Tests\Fixture;

/**
 * Stream wrapper that stands in for PHP's http and https wrappers, so a download through
 * file_get_contents() can be tested without a network: every URL answers with BODY and is
 * recorded in $requestedUrls.
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

    public function stream_open(string $path): bool // NOSONAR - name set by PHP's streamWrapper
    {
        static::$requestedUrls[] = $path;
        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string // NOSONAR - name set by PHP's streamWrapper
    {
        $chunk = substr(self::BODY, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool // NOSONAR - name set by PHP's streamWrapper
    {
        return $this->position >= strlen(self::BODY);
    }

    public function stream_stat(): array // NOSONAR - name set by PHP's streamWrapper
    {
        return [];
    }
}
