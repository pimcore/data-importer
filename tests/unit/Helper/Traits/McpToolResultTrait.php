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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Helper\Traits;

use const JSON_THROW_ON_ERROR;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;

/**
 * Envelope assertions shared by the Data Importer MCP tool tests.
 *
 * Every one of those tools answers with a single JSON TextContent, so asserting the shape in
 * one place is what keeps a change to the envelope (a second content block, a move to
 * structuredContent, a renamed key) from passing every test in the suite.
 */
trait McpToolResultTrait
{
    /**
     * @return array<string, mixed>
     */
    private function decodeToolResult(CallToolResult $result): array
    {
        $this->assertCount(1, $result->content, 'Expected exactly one content block.');

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);

        $payload = json_decode((string) $content->text, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);

        return $payload;
    }

    /**
     * @return array<string, mixed> the decoded payload, for further per-tool assertions
     */
    private function assertToolError(CallToolResult $result, string $message, string $code): array
    {
        $this->assertTrue($result->isError, 'Expected an error result.');

        $payload = $this->decodeToolResult($result);
        $this->assertSame($message, $payload['error'] ?? null);
        $this->assertSame($code, $payload['code'] ?? null);
        $this->assertSame(['error', 'code'], array_keys($payload), 'Unexpected error envelope keys.');

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function assertToolSuccess(CallToolResult $result): array
    {
        $this->assertFalse($result->isError, 'Expected a success result.');

        $payload = $this->decodeToolResult($result);
        $this->assertArrayNotHasKey('error', $payload);
        $this->assertArrayNotHasKey('code', $payload);

        return $payload;
    }

    /**
     * The terminal `catch (Throwable)` of a tool must never forward what the collaborator said.
     * Asserts the generic sentence, the correlation id, the code and the absence of $secret.
     */
    private function assertGenericInternalError(
        CallToolResult $result,
        string $toolName,
        string $secret
    ): void {
        $this->assertTrue($result->isError, 'Expected an error result.');

        $payload = $this->decodeToolResult($result);
        $this->assertMatchesRegularExpression(
            '/^Internal error while executing ' . preg_quote($toolName, '/') . ' \(ref: [0-9a-f]{8}\)\./',
            (string) ($payload['error'] ?? ''),
        );
        $this->assertSame('internal_error', $payload['code'] ?? null);
        $this->assertSame(['error', 'code'], array_keys($payload), 'Unexpected error envelope keys.');
        $this->assertStringNotContainsString($secret, (string) json_encode($payload));
    }
}
