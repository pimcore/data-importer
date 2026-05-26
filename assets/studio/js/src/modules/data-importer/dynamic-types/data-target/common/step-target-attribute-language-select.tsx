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
import { useLanguageOptions } from "./use-language-options";

export interface StepTargetAttributeLanguageSelectProps {
    value?: string;
    onChange(value: string): void;
}

export function StepTargetAttributeLanguageSelect({
    value,
    onChange,
}: StepTargetAttributeLanguageSelectProps) {
    const { t } = useTranslation();
    const { styles } = useStyles();
    const options = useLanguageOptions();

    return (
        <div>
            <div className={styles.fieldLabel}>
                {t(
                    "data-importer.mapping.item.data-target.language-placeholder",
                )}
            </div>
            <div className={styles.selectSkeletonWrapper}>
                <Select
                    className={styles.selectFull}
                    onChange={onChange}
                    options={options}
                    showSearch
                    value={value}
                />
            </div>
        </div>
    );
}
