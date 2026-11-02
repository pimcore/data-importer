<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Utils\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * Expression language used by the symfony expression operator. Functions can be contributed by
 * services tagged with `pimcore.datahub.data_importer.expression_language_provider`. The built-in
 * `constant()` function is disabled because it would expose internal PHP constants to import configurations.
 */
class DataImporterExpressionLanguage extends ExpressionLanguage
{
    /**
     * @param iterable<ExpressionFunctionProviderInterface> $providers
     */
    public function __construct(iterable $providers = [])
    {
        parent::__construct();

        $this->register('constant', self::rejectConstant(...), self::rejectConstant(...));

        foreach ($providers as $provider) {
            $this->registerProvider($provider);
        }
    }

    private static function rejectConstant(): never
    {
        throw new SyntaxError('The `constant` function is not available.');
    }
}
