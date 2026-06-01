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
import { DataTargetClassificationstoreSettings } from './data-target-classificationstore-settings';
import { DataTargetConfig } from '../../../types';

export class DynamicTypeDataTargetClassificationstore extends DynamicTypeDataTargetAbstract {
    readonly id = 'classificationstore';
    readonly label = 'data-importer.mapping.item.data-target.type.classificationstore';

    supportsType(type?: string): boolean {
        return true;
    }

    getDefaultSettings(currentSettings: DataTargetConfig['settings']): DataTargetConfig['settings'] {
        return {
            ...currentSettings,
            overwriteMode: undefined,
            keyId: currentSettings?.keyId,
            fieldName: undefined,
            language: undefined,
        };
    }

    renderSettings(props: DynamicTypeDataTargetRenderProps): React.ReactNode {
        return <DataTargetClassificationstoreSettings {...props} />;
    }
}
