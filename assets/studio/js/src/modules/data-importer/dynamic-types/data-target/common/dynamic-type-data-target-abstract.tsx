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
import { injectable } from '@pimcore/studio-ui-bundle/app';
import { DataTargetConfig } from '../../../types';
import { TFunction } from 'i18next';

export interface DynamicTypeDataTargetRenderProps {
    classId?: string;
    classFieldOptions: Array<{ value: string; label: string }>;
    isLocalized: boolean;
    transformationResultType?: string;
    settings: DataTargetConfig['settings'];
    onChange(settings: DataTargetConfig['settings']): void;
}

@injectable()
export abstract class DynamicTypeDataTargetAbstract {
    /** Unique identifier, e.g. 'direct', or 'classificationstore' */
    abstract readonly id: string;

    /** Human-readable label shown in the UI */
    abstract readonly label: string;

    /** Specifies whether given type is supported by data target */
    abstract supportsType(type?: string): boolean;

    /** Translated message displayed when target is used on an unsupported type */
    getTypeErrorMessage(t: TFunction): string | undefined {
        return undefined;
    }

    /** Default settings to apply when selecting target */
    getDefaultSettings(currentSettings: DataTargetConfig['settings']): DataTargetConfig['settings'] {
        return currentSettings;
    }

    /** Render the settings form for this data target type */
    abstract renderSettings(props: DynamicTypeDataTargetRenderProps): React.ReactNode;
}
