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
use Doctrine\DBAL\Schema\Schema;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationChain;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Migrations\Version20261106170000;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\MappingSuggestionServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StaticResolverBundle\Db\DbResolver;
use Pimcore\Bundle\StaticResolverBundle\Lib\CacheResolver;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\DataObjectResolver;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Voter\UserPermissionVoter;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\CacheKeys;
use Pimcore\Cache;
use Pimcore\Db;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\User;
use Pimcore\Model\User\Workspace\DataObject as ObjectWorkspace;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;

/**
 * A saved non-admin user on an install that gets the right by the migration, while Studio still has the list of
 * rights cached from before it.
 */
class AdHocImportPermissionTest extends Unit
{
    private const RIGHT = PermissionConstants::PLUGIN_DATA_IMPORTER_AD_HOC_IMPORT;

    protected $tester;

    private Folder $folder;

    private User $importer;

    private User $withoutRight;

    private User $currentUser;

    private string $storage;

    private string $csv;

    private bool $cacheWasEnabled;

    private bool $cacheWroteImmediately;

    /**
     * @var array<string, mixed>|false
     */
    private array|false $definition;

    protected function _before(): void
    {
        $this->cacheWasEnabled = Cache::isEnabled();
        $this->cacheWroteImmediately = Cache::getForceImmediateWrite();
        Cache::enable();
        Cache::setForceImmediateWrite(true);

        $this->storage = sys_get_temp_dir() . '/' . uniqid('ad-hoc-permission-', true);
        mkdir($this->storage);
        $this->csv = $this->storage . '.csv';
        file_put_contents($this->csv, "sku;name\nA-1;Chair\nA-2;Table\nA-3;Lamp\n");

        $this->folder = new Folder();
        $this->folder->setKey(uniqid('ad-hoc-import-', true));
        $this->folder->setParentId(1);
        $this->folder->save();

        // the install as it was before the migration
        $this->definition = Db::get()->fetchAssociative(
            'SELECT `key`, `category` FROM users_permission_definitions WHERE `key` = ?',
            [self::RIGHT]
        );
        Db::get()->executeStatement('DELETE FROM users_permission_definitions WHERE `key` = ?', [self::RIGHT]);

        $this->importer = $this->saveUser('ad-hoc-importer', [self::RIGHT]);
        $this->withoutRight = $this->saveUser('ad-hoc-no-right', []);
    }

    protected function _after(): void
    {
        $this->importer->delete();
        $this->withoutRight->delete();
        $this->folder->delete();
        Db::get()->executeStatement('DELETE FROM users_permission_definitions WHERE `key` = ?', [self::RIGHT]);
        if ($this->definition !== false) {
            Db::get()->executeStatement(
                'INSERT INTO users_permission_definitions (`key`, `category`) VALUES (?, ?)',
                [$this->definition['key'], $this->definition['category']]
            );
        }
        Cache::remove(CacheKeys::USER_PERMISSIONS->value);
        Cache::setForceImmediateWrite($this->cacheWroteImmediately);
        if (!$this->cacheWasEnabled) {
            Cache::disable();
        }

        foreach (glob($this->storage . '/*/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->storage . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            @rmdir($directory);
        }
        @rmdir($this->storage);
        @unlink($this->csv);
    }

    public function testANonAdminWithTheRightMayImportOnceTheMigrationRanWithoutAnotherCacheClear(): void
    {
        $stale = Db::get()->fetchFirstColumn('SELECT `key` FROM users_permission_definitions');
        $this->assertNotContains(self::RIGHT, $stale);
        Cache::save($stale, CacheKeys::USER_PERMISSIONS->value, [], null, 0, true);
        $this->assertSame($stale, Cache::load(CacheKeys::USER_PERMISSIONS->value));

        $this->currentUser = $this->importer;
        $this->assertSame(
            UserPermissionVoter::ACCESS_ABSTAIN,
            $this->vote(),
            'the right is unknown to Studio before the migration'
        );

        $this->migrate();

        $this->assertFalse(Cache::load(CacheKeys::USER_PERMISSIONS->value), 'the migration dropped the cached list');
        $this->assertSame(UserPermissionVoter::ACCESS_GRANTED, $this->vote());

        $response = $this->service()->upload(
            new UploadedFile($this->csv, 'prices.csv', null, null, true),
            $this->folder->getId()
        );

        $this->assertNull($response->getRefusal());
        $this->assertNotNull($response->getFileId());
        $this->assertSame(3, $response->getRowCount());
        $this->assertFileExists(
            $this->storage . '/' . $this->importer->getId() . '/' . $response->getFileId() . '.csv'
        );
    }

    public function testANonAdminWithoutTheRightIsRefused(): void
    {
        $this->migrate();

        $this->currentUser = $this->withoutRight;

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage(self::RIGHT);
        $this->vote();
    }

    public function testTheRightAloneDoesNotOpenAFolderWithoutCreateAndSave(): void
    {
        $this->migrate();

        $folder = new Folder();
        $folder->setKey(uniqid('ad-hoc-elsewhere-', true));
        $folder->setParentId(1);
        $folder->save();
        $this->currentUser = $this->importer;

        try {
            $this->assertSame(UserPermissionVoter::ACCESS_GRANTED, $this->vote());
            $this->expectException(ForbiddenException::class);
            $this->service()->upload(new UploadedFile($this->csv, 'prices.csv', null, null, true), $folder->getId());
        } finally {
            $folder->delete();
        }
    }

    /**
     * @param string[] $rights
     */
    private function saveUser(string $name, array $rights): User
    {
        $workspace = new ObjectWorkspace();
        $workspace->setCid($this->folder->getId());
        $workspace->setCpath($this->folder->getRealFullPath());
        $workspace->setList(true);
        $workspace->setView(true);
        $workspace->setCreate(true);
        $workspace->setSave(true);

        $user = new User();
        $user->setParentId(0);
        $user->setName(uniqid($name . '-', true));
        $user->setActive(true);
        $user->setAdmin(false);
        $user->setPermission('objects', true);
        foreach ($rights as $right) {
            $user->setPermission($right, true);
        }
        $user->setWorkspacesObject([$workspace]);
        $user->save();

        $saved = User::getById($user->getId());
        $this->assertInstanceOf(User::class, $saved);
        $this->assertFalse($saved->isAdmin());

        return $saved;
    }

    private function migrate(): void
    {
        $migration = new Version20261106170000(Db::get(), new NullLogger());
        $schema = new Schema();
        $migration->up($schema);
        foreach ($migration->getSql() as $query) {
            Db::get()->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
        $migration->postUp($schema);
    }

    /**
     * A voter is built per request, as the container does.
     */
    private function vote(): int
    {
        return (new UserPermissionVoter(new CacheResolver(), new DbResolver(), $this->security()))
            ->vote(new NullToken(), null, [self::RIGHT]);
    }

    private function security(): SecurityServiceInterface
    {
        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturnCallback(fn (): User => $this->currentUser);

        return $security;
    }

    private function service(): AdHocImportService
    {
        return new AdHocImportService(
            AdHocCsvFiles::store($this->storage),
            AdHocCsvFiles::inspector(),
            $this->security(),
            new DataObjectResolver(),
            $this->createStub(DataTypeServiceInterface::class),
            $this->createStub(ClassDefinitionServiceInterface::class),
            $this->createStub(MappingSuggestionServiceInterface::class),
            $this->createStub(ClassDefinitionResolverInterface::class),
            new TransformationChain(
                new MappingConfigurationFactory(new MappingConfiguration(), [], []),
                $this->createStub(DataTypeServiceInterface::class)
            )
        );
    }
}
