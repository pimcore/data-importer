/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { Select } from "@pimcore/studio-ui-bundle/components";
import React from "react";
import { useTranslation } from "@pimcore/studio-ui-bundle/app";
import { useStyles } from "../../../components/tabs/steps/advanced-mapping-modal/step-target/step-target.styles";

export interface StepTargetAttributeSelectProps {
    options: Array<{ value: string; label: string }>;
    isLoading?: boolean;
    value?: string;
    onChange(value: string): void;
}

export function StepTargetAttributeSelect({
    options,
    isLoading,
    value,
    onChange,
}: StepTargetAttributeSelectProps) {
    const { t } = useTranslation();
    const { styles } = useStyles();

    return (
        <div>
            <div className={styles.fieldLabel}>
                {t(
                    "data-importer.mapping.advanced-modal.step-target.field-name",
                )}
            </div>
            <div className={styles.selectSkeletonWrapper}>
                <Select
                    className={styles.selectFull}
                    loadingSkeleton={isLoading}
                    onChange={onChange}
                    options={options}
                    showSearch
                    value={value}
                />
            </div>
        </div>
    );
}
