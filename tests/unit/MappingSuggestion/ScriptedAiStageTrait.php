<?php declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\MappingSuggestion;

use LogicException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\AiSuggestionStage;
use Pimcore\Bundle\StaticResolverBundle\Models\Tool\TmpStoreResolverInterface;
use Pimcore\Contracts\PimcoreAgent\AgentTaskInfo;
use Pimcore\Contracts\PimcoreAgent\AgentTaskRequest;
use Pimcore\Contracts\PimcoreAgent\AgentTaskResult;
use Pimcore\Contracts\PimcoreAgent\AgentTaskServiceInterface;
use Pimcore\Contracts\PimcoreAgent\AgentTaskStatus;
use Pimcore\Model\Tool\TmpStore;
use Psr\Log\NullLogger;

/**
 * A stage whose agent is scripted, for tests of what is asked of the agent and what comes back.
 */
trait ScriptedAiStageTrait
{
    /**
     * A task service that remembers its requests and answers with the given output.
     */
    protected function agent(array $output = []): AgentTaskServiceInterface
    {
        return new class($output) implements AgentTaskServiceInterface {
            /** @var list<AgentTaskRequest> */
            public array $requests = [];

            public function __construct(private readonly array $output)
            {
            }

            public function start(AgentTaskRequest $request): AgentTaskInfo
            {
                $this->requests[] = $request;

                return new AgentTaskInfo('task-1', 'session-1', '', AgentTaskStatus::Running, null);
            }

            public function continueTask(
                string $previousTaskId,
                string $instructions,
                ?int $deadlineMinutes = null,
                ?int $maxAutoContinues = null,
                ?array $outputSchema = null
            ): AgentTaskInfo {
                throw new LogicException('not used');
            }

            public function get(string $taskId): AgentTaskInfo
            {
                throw new LogicException('not used');
            }

            public function waitFor(string $taskId, int $timeoutSeconds): AgentTaskResult
            {
                return new AgentTaskResult($taskId, AgentTaskStatus::Succeeded, ['output' => $this->output]);
            }

            public function cancel(string $taskId): void
            {
                // a scripted task has nothing running to cancel
            }
        };
    }

    /**
     * A stage on an in-memory temporary store.
     */
    protected function aiStage(
        ?AgentTaskServiceInterface $agent,
        bool $enabled = true,
        bool $sendSampleValues = false
    ): AiSuggestionStage {
        $store = new class() {
            public array $entries = [];
        };

        $tmpStore = $this->createStub(TmpStoreResolverInterface::class);
        $tmpStore->method('add')->willReturnCallback(static function (string $id, mixed $data) use ($store): bool {
            $store->entries[$id] = $data;

            return true;
        });
        $tmpStore->method('get')->willReturnCallback(static function (string $id) use ($store): ?TmpStore {
            if (!isset($store->entries[$id])) {
                return null;
            }

            $entry = new TmpStore();
            $entry->setData($store->entries[$id]);

            return $entry;
        });

        return new AiSuggestionStage($agent, $tmpStore, new NullLogger(), $enabled, $sendSampleValues);
    }
}
