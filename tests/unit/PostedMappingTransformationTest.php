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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\Mapping\CalculateTransformationResultTypeController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\Mapping\LoadTransformationResultController;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Schema\CalculateTransformationResultTypeParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultParameters;
use Pimcore\Bundle\DataImporterBundle\Tests\UnitTester;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\EventSubscriber\ApiExceptionSubscriber;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Model\User;
use Pimcore\Security\User\User as SecurityUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Throwable;

class PostedMappingTransformationTest extends Unit
{
    private const USER_ID = 4712;

    private const STUDIO_API = '/pimcore-studio/api';

    protected UnitTester $tester;

    protected function _before(): void
    {
        $this->logInWith([PermissionConstants::PLUGIN_DATA_IMPORTER_ADAPTER]);
    }

    protected function _after(): void
    {
        $this->tokenStorage()->setToken(null);
    }

    public function testThePreviewTransformsTheGivenRecord(): void
    {
        $response = $this->preview(
            [
                [
                    'dataSourceIndex' => ['sku'],
                    'transformationPipeline' => [['type' => 'trim', 'settings' => ['mode' => 'both']]],
                ],
                [
                    'dataSourceIndex' => ['name', 'color'],
                    'transformationPipeline' => [['type' => 'combine', 'settings' => ['glue' => ' / ']]],
                ],
                ['dataSourceIndex' => ['missing']],
            ],
            ['sku' => '  A-100 ', 'name' => 'Chair', 'color' => 'red']
        );

        $this->assertSame(['A-100', 'Chair / red', '-- EMPTY --'], $response['transformationResultPreviews']);
    }

    public function testTheTypeFollowsThePipeline(): void
    {
        $this->assertSame('default', $this->type(['dataSourceIndex' => ['sku']]));
        $this->assertSame('array', $this->type(['dataSourceIndex' => ['a', 'b']]));
        $numeric = ['dataSourceIndex' => ['price'], 'transformationPipeline' => [['type' => 'numeric']]];
        $this->assertSame('numeric', $this->type($numeric));
    }

    public function testAPreviewRequestNeedsItsMappingAndItsRow(): void
    {
        $serializer = $this->tester->grabService('serializer');
        $this->assertInstanceOf(DenormalizerInterface::class, $serializer);

        $payloads = ['mappingConfig' => ['dataRow' => ['sku' => 'A-100']], 'dataRow' => ['mappingConfig' => []]];
        foreach ($payloads as $missing => $payload) {
            $refused = false;

            try {
                $serializer->denormalize($payload, TransformationResultParameters::class);
            } catch (MissingConstructorArgumentsException) {
                $refused = true;
            }
            $this->assertTrue($refused, sprintf('a request without %s is refused', $missing));
        }
    }

    public function testAnUnknownOperatorIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->type([
            'dataSourceIndex' => ['sku'],
            'transformationPipeline' => [['type' => 'no-such-operator']],
        ]);
    }

    public function testAPreviewRefusesAnOperatorThatWritesOrFetches(): void
    {
        $refusal = $this->refusalOf(fn () => $this->preview(
            [[
                'dataSourceIndex' => ['image'],
                'transformationPipeline' => [['type' => 'importAsset', 'settings' => ['parentFolder' => '/preview']]],
            ]],
            ['image' => 'https://example.com/image.png']
        ));

        $response = $this->asStudioApiError($refusal, '/bundle/data-importer/mapping/transformation-result');

        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('"importAsset"', $body['message']);
    }

    public function testTheDataHubAdminMayPreview(): void
    {
        $this->logInWith([PermissionConstants::PLUGIN_DATA_IMPORTER_ADMIN]);

        $this->assertSame('default', $this->type(['dataSourceIndex' => ['sku']]));
    }

    public function testThePreviewIsRefusedWithoutTheImporterPermission(): void
    {
        $this->logInWith([PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG]);

        $this->expectException(ForbiddenException::class);

        $this->preview([['dataSourceIndex' => ['sku']]], ['sku' => 'A-100']);
    }

    public function testTheTypeIsRefusedWithoutTheImporterPermission(): void
    {
        $this->logInWith([PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG]);

        $this->expectException(ForbiddenException::class);

        $this->type(['dataSourceIndex' => ['sku']]);
    }

    private function logInWith(array $permissions): void
    {
        $user = new User();
        $user->setId(self::USER_ID);
        $user->setName('posted-mapping-preview');
        $user->setPermissions($permissions);
        $securityUser = new SecurityUser($user);

        $this->tokenStorage()->setToken(
            new UsernamePasswordToken($securityUser, 'pimcore_studio', $securityUser->getRoles())
        );
    }

    private function tokenStorage(): TokenStorageInterface
    {
        return $this->tester->grabService('security.token_storage');
    }

    private function refusalOf(callable $request): Throwable
    {
        try {
            $request();
        } catch (Throwable $refusal) {
            return $refusal;
        }

        $this->fail('the request is refused');
    }

    /**
     * What a Studio client receives for an exception; dev adds the stack trace as detail.
     */
    private function asStudioApiError(Throwable $exception, string $route): Response
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create(self::STUDIO_API . $route, 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );
        (new ApiExceptionSubscriber('dev', self::STUDIO_API))->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response, 'Studio answers with its JSON error, not the framework error page');

        return $response;
    }

    private function preview(array $mappingConfig, array $dataRow): array
    {
        $controller = $this->tester->grabService(LoadTransformationResultController::class);
        $response = $controller->loadTransformationResult(
            new TransformationResultParameters($mappingConfig, $dataRow)
        );

        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function type(array $mappingEntry): string
    {
        $controller = $this->tester->grabService(CalculateTransformationResultTypeController::class);
        $response = $controller->calculateTransformationResultType(
            new CalculateTransformationResultTypeParameters($mappingEntry)
        );

        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR)['type'];
    }
}
