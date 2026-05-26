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
import {
    DynamicTypeDataTargetAbstract,
    DynamicTypeDataTargetRenderProps,
} from "../common/dynamic-type-data-target-abstract";
import { DataTargetManyToManyRelationSettings } from "./data-target-many-to-many-relation-settings";

export class DynamicTypeDataTargetManyToManyRelation extends DynamicTypeDataTargetAbstract {
    id = "manyToManyRelation";
    label = "Many to Many Relation";

    supportsType(type: string): boolean {
        return [
            "advancedDataObjectArray",
            "dataObjectArray",
            "assetArray",
            "advancedAssetArray",
        ].includes(type);
    }

    renderSettings(props: DynamicTypeDataTargetRenderProps): React.ReactNode {
        return <DataTargetManyToManyRelationSettings {...props} />;
    }
}
