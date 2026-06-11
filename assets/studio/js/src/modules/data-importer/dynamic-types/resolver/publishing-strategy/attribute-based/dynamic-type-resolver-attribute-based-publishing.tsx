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
import {
    DynamicTypeResolverAbstract,
    DynamicTypeResolverRenderProps,
} from '../../common/dynamic-type-resolver-abstract';
import { injectable } from '@pimcore/studio-ui-bundle/app';
import { AttributeBasedPublishingStrategyResolverSettings } from './attribute-based-publishing-strategy-resolver-settings';

@injectable()
export class DynamicTypeResolverAttributeBasedPublishing extends DynamicTypeResolverAbstract {
    readonly id: 'attributeBased';
    readonly value: 'attributeBased';
    readonly label: 'data-importer.resolver.publishing-strategy.attributeBased';
    readonly group: 'publishing';
    renderSettings(props: DynamicTypeResolverRenderProps) {
        return <AttributeBasedPublishingStrategyResolverSettings {...props} />;
    }
}
