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
import { DataTargetClassificationstoreBatchSettings } from './data-target-classificationstore-batch-settings';
import { TFunction } from 'i18next';
import { DataTargetConfig } from '../../../types';

export class DynamicTypeDataTargetClassificationstoreBatch extends DynamicTypeDataTargetAbstract {
    readonly id = 'classificationstoreBatch';
    readonly label = 'Classification Store Batch';

    supportsType(type?: string): boolean {
        return ['array', 'quantityValueArray', 'inputQuantityValueArray', 'dateArray'].includes(type ?? '');
    }

    getTypeErrorMessage(t: TFunction): string | undefined {
        return t('data-importer.mapping.advanced-modal.step-target.type-error.classificationstoreBatch');
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
        return <DataTargetClassificationstoreBatchSettings {...props} />;
    }
}
