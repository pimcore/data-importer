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

use Closure;
use Codeception\Test\Unit;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigPolicy;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ImportConfigProposalBuilder;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ImportConfigVocabulary;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ProposeImportConfigTool;
use Pimcore\Bundle\PimcoreAgentBundle\Proposal\ProposalResolverInterface;
use Pimcore\Bundle\PimcoreAgentBundle\Proposal\ProposalResolverRegistry;
use Pimcore\Bundle\PimcoreAgentBundle\Proposal\ProposalWidgetEmitter;
use Pimcore\Bundle\PimcoreAgentBundle\Security\BoundSessionReferenceResolver;
use Pimcore\Bundle\PimcoreAgentBundle\Service\AgentSessionServiceInterface;
use Pimcore\Bundle\PimcoreAgentBundle\Service\TaskContextResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Exception\InvalidMcpToolArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Authenticator\Mcp\McpAccessTokenAuthenticator;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use UnexpectedValueException;

class ProposeImportConfigToolTest extends Unit
{
    private const string SESSION = 'session-7';

    protected function _before(): void
    {
        // the Agent bundle is an optional integration this repository cannot install
        if (!interface_exists(AgentSessionServiceInterface::class)) {
            static::markTestSkipped('Agent bundle not installed');
        }
    }

    public function testAnUnknownNameIsProposedAsNewAndOnlyRecorded(): void
    {
        $recorded = [];
        $sessions = $this->createMock(AgentSessionServiceInterface::class);
        $sessions->expects($this->once())
            ->method('setProposalData')
            ->willReturnCallback(
                static function (string $session, string $id, array $data, int $user) use (&$recorded): void {
                    $recorded = [$session, $data, $user];
                }
            );

        $result = $this->tool($sessions, static fn (): null => null)
            ->execute('dealer-feed', $this->complete());

        static::assertFalse($result->isError);
        static::assertSame(self::SESSION, $recorded[0]);
        static::assertSame(42, $recorded[2]);
        static::assertTrue($recorded[1]['isNew']);
        static::assertSame('dealer-feed', $recorded[1]['subjectRef']);
        static::assertSame(ImportConfigPolicy::SUBJECT_TYPE, $recorded[1]['subjectType']);
        $builder = new ImportConfigProposalBuilder(new ImportConfigVocabulary([]));
        static::assertSame($builder->build('dealer-feed', $this->complete(), null), $recorded[1]['subjectState']);
        static::assertSame('dealer-feed', $this->payload($result)['name']);
    }

    public function testAChangeToAStoredConfigurationIsNotNewAndWritesNothing(): void
    {
        $stored = $this->createMock(Configuration::class);
        $stored->method('getName')->willReturn('dealer-feed');
        $stored->method('getType')->willReturn(ImportConfigPolicy::CONFIG_TYPE);
        $stored->method('getConfiguration')->willReturn($this->complete());
        $stored->expects($this->never())->method('save');

        $recorded = [];
        $sessions = $this->createMock(AgentSessionServiceInterface::class);
        $sessions->expects($this->once())
            ->method('setProposalData')
            ->willReturnCallback(static function (string $session, string $id, array $data) use (&$recorded): void {
                $recorded = $data;
            });

        $result = $this->tool($sessions, static fn (): Configuration => $stored)
            ->execute('dealer-feed', ['interpreterConfig' => ['type' => 'xml']]);

        static::assertFalse($result->isError);
        static::assertFalse($recorded['isNew']);
        static::assertSame('xml', $recorded['subjectState']['interpreterConfig']['type']);
    }

    public function testAFailedReadIsAnErrorNotANewConfiguration(): void
    {
        $sessions = $this->createMock(AgentSessionServiceInterface::class);
        $sessions->expects($this->never())->method('setProposalData');

        $failingRead = static fn (): never => throw new UnexpectedValueException('storage unavailable');
        $result = $this->tool($sessions, $failingRead)->execute('dealer-feed', $this->complete());

        static::assertTrue($result->isError);
        static::assertSame('internal_error', $this->payload($result)['code']);
    }

