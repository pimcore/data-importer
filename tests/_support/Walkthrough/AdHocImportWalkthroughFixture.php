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

use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\User;
use Pimcore\Model\User\Workspace\DataObject as ObjectWorkspace;
use Pimcore\Security\User\User as SecurityUser;
use Pimcore\Tool;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * What a run works on: its class, its folders and its user, who has every right in the folder except writing a
 * language below locked/.
 */
final readonly class AdHocImportWalkthroughFixture
{
    private function __construct(
        public ClassDefinition $class,
        public User $user,
        public int $folderId,
        public string $language,
    ) {
    }

    /**
     * @param string|null $updatedSku the object a run changes, if it does
     */
    public static function create(
        string $run,
        string $lockedSku,
        ?string $updatedSku,
        TokenStorageInterface $tokens
    ): self {
        $class = ClassDefinition::getByName(AdHocImportWalkthrough::CLASS_NAME) ?? self::createClass();

        $root = AdHocImportWalkthrough::ROOT . '/' . $run;
        $folder = Service::createFolderByPath($root);
        $locked = Service::createFolderByPath($root . '/locked');
        self::createObject($lockedSku, $locked, 'Locked');
        if ($updatedSku !== null) {
            self::createObject($updatedSku, $folder, 'Before the import');
        }

        $user = self::createUser($run, $folder, $locked);
        $securityUser = new SecurityUser($user);
        $tokens->setToken(new UsernamePasswordToken($securityUser, 'pimcore_studio', $securityUser->getRoles()));

        return new self($class, $user, (int) $folder->getId(), (string) (Tool::getValidLanguages()[0] ?? 'en'));
    }

    private static function createUser(string $run, DataObject\Folder $folder, DataObject\Folder $locked): User
    {
        $rights = ['list' => true, 'view' => true, 'create' => true, 'save' => true, 'publish' => true];

        $user = new User();
        $user->setParentId(0);
        $user->setName('ad-hoc-walkthrough-' . $run);
        $user->setActive(true);
        $user->setAdmin(false);
        $user->setPermission('objects', true);
        $user->save();
        $user->setWorkspacesObject([
            self::workspace($folder, $rights),
            self::workspace($locked, [...$rights, 'lEdit' => 'none']),
        ]);
        $user->save();

        return $user;
    }

    /**
     * @param array<string, bool|string> $rights
     */
    private static function workspace(DataObject\Folder $folder, array $rights): ObjectWorkspace
    {
        $workspace = new ObjectWorkspace();
        $workspace->setCid((int) $folder->getId());
        $workspace->setCpath($folder->getRealFullPath());
        $workspace->setValues($rights);

        return $workspace;
    }

    private static function createObject(string $sku, DataObject\Folder $folder, string $name): void
    {
        $class = '\\Pimcore\\Model\\DataObject\\' . AdHocImportWalkthrough::CLASS_NAME;
        /** @var Concrete $object */
        $object = new $class();
        $object->setParent($folder);
        $object->setKey($sku);
        $object->setPublished(true);
        $object->set('sku', $sku);
        $object->set('name', $name);
        $object->save();
    }

    private static function createClass(): ClassDefinition
    {
        $panel = new ClassDefinition\Layout\Panel();
        $panel->setName('pimcore_root');

        foreach (['sku', 'name'] as $field) {
            $input = new ClassDefinition\Data\Input();
            $input->setName($field);
            $input->setTitle($field);
            $panel->addChild($input);
        }

        $quantity = new ClassDefinition\Data\Numeric();
        $quantity->setName('quantity');
        $quantity->setTitle('quantity');
        $panel->addChild($quantity);

        $title = new ClassDefinition\Data\Input();
        $title->setName('title');
        $title->setTitle('title');
        $localized = new ClassDefinition\Data\Localizedfields();
        $localized->setName('localizedfields');
        $localized->addChild($title);
        $panel->addChild($localized);

        $definition = new ClassDefinition();
        $definition->setName(AdHocImportWalkthrough::CLASS_NAME);
        $definition->setLayoutDefinitions($panel);
        $definition->save();

        return $definition;
    }
}
