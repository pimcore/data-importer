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
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportDryRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportServiceInterface;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\User;
use Pimcore\Model\User\Workspace\DataObject as ObjectWorkspace;
use Pimcore\Security\User\User as SecurityUser;
use Pimcore\Tool;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * One file after the other through an ad hoc import, whichever way it writes on the Pimcore it is booted in: into the
 * objects, or through a Change Control branch. Every check reads back from the database. The data stays, so a run can
 * be opened in Studio afterwards.
 */
final class AdHocImportWalkthrough
{
    public const string CLASS_NAME = 'AdHocImportWalkthrough';

    public const string ROOT = '/Ad hoc import walkthrough';

    private const string HEADER = "sku;name;title;quantity\n";

    private const string LOCKED_SKU = 'W-9';

    private const string UPDATED_SKU = 'W-8';

    private const string FILE_NAME = 'products.csv';

    /** @var list<string> */
    private array $lines = [];

    /** @var list<string> */
    private array $failed = [];

    private ?ClassDefinition $class = null;

    private ?User $user = null;

    private int $folderId = 0;

    private string $language = '';

    private bool $throughBranch = false;

    /**
     * @param \Closure(string): void $work works off what the import of the file queued, until it no longer goes on
     * @param bool|null $expectBranch whether the rows must go through a branch; null takes the way the Pimcore has
     * @param bool $withUpdates also change an existing object, which needs a search index to save it again
     */
    public function __construct(
        private readonly AdHocImportServiceInterface $uploads,
        private readonly AdHocImportRunServiceInterface $service,
        private readonly TokenStorageInterface $tokens,
        private readonly \Closure $work,
        public readonly string $run,
        private readonly ?bool $expectBranch,
        private readonly bool $withUpdates = false,
    ) {
    }

    /**
     * What a run imports, for a dry run that boots nothing.
     *
     * @return list<string>
     */
    public static function plan(): array
    {
        return [
            'Rows with an error are left out, the others written (a key twice in the file)',
            'A value that cannot be saved: the row is written without it',
            'A row the user may not write: a partial result, or with a branch a choice, then merged without it',
            'With a branch: an import nobody goes on with is discarded and writes nothing',
            'An existing object is changed and stays published',
        ];
    }

    public static function newRun(): string
    {
        return date('YmdHis') . '-' . bin2hex(random_bytes(2));
    }

    public function user(): ?User
    {
        return $this->user;
    }

    /**
     * @return list<WalkthroughStep>
     */
    public function run(): array
    {
        $this->setUp();

        $steps = [
            ['Rows with an error are left out, the others written', $this->leaveOutRowsWithErrors(...), false],
            ['A row goes in without the value that cannot be saved', $this->importWithoutInvalidValues(...), false],
            ['A row the user may not write does not hold the others', $this->refusedRow(...), false],
            ['An import nobody goes on with writes nothing', $this->discarded(...), true],
        ];
        if ($this->withUpdates) {
            $steps[] = ['An existing object is changed and stays published', $this->updateExisting(...), false];
        }

        $results = [];
        foreach ($steps as [$title, $step, $branchOnly]) {
            if ($branchOnly && !$this->throughBranch) {
                continue;
            }

            $this->lines = [];
            $this->failed = [];

            try {
                $step();
            } catch (\Throwable $throwable) {
                $this->failed[] = sprintf('%s: %s', $throwable::class, $throwable->getMessage());
            }

            $results[] = new WalkthroughStep(count($results) + 1, $title, $this->lines, $this->failed);
            if ($this->failed !== []) {
                break;
            }
        }

        return $results;
    }

    /**
     * Its class, its folders and its user, who has every right in the folder except writing a language below locked/.
     */
    public function setUp(): void
    {
        if ($this->user instanceof User) {
            return;
        }

        $this->class = ClassDefinition::getByName(self::CLASS_NAME) ?? $this->createClass();
        $this->language = (string) (Tool::getValidLanguages()[0] ?? 'en');

        $root = self::ROOT . '/' . $this->run;
        $folder = Service::createFolderByPath($root);
        $locked = Service::createFolderByPath($root . '/locked');
        $this->folderId = (int) $folder->getId();
        $this->createObject(self::LOCKED_SKU, $locked, 'Locked');
        if ($this->withUpdates) {
            $this->createObject(self::UPDATED_SKU, $folder, 'Before the import');
        }

        $user = new User();
        $user->setParentId(0);
        $user->setName('ad-hoc-walkthrough-' . $this->run);
        $user->setActive(true);
        $user->setAdmin(false);
        $user->setPermission('objects', true);
        $user->save();
        $user->setWorkspacesObject([
            $this->workspace($folder, ['list' => true, 'view' => true, 'create' => true, 'save' => true, 'publish' => true]),
            $this->workspace(
                $locked,
                ['list' => true, 'view' => true, 'create' => true, 'save' => true, 'publish' => true, 'lEdit' => 'none']
            ),
        ]);
        $user->save();
        $this->user = $user;

        $securityUser = new SecurityUser($user);
        $this->tokens->setToken(new UsernamePasswordToken($securityUser, 'pimcore_studio', $securityUser->getRoles()));
    }

