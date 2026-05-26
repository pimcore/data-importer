/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { Button, Flex, Input } from "@pimcore/studio-ui-bundle/components";
import React, { useMemo, useState } from "react";
import { useTranslation } from "@pimcore/studio-ui-bundle/app";
import { useStyles } from "../../../components/tabs/steps/advanced-mapping-modal/step-target/step-target.styles";
import { useBundleDataImporterClassificationstoreLoadKeyNameQuery } from "../../../data-importer-api-slice.gen";
import { ClassificationStoreKeyModal } from "../../../components/tabs/steps/advanced-mapping-modal/step-target/classification-store-key-modal/classification-store-key-modal";

export interface StepTargetClassificationstoreKeySearchProps {
    classId: string;
    fieldName: string;
    transformationResultType: string;
    keyId?: string;
    onChange(keyId: string): void;
}

export function StepTargetClassificationstoreKeySearch({
    classId,
    fieldName,
    transformationResultType,
    keyId,
    onChange,
}: StepTargetClassificationstoreKeySearchProps) {
    const { t } = useTranslation();
    const { styles } = useStyles();

    const [keyModalOpen, setKeyModalOpen] = useState(false);

    const canOpenKeyModal = !!(
        classId &&
        fieldName &&
        transformationResultType
    );

    const { data: keyNameResponse } =
        useBundleDataImporterClassificationstoreLoadKeyNameQuery(
            { keyId: keyId ?? "" },
            { skip: keyId === undefined || keyId === "" },
        );

    const keyLabel = useMemo(() => {
        if (keyId === undefined || keyId === "") {
            return t(
                "data-importer.mapping.advanced-modal.step-target.classification-store-key-placeholder",
            );
        }

        if (
            keyNameResponse?.groupName !== undefined &&
            keyNameResponse?.keyName !== undefined
        ) {
            return t(
                "data-importer.mapping.advanced-modal.step-target.classification-store-key-in-group",
                {
                    key: keyNameResponse.keyName,
                    group: keyNameResponse.groupName,
                },
            );
        }

        return keyId;
    }, [keyId, keyNameResponse?.groupName, keyNameResponse?.keyName, t]);

    return (
        <>
            <ClassificationStoreKeyModal
                classId={classId}
                fieldName={fieldName}
                onClose={() => setKeyModalOpen(false)}
                onSelect={(selectedKeyId) => {
                    onChange(selectedKeyId);
                    setKeyModalOpen(false);
                }}
                open={keyModalOpen}
                transformationResultType={transformationResultType}
            />
            <div>
                <div className={styles.fieldLabel}>
                    {t(
                        "data-importer.mapping.advanced-modal.step-target.classification-store-key",
                    )}
                </div>
                <Flex align="center" gap="extra-small">
                    <Input
                        className={styles.classificationStoreKeyInput}
                        readOnly
                        value={keyLabel}
                    />
                    <Button
                        disabled={!canOpenKeyModal}
                        onClick={() => {
                            setKeyModalOpen(true);
                        }}
                        type="default"
                    >
                        {t("common.search")}
                    </Button>
                </Flex>
            </div>
        </>
    );
}
