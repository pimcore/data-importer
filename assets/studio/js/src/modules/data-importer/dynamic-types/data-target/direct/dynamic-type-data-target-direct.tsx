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
    DynamicTypeDataTargetAbstract,
    DynamicTypeDataTargetRenderProps,
} from '../common/dynamic-type-data-target-abstract';
import { DataTargetDirectSettings } from './data-target-direct-settings';
import { DataTargetConfig } from '../../../types';

export class DynamicTypeDataTargetDirect extends DynamicTypeDataTargetAbstract {
    readonly id = 'direct';
    readonly label = 'data-importer.mapping.item.data-target.type.direct';

    supportsType(type?: string): boolean {
        return true;
    }

    getDefaultSettings(currentSettings: DataTargetConfig['settings']): DataTargetConfig['settings'] {
        return {
            ...currentSettings,
            overwriteMode: undefined,
            keyId: undefined,
            fieldName: currentSettings?.fieldName,
            language: currentSettings?.language,
            writeIfTargetIsNotEmpty: currentSettings?.writeIfTargetIsNotEmpty ?? true,
            writeIfSourceIsEmpty: currentSettings?.writeIfSourceIsEmpty ?? true,
        };
    }

    renderSettings(props: DynamicTypeDataTargetRenderProps): React.ReactNode {
        return <DataTargetDirectSettings {...props} />;
    }
}
