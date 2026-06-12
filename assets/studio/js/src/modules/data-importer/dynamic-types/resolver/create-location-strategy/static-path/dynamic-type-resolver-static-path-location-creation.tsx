/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from 'react';
import { DynamicTypeResolverAbstract } from '../../common/dynamic-type-resolver-abstract';
import { injectable } from '@pimcore/studio-ui-bundle/app';
import { StaticPathCreateLocationStrategyResolverSettings } from './static-path-create-location-strategy-resolver-settings';

@injectable()
export class DynamicTypeResolverStaticPathLocationCreation extends DynamicTypeResolverAbstract {
    readonly id = 'creation.staticPath';
    readonly value = 'staticPath';
    readonly label = 'data-importer.resolver.location-strategy.staticPath';
    readonly group = 'createLocation';
    renderSettings() {
        return <StaticPathCreateLocationStrategyResolverSettings />;
    }
}
