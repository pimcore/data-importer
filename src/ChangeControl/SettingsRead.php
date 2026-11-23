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

namespace Pimcore\Bundle\DataImporterBundle\ChangeControl;

use Closure;
use function error_reporting;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use TypeError;

/**
 * Lets an importer component read proposed settings the way an import would, and says what it
 * could not read.
 *
 * @internal
 */
final class SettingsRead
{
    /** what error_reporting() keeps inside an @-silenced expression */
    private const int LEVELS_AT_SILENCE = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR
        | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE;

    /**
     * @param string $where   the document path the problem is reported at
     * @param string $subject the component, e.g. 'the "http" loader'
     * @param Closure(): mixed $read
     *
     * @return string|null null when the component accepts its settings
     */
    public static function problem(string $where, string $subject, Closure $read): ?string
    {
        // a setting the component reads but the proposal lacks fails the import just as an empty one;
        // a deprecation or an error the component silences with @ does not
        $reported = error_reporting();
        set_error_handler(static function (int $severity, string $message) use ($reported): bool {
            $silenced = ($reported & ~self::LEVELS_AT_SILENCE) !== 0
                && (error_reporting() & ~self::LEVELS_AT_SILENCE) === 0;
            if ($silenced || ($severity & (E_DEPRECATED | E_USER_DEPRECATED)) !== 0) {
                return false;
            }

            throw new LoaderSettingsReadException($message, 0, $severity);
        });

        try {
            $read();
        } catch (InvalidConfigurationException $e) {
            return $where . ': ' . $e->getMessage();
        } catch (LoaderSettingsReadException $e) {
            return sprintf('%s: %s cannot read its settings: %s.', $where, $subject, $e->getMessage());
        } catch (TypeError) {
            return sprintf('%s: a setting of %s is missing or has the wrong type.', $where, $subject);
        } finally {
            restore_error_handler();
        }

        return null;
    }
}
