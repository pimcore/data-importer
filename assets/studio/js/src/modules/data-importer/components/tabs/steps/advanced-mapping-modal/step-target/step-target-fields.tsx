/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React, { useMemo } from 'react';
import { useTranslation } from '@pimcore/studio-ui-bundle/app';
import { Flex, Select } from '@pimcore/studio-ui-bundle/components';
import { type MappingConfigItem } from '../../../../../types';
import { useStyles } from './step-target.styles';
import { ErrorBox } from '../error-box/error-box';
import { container } from '@pimcore/studio-ui-bundle';
import { bundleServiceIds } from '../../../../../../../config/service-ids';
import { DynamicTypeDataTargetRegistry } from '../../../../../dynamic-types/data-target/dynamic-type-data-target-registry';

export interface StepTargetFieldsProps {
    dataTarget?: MappingConfigItem['dataTarget'];
    transformationResultType?: string;
    classId?: string;
    classFieldOptions: Array<{ value: string; label: string }>;
    isLocalized: boolean;
    onDataTargetChange: (dataTarget: MappingConfigItem['dataTarget']) => void;
}

export const StepTargetFields = ({
    dataTarget,
    transformationResultType,
    classId,
    classFieldOptions,
    isLocalized,
    onDataTargetChange,
}: StepTargetFieldsProps): React.JSX.Element => {
    const { t } = useTranslation();
    const { styles } = useStyles();

    const targetRegistry = useMemo(
        () =>
            container.get<DynamicTypeDataTargetRegistry>(
                bundleServiceIds['DataImporter/DynamicTypes/DataTarget/Registry']
            ),
        []
    );
    const selectedTarget = useMemo(
        () => targetRegistry.getDynamicType(dataTarget?.type ?? ''),
        [targetRegistry, dataTarget?.type]
    );
    const targetOptions = useMemo(
        () => targetRegistry.getAllTypes().map(({ id, label }) => ({ value: id, label: t(label) })),
        [targetRegistry]
    );

    function onTargetChange(type: string) {
        const nextTarget = targetRegistry.getDynamicType(type ?? '');
        const settings = nextTarget?.getDefaultSettings?.(dataTarget?.settings) ?? dataTarget?.settings ?? {};
        onDataTargetChange({ ...dataTarget, type, settings });
    }

    return (
        <Flex className={styles.fieldsContainer} gap={6} vertical>
            <div>
                <div className={styles.fieldLabel}>{t('data-importer.mapping.advanced-modal.step-target.type')}</div>
                <div className={styles.selectSkeletonWrapper}>
                    <Select
                        className={styles.selectFull}
                        onChange={onTargetChange}
                        options={targetOptions}
                        value={dataTarget?.type}
                    />
                </div>
            </div>

            {selectedTarget ? (
                selectedTarget?.supportsType(transformationResultType) ? (
                    selectedTarget.renderSettings({
                        classId,
                        transformationResultType,
                        settings: dataTarget?.settings,
                        onChange: (settings) => onDataTargetChange({ ...dataTarget, settings }),
                        isLocalized,
                        classFieldOptions,
                    })
                ) : (
                    <ErrorBox>{selectedTarget?.getTypeErrorMessage(t)}</ErrorBox>
                )
            ) : (
                <></>
            )}
        </Flex>
    );
};
