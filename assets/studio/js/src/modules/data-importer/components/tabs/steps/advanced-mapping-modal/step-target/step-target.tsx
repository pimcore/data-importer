/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useEffect, useRef, useState } from 'react';
import { useTranslation } from '@pimcore/studio-ui-bundle/app';
import { Flex, Text } from '@pimcore/studio-ui-bundle/components';
import { type ClassAttribute, type MappingConfigItem, resolveAttrMapKey } from '../../../../../types';
import { useResultPreviewContext } from '../result-preview/result-preview-context';
import { useStyles } from './step-target.styles';
import { ClassificationStoreKeyModal } from './classification-store-key-modal/classification-store-key-modal';
import { StepTargetPreviewActions } from './step-target-preview-actions';
import { StepTargetFields } from './step-target-fields';

export interface StepTargetProps {
    attributesMap: Record<string, ClassAttribute[]>;
    transformationResultType?: string;
    dataTarget?: MappingConfigItem['dataTarget'];
    classId?: string;
    onDataTargetChange: (dataTarget: MappingConfigItem['dataTarget']) => void;
}

export const StepTarget = ({
    attributesMap,
    transformationResultType,
    dataTarget,
    classId,
    onDataTargetChange,
}: StepTargetProps): React.JSX.Element => {
    const { t } = useTranslation();
    const { styles } = useStyles();
    const { isFetchingAttributes, calculateTypeError } = useResultPreviewContext();
    const [keyModalOpen, setKeyModalOpen] = useState(false);

    const isDirect = dataTarget?.type === 'direct';
    const isClassificationStore = dataTarget?.type === 'classificationstore';
    const isManyToMany = dataTarget?.type === 'manyToManyRelation';

    const attrMapKey = resolveAttrMapKey(transformationResultType);
    const defaultAttributes: ClassAttribute[] = attributesMap[attrMapKey] ?? [];

    const attributeOptions =
        calculateTypeError !== undefined ? [] : defaultAttributes.map((a) => ({ value: a.key, label: a.title }));
    const isLocalized = defaultAttributes.find((a) => a.key === dataTarget?.settings?.fieldName)?.localized ?? false;

    const writeIfNotEmpty = dataTarget?.settings?.writeIfTargetIsNotEmpty ?? false;
    const showWriteSettings = isDirect || isManyToMany;

    // When writeIfTargetIsNotEmpty is turned off, clear overwriteMode
    useEffect(() => {
        if (!showWriteSettings) return;

        if (
            !writeIfNotEmpty &&
            (dataTarget?.settings?.overwriteMode !== undefined || dataTarget?.settings?.writeIfSourceIsEmpty === true)
        ) {
            onDataTargetChange({
                ...dataTarget,
                settings: {
                    ...dataTarget.settings,
                    overwriteMode: undefined,
                    writeIfSourceIsEmpty: false,
                },
            });
        }
    }, [writeIfNotEmpty, showWriteSettings]);

    // Reset fieldName when it no longer exists in the loaded attributes or when type calculation failed
    useEffect(() => {
        if (isFetchingAttributes) return;
        const currentFieldName = dataTarget?.settings?.fieldName;
        if (currentFieldName === undefined) return;
        if (!attributeOptions.some((a) => a.value === currentFieldName)) {
            onDataTargetChange({
                ...dataTarget,
                settings: {
                    ...dataTarget?.settings,
                    fieldName: undefined,
                    language: undefined,
                },
            });
        }
    }, [attributeOptions, isFetchingAttributes]);

    return (
        <Flex className={styles.twoColumnLayout} gap="extra-small">
            <Flex className={styles.leftColumn} vertical>
                <Flex align="center" className={styles.leftHeader}>
                    <Text className={styles.leftHeaderTitle} strong>
                        {t('data-importer.mapping.advanced-modal.step-target')}
                    </Text>
                </Flex>

                <StepTargetFields
                    classId={classId}
                    transformationResultType={transformationResultType}
                    classFieldOptions={attributeOptions}
                    isLocalized={isLocalized}
                    dataTarget={dataTarget}
                    onDataTargetChange={onDataTargetChange}
                />
            </Flex>

            <StepTargetPreviewActions />

            {isClassificationStore &&
                classId !== undefined &&
                dataTarget?.settings?.fieldName !== undefined &&
                transformationResultType !== undefined && (
                    <ClassificationStoreKeyModal
                        classId={classId}
                        fieldName={dataTarget.settings.fieldName}
                        onClose={() => {
                            setKeyModalOpen(false);
                        }}
                        onSelect={(selectedKeyId) => {
                            onDataTargetChange({
                                ...dataTarget,
                                settings: {
                                    ...dataTarget?.settings,
                                    keyId: selectedKeyId,
                                },
                            });
                            setKeyModalOpen(false);
                        }}
                        open={keyModalOpen}
                        transformationResultType={transformationResultType}
                    />
                )}
        </Flex>
    );
};
