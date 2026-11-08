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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion;

/**
 * The state of the AI stage of a suggestion request.
 *
 * @internal
 */
enum AiSuggestionStatus: string
{
    // no task was started: the stage is off, the agent is missing or nothing is open
    case None = 'none';
    case Running = 'running';
    case Finished = 'finished';
    case Failed = 'failed';
}