    public function testAnArgumentErrorIsAnInvalidRequestNotAnInternalError(): void
    {
        $sessions = $this->createMock(AgentSessionServiceInterface::class);
        $sessions->expects($this->never())->method('setProposalData');

        $badArgument = static fn (): never => throw new InvalidMcpToolArgumentException('name must not be empty');
        $result = $this->tool($sessions, $badArgument)->execute('dealer-feed', $this->complete());

        static::assertTrue($result->isError);
        static::assertSame('invalid_request', $this->payload($result)['code']);
    }

    public function testARefusalComesBackAsAnErrorAndRecordsNothing(): void
    {
        $sessions = $this->createMock(AgentSessionServiceInterface::class);
        $sessions->expects($this->never())->method('setProposalData');

        $result = $this->tool($sessions, static fn (): null => null)
            ->execute('dealer-feed', ['loaderConfig' => ['type' => 'asset']]);

        static::assertTrue($result->isError);
        static::assertSame('invalid_request', $this->payload($result)['code']);
        static::assertStringContainsString('A new configuration needs', $this->payload($result)['error']);
    }

    public function testAUserWhoMayNotConfigureImportsIsRefused(): void
    {
        $sessions = $this->createMock(AgentSessionServiceInterface::class);
        $sessions->expects($this->never())->method('setProposalData');

        $result = $this->tool($sessions, static fn (): null => null, allowed: false)
            ->execute('dealer-feed', $this->complete());

        static::assertTrue($result->isError);
        static::assertSame('permission_denied', $this->payload($result)['code']);
    }

    public function testACallOutsideAChatSessionRecordsNothing(): void
    {
        $sessions = $this->createMock(AgentSessionServiceInterface::class);
        $sessions->expects($this->never())->method('setProposalData');

        $result = $this->tool($sessions, static fn (): null => null, session: null)
            ->execute('dealer-feed', $this->complete());

        static::assertTrue($result->isError);
        static::assertSame('No chat session context.', $this->payload($result)['error']);
    }

    private function tool(
        AgentSessionServiceInterface $sessions,
        Closure $find,
        bool $allowed = true,
        ?string $session = self::SESSION,
    ): ProposeImportConfigTool {
        $user = $this->createStub(UserInterface::class);
        $user->method('isAllowed')->willReturn($allowed);
        $user->method('getId')->willReturn(42);
        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturn($user);

        $resolver = $this->createStub(ProposalResolverInterface::class);
        $resolver->method('getType')->willReturn('subject-update');
        $resolver->method('getWidgetType')->willReturn('proposal-subject');

        $request = new Request();
        $request->attributes->set(McpAccessTokenAuthenticator::REQUEST_ATTR_REFERENCE, $session);
        $requests = new RequestStack();
        $requests->push($request);

        $errors = $this->createStub(McpToolErrorHandlerInterface::class);
        $errors->method('handle')->willReturn('Something went wrong.');

        return new ProposeImportConfigTool(
            $sessions,
            new ProposalWidgetEmitter(
                new ProposalResolverRegistry([$resolver]),
                $this->createStub(TaskContextResolverInterface::class),
            ),
            new BoundSessionReferenceResolver($requests),
            $security,
            $errors,
            new ImportConfigProposalBuilder(new ImportConfigVocabulary([])),
            $find,
        );
    }

    /** @return array<string, mixed> */
    private function payload(CallToolResult $result): array
    {
        $content = $result->content[0];
        static::assertInstanceOf(TextContent::class, $content);

        return json_decode($content->text, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function complete(): array
    {
        return [
            'loaderConfig' => ['type' => 'asset', 'settings' => ['assetPath' => '/feeds/cars.json']],
            'interpreterConfig' => ['type' => 'json'],
            'resolverConfig' => [
                'dataObjectClassId' => 'CAR',
                'loadingStrategy' => ['type' => 'notLoad'],
                'createLocationStrategy' => ['type' => 'staticPath', 'settings' => ['path' => '/import']],
                'locationUpdateStrategy' => ['type' => 'noChange'],
                'publishingStrategy' => ['type' => 'noChangeUnpublishNew'],
            ],
        ];
    }
}
