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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun;

/**
 * Why a value of the file cannot go into its field.
 *
 * @internal
 */
enum ValueError: string
{
    case NotANumber = 'not-a-number';
    case NotADate = 'not-a-date';
    case NotYesOrNo = 'not-yes-no';
    case NotAnOption = 'not-an-option';

    // a published object cannot be saved without it
    case Required = 'required';
    case Invalid = 'invalid';

    public static function ofConversion(?string $conversion): self
    {
        return match ($conversion) {
            'numeric' => self::NotANumber,
            'date' => self::NotADate,
            'boolean' => self::NotYesOrNo,
            default => self::Invalid,
        };
    }
}
