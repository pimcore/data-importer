/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from "react";
import { DynamicTypeDataTargetRenderProps } from "../common/dynamic-type-data-target-abstract";
import { StepTargetWriteSettings } from "../../../components/tabs/steps/advanced-mapping-modal/step-target/step-target-write-settings";
import { StepTargetAttributeSelect } from "../common/step-target-attribute-select";
import { StepTargetAttributeLanguageSelect } from "../common/step-target-attribute-language-select";

export function DataTargetDirectSettings({
    isLocalized,
    settings,
    onChange,
    classFieldOptions,
}: DynamicTypeDataTargetRenderProps) {
    return (
        <>
            <StepTargetAttributeSelect
                options={classFieldOptions}
                value={settings?.fieldName}
                onChange={(value) =>
                    onChange({ ...settings, fieldName: value })
                }
            />

            {isLocalized && (
                <StepTargetAttributeLanguageSelect
                    value={settings?.language}
                    onChange={(language) => onChange({ ...settings, language })}
                />
            )}

            <StepTargetWriteSettings settings={settings} onChange={onChange} />
        </>
    );
}