    /**
     * @param array<string, bool|string> $rights
     */
    private function workspace(DataObject\Folder $folder, array $rights): ObjectWorkspace
    {
        $workspace = new ObjectWorkspace();
        $workspace->setCid((int) $folder->getId());
        $workspace->setCpath($folder->getRealFullPath());
        $workspace->setValues($rights);

        return $workspace;
    }

    private function createObject(string $sku, DataObject\Folder $folder, string $name): void
    {
        $class = '\\Pimcore\\Model\\DataObject\\' . self::CLASS_NAME;
        /** @var Concrete $object */
        $object = new $class();
        $object->setParent($folder);
        $object->setKey($sku);
        $object->setPublished(true);
        $object->set('sku', $sku);
        $object->set('name', $name);
        $object->save();
    }

    private function createClass(): ClassDefinition
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
        $definition->setName(self::CLASS_NAME);
        $definition->setLayoutDefinitions($panel);
        $definition->save();

        return $definition;
    }

    /**
     * Uploads the rows as a file, starts its import and works it off.
     *
     * @param list<string> $rows the lines of the file below its header
     *
     * @return array{string, AdHocImportRunResponse} the file and how its import started
     */
    public function import(array $rows, string $errorMode = AdHocImportDryRunServiceInterface::SKIP_ROWS): array
    {
        $this->setUp();
        $fileId = $this->upload(self::HEADER . implode("\n", $rows) . "\n");
        $started = $this->service->start($fileId, $this->parameters($errorMode));
        $this->throughBranch = $started->isThroughBranch();
        ($this->work)($fileId);

        return [$fileId, $started];
    }

    private function upload(string $csv): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('ad-hoc-walkthrough-', true);
        file_put_contents($path, $csv);

        try {
            $fileId = $this->uploads
                ->upload(new UploadedFile($path, self::FILE_NAME, null, null, true), $this->folderId)
                ->getFileId();
        } finally {
            @unlink($path);
        }

        return $fileId ?? throw new \RuntimeException('The upload was refused.');
    }

    private function parameters(string $errorMode): AdHocImportRunParameters
    {
        return new AdHocImportRunParameters(
            new AdHocImportDryRunParameters(
                $this->folderId,
                (string) $this->class?->getId(),
                AdHocImportDryRunParameters::MATCH_BY_FIELD,
                'sku',
                'sku',
                columns: [
                    ['column' => 'sku', 'field' => 'sku'],
                    ['column' => 'name', 'field' => 'name'],
                    ['column' => 'title', 'field' => 'title', 'language' => $this->language],
                    ['column' => 'quantity', 'field' => 'quantity'],
                ]
            ),
            $errorMode,
            self::FILE_NAME
        );
    }

    public function read(string $sku): ?Concrete
    {
        $object = DataObject::getByPath(self::ROOT . '/' . $this->run . '/' . $sku, ['force' => true]);

        return $object instanceof Concrete ? $object : null;
    }

    private function readLocked(): ?Concrete
    {
        return $this->read('locked/' . self::LOCKED_SKU);
    }

    private function leaveOutRowsWithErrors(): void
    {
        [$fileId, $started] = $this->import(['W-1;Chair;Stuhl;1', 'W-2;Lamp;Lampe;2', 'W-2;Again;Nochmal;3']);
        $this->lines[] = $this->throughBranch ? 'Writes through a Change Control branch' : 'Writes directly';
        if ($this->expectBranch !== null) {
            $this->check($this->throughBranch === $this->expectBranch, 'the import writes the way this Pimcore should');
        }

        $this->counts($started, ['total' => 2, 'skipped' => 1]);
        $this->finished($fileId, ['created' => 2, 'updated' => 0, 'failed' => 0]);

        $chair = $this->read('W-1');
        $this->check($chair !== null, 'W-1 exists');
        $this->check($chair?->get('name') === 'Chair', 'W-1 holds its name');
        $this->check($chair?->get('title', $this->language) === 'Stuhl', 'W-1 holds its title');
        $this->check((float) $chair?->get('quantity') === 1.0, 'W-1 holds its quantity');
        $this->check($chair?->isPublished() === false, 'W-1 stays unpublished');
        $this->check($chair?->getUserModification() === $this->user?->getId(), 'W-1 was written as the user');
        $this->check($this->read('W-2')?->get('name') === 'Lamp', 'the first W-2 was written, the second left out');
        $this->lines[] = sprintf('W-1 name %s, published no', (string) $chair?->get('name'));

        $this->service->discard($fileId);
    }

    private function importWithoutInvalidValues(): void
    {
        [$fileId, $started] = $this->import(['W-3;Bench;Bank;abc'], AdHocImportDryRunServiceInterface::WITHOUT_INVALID_VALUES);

        $this->counts($started, ['total' => 1, 'skipped' => 0]);
        $this->finished($fileId, ['created' => 1, 'failed' => 0]);
        $bench = $this->read('W-3');
        $this->check($bench?->get('name') === 'Bench', 'W-3 is written');
        $this->check($bench?->get('quantity') === null, 'W-3 goes without the value that is no number');

        $this->service->discard($fileId);
    }

    private function refusedRow(): void
    {
        [$fileId] = $this->import(['W-4;Desk;Tisch;1', self::LOCKED_SKU . ';Changed;Gesperrt;1']);

        if ($this->throughBranch) {
            $status = $this->service->status($fileId);
            $this->check($status->getState() === 'decision', 'the import waits for the choice');
            $this->check($this->read('W-4') === null, 'nothing is merged yet');
            $this->failedRow($status);
            $this->service->mergeLeavingOut($fileId);
            ($this->work)($fileId);
        }

        $this->finished($fileId, ['created' => 1, 'updated' => 0, 'failed' => 1]);
        $this->check($this->read('W-4')?->get('name') === 'Desk', 'the row that may be written is saved');
        $this->check($this->readLocked()?->get('name') === 'Locked', 'the refused object is unchanged');
        $this->failedRow($this->service->status($fileId));
        $this->check(
            str_contains($this->service->failedRows($fileId), self::LOCKED_SKU),
            'the refused row is in the download'
        );

        $this->service->discard($fileId);
    }

    private function discarded(): void
    {
        [$fileId] = $this->import(['W-5;Shelf;Regal;1', self::LOCKED_SKU . ';Changed;Gesperrt;1']);

        $this->check($this->service->status($fileId)->getState() === 'decision', 'the import waits for the choice');
        $this->service->discard($fileId);

        $this->check($this->read('W-5') === null, 'nothing was merged');

        try {
            $this->service->status($fileId);
            $this->check(false, 'the import is forgotten');
        } catch (NotFoundHttpException) {
            $this->lines[] = 'The import is forgotten';
        }
    }

    private function updateExisting(): void
    {
        [$fileId] = $this->import([self::UPDATED_SKU . ';After the import;Titel;5']);

        $this->finished($fileId, ['created' => 0, 'updated' => 1, 'failed' => 0]);
        $object = $this->read(self::UPDATED_SKU);
        $this->check($object?->get('name') === 'After the import', 'the object is changed');
        $this->check($object?->isPublished() === true, 'the object stays published');

        $this->service->discard($fileId);
    }

    /**
     * @param array<string, int> $expected
     */
    private function counts(AdHocImportRunResponse $run, array $expected): void
    {
        foreach ($expected as $count => $value) {
            $this->check($run->{'get' . ucfirst($count)}() === $value, sprintf('%s is %d', $count, $value));
        }
    }

    /**
     * @param array<string, int> $expected
     */
    private function finished(string $fileId, array $expected): void
    {
        $status = $this->service->status($fileId);
        $this->check($status->getState() === 'done', 'the import has finished');
        $this->counts($status, $expected);
    }

    private function failedRow(AdHocImportRunResponse $status): void
    {
        $this->check(
            array_column($status->getFailures(), 'matchValue') === [self::LOCKED_SKU],
            'the refused row is listed'
        );
    }

    private function check(bool $holds, string $what): void
    {
        if ($holds) {
            $this->lines[] = $what;

            return;
        }

        $this->failed[] = $what;
    }
}
