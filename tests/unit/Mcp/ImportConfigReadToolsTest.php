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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Mcp;

use Codeception\Test\Unit;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Proposal\Mcp\ConfigProposalTools;
use Pimcore\Bundle\DataHubBundle\Proposal\StoredConfigurationsInterface;
use Pimcore\Bundle\DataHubBundle\Service\AdapterAvailabilityServiceInterface;
use Pimcore\Bundle\DataHubBundle\Service\Studio\ConfigurationServiceInterface;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigPolicy;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\GetImportConfigTool;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ListImportConfigsTool;
use Pimcore\Bundle\PimcoreAgentBundle\Proposal\ProposalWidgetEmitter;
use Pimcore\Bundle\PimcoreAgentBundle\Security\BoundSessionReferenceResolver;
use Pimcore\Bundle\PimcoreAgentBundle\Service\AgentSessionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use ReflectionClass;
use Symfony\Component\HttpFoundation\RequestStack;

class ImportConfigReadToolsTest extends Unit
{
    private ConfigProposalTools $tools;

    private ImportConfigPolicy $policy;

    protected function _before(): void
    {
        // the Agent bundle is an optional integration this repository cannot install, and the
        // shared tools come from a Data Hub proposal lane the data-hub constraint admits no release of yet
        if (!interface_exists(AgentSessionServiceInterface::class) || !class_exists(ConfigProposalTools::class)) {
            static::markTestSkipped('Agent bundle or Data Hub proposal lane not installed');
        }

        $carImport = $this->stored('car-import', ImportConfigPolicy::CONFIG_TYPE, [
            'general' => ['name' => 'car-import', 'group' => 'fleet', 'active' => true],
            'interpreterConfig' => ['type' => 'csv'],
        ]);
        $graphql = $this->stored('car-api', 'graphql', ['general' => ['name' => 'car-api']]);
        $stored = $this->createStub(StoredConfigurationsInterface::class);
        $stored->method('all')->willReturn([$carImport, $graphql]);
        $stored->method('find')->willReturnCallback(
            static fn (string $name): ?Configuration => [
                'car-import' => $carImport,
                'car-api' => $graphql,
            ][$name] ?? null
        );

        $admin = new User();
        $admin->setAdmin(true);
        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturn($admin);
        $adapters = $this->createStub(AdapterAvailabilityServiceInterface::class);
        $adapters->method('isEnabled')->willReturn(true);

        $this->tools = new ConfigProposalTools(
            $this->createStub(AgentSessionServiceInterface::class),
            // reading never emits a proposal widget
            (new ReflectionClass(ProposalWidgetEmitter::class))->newInstanceWithoutConstructor(),
            new BoundSessionReferenceResolver(new RequestStack()),
            $security,
            $this->createStub(McpToolErrorHandlerInterface::class),
            $stored,
            $adapters,
        );
        $this->policy = new ImportConfigPolicy($this->createStub(ConfigurationServiceInterface::class));
    }

    public function testTheListNamesOnlyImportConfigurations(): void
    {
        $payload = $this->payload((new ListImportConfigsTool($this->tools, $this->policy))->execute());

        static::assertSame(
            [['name' => 'car-import', 'group' => 'fleet', 'description' => '', 'active' => true]],
            $payload['configurations']
        );
    }

    public function testAnImportConfigurationIsReadInFull(): void
    {
        $payload = $this->payload((new GetImportConfigTool($this->tools, $this->policy))->execute('car-import'));

        static::assertSame('car-import', $payload['name']);
        static::assertSame('csv', $payload['configuration']['interpreterConfig']['type']);
    }

    public function testAnotherAdaptersConfigurationIsNoImportConfiguration(): void
    {
        $result = (new GetImportConfigTool($this->tools, $this->policy))->execute('car-api');

        static::assertTrue($result->isError);
        static::assertSame('not_found', $this->payload($result)['code']);
    }

    /** @param array<string, mixed> $document */
    private function stored(string $name, string $type, array $document): Configuration
    {
        $configuration = $this->createStub(Configuration::class);
        $configuration->method('getName')->willReturn($name);
        $configuration->method('getType')->willReturn($type);
        $configuration->method('getConfiguration')->willReturn($document);
        $configuration->method('isAllowed')->willReturn(true);

        return $configuration;
    }

    /** @return array<string, mixed> */
    private function payload(CallToolResult $result): array
    {
        $content = $result->content[0];
        static::assertInstanceOf(TextContent::class, $content);

        return json_decode($content->text, true, flags: JSON_THROW_ON_ERROR);
    }
}
