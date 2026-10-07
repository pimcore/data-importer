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
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\TransformationServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Tests\UnitTester;
use Pimcore\Model\Tool\SettingsStore;
use Pimcore\Model\User;
use Pimcore\Security\User\User as SecurityUser;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class StoredMappingTransformationTest extends Unit
{
    private const SETTINGS_STORE_SCOPE = 'pimcore_data_hub';

    private const USER_ID = 4711;

    protected UnitTester $tester;

    private string $name;

    protected function _before(): void
    {
        $this->name = uniqid('stored_mapping_');
        $now = time();
        $configuration = [
            'general' => [
                'active' => true,
                'type' => 'dataImporterDataObject',
                'name' => $this->name,
                'path' => '',
                'createDate' => $now,
                'modificationDate' => $now,
            ],
            'interpreterConfig' => [
                'type' => 'csv',
                'settings' => [
                    'skipFirstRow' => true,
                    'saveHeaderName' => true,
                    'delimiter' => ',',
                    'enclosure' => '"',
                    'escape' => '\\',
                ],
            ],
            'mappingConfig' => [
                [
                    'dataSourceIndex' => ['sku'],
                    'transformationPipeline' => [['type' => 'trim', 'settings' => ['mode' => 'both']]],
                ],
                [
                    'dataSourceIndex' => ['name', 'color'],
                    'transformationPipeline' => [['type' => 'combine', 'settings' => ['glue' => ' / ']]],
                ],
            ],
        ];

        SettingsStore::set(
            $this->name,
            json_encode($configuration, JSON_THROW_ON_ERROR),
            SettingsStore::TYPE_STRING,
            self::SETTINGS_STORE_SCOPE
        );
    }

    protected function _after(): void
    {
        $this->tester->grabService('security.token_storage')->setToken(null);
        $storage = $this->previewStorage();
        if ($storage->directoryExists($this->name)) {
            $storage->deleteDirectory($this->name);
        }
        SettingsStore::delete($this->name, self::SETTINGS_STORE_SCOPE);
    }

    public function testTheStoredMappingRunsOnTheUsersPreviewRecord(): void
    {
        $user = $this->logIn();
        $file = tempnam(sys_get_temp_dir(), 'di_preview_');
        file_put_contents($file, "sku,name,color\r\n  A-100 ,Chair,red\r\n B-200,Table,oak\r\n");

        try {
            $this->previewService()->writePreviewFile($this->name, $file, $user);
        } finally {
            unlink($file);
        }

        $response = $this->transformationService()->loadTransformationResultPreviews($this->name, null, 1);

        $this->assertSame(['B-200', 'Table / oak'], $response->getTransformationResultPreviews());
    }

    public function testTheTypeIsCalculatedForAStoredConfiguration(): void
    {
        $response = $this->transformationService()->calculateTransformationResultType(
            $this->name,
            ['dataSourceIndex' => ['price'], 'transformationPipeline' => [['type' => 'numeric']]]
        );

        $this->assertSame('numeric', $response->getType());
    }

    public function testAnUnknownConfigurationIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->transformationService()->calculateTransformationResultType(
            uniqid('missing_'),
            ['dataSourceIndex' => ['sku']]
        );
    }

    private function logIn(): User
    {
        $user = new User();
        $user->setId(self::USER_ID);
        $user->setName('stored-mapping-preview');
        $user->setAdmin(true);
        $securityUser = new SecurityUser($user);

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->tester->grabService('security.token_storage');
        $tokenStorage->setToken(new UsernamePasswordToken($securityUser, 'pimcore_studio', $securityUser->getRoles()));

        return $user;
    }

    private function transformationService(): TransformationServiceInterface
    {
        return $this->tester->grabService(TransformationServiceInterface::class);
    }

    private function previewService(): PreviewService
    {
        return $this->tester->grabService(PreviewService::class);
    }

    private function previewStorage(): FilesystemOperator
    {
        return $this->tester->grabService('pimcore.dataImporter.preview.storage');
    }
}
