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
import { injectable } from "@pimcore/studio-ui-bundle/app";
import { ClassAttribute, DataTargetConfig } from "../../../types";

export interface DynamicTypeDataTargetRenderProps {
    classId: string;
    classFieldOptions: Array<{ value: string; label: string }>;
    isLocalized: boolean;
    transformationResultType: string;
    settings: DataTargetConfig["settings"];
    onChange(settings: DataTargetConfig["settings"]): void;
}

@injectable()
export abstract class DynamicTypeDataTargetAbstract {
    /** Unique identifier, e.g. 'direct', or 'classificationstore' */
    abstract readonly id: string;

    /** Human-readable label shown in the UI */
    abstract readonly label: string;

    /** Specifies whether given type is supported by data target */
    abstract supportsType(type: string): boolean;

    /** Render the settings form for this data target type. */
    abstract renderSettings(
        props: DynamicTypeDataTargetRenderProps,
    ): React.ReactNode;
}
