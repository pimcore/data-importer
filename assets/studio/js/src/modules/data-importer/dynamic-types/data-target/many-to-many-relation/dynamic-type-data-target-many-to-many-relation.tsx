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
import { DataTargetManyToManyRelationSettings } from './data-target-many-to-many-relation-settings';
import { TFunction } from 'i18next';
import { DataTargetConfig } from '../../../types';

export class DynamicTypeDataTargetManyToManyRelation extends DynamicTypeDataTargetAbstract {
    readonly id = 'manyToManyRelation';
    readonly label = 'Many to Many Relation';

    supportsType(type?: string): boolean {
        return ['advancedDataObjectArray', 'dataObjectArray', 'assetArray', 'advancedAssetArray'].includes(type ?? '');
    }

    getTypeErrorMessage(t: TFunction): string | undefined {
        return t('data-importer.mapping.advanced-modal.step-target.type-error.manyToManyRelation');
    }

    getDefaultSettings(currentSettings: DataTargetConfig['settings']): DataTargetConfig['settings'] {
        return {
            ...currentSettings,
            overwriteMode: currentSettings?.overwriteMode,
            keyId: undefined,
            fieldName: currentSettings?.fieldName,
            language: currentSettings?.language,
            writeIfTargetIsNotEmpty: currentSettings?.writeIfTargetIsNotEmpty ?? true,
            writeIfSourceIsEmpty: currentSettings?.writeIfSourceIsEmpty ?? true,
        };
    }

    renderSettings(props: DynamicTypeDataTargetRenderProps): React.ReactNode {
        return <DataTargetManyToManyRelationSettings {...props} />;
    }
}
