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
import { Form, Select } from '@pimcore/studio-ui-bundle/components';
import { useTranslation } from '@pimcore/studio-ui-bundle/app';
import { useSettings } from '@pimcore/studio-ui-bundle/modules/app';
import { FieldWidthProvider } from '@pimcore/studio-ui-bundle/modules/element';
import { useClassDefinitionCollectionQuery } from '@pimcore/studio-ui-bundle/api/class-definition';
import { useBundleDataImporterDataTypeLoadClassAttributesQuery } from '../../../../data-importer-api-slice.gen';
import { DataImporterPanel } from '../data-importer-panel/data-importer-panel';
import { StepHeading } from '../step-heading/step-heading';
import { filterByLabel } from '../../../../utils/select-utils';
import type { ClassAttribute } from '../../../../types';
import { LoadingPanel } from './loading-panel';
import { CreationPanel } from './creation-panel';
import { LocationUpdatePanel } from './location-update-panel';
import { PublishingPanel } from './publishing-panel';
import { container } from '@pimcore/studio-ui-bundle';
import { bundleServiceIds } from '../../../../../../config/service-ids';
import { DynamicTypeResolverRegistry } from '../../../../dynamic-types/resolver/dynamic-type-resolver-registry';

export interface ResolverStepProps {
    configName: string;
    columnHeaderOptions: Array<{ value: string; label: string }>;
    isActive: boolean;
}

function parseClassAttribute(raw: object): ClassAttribute {
    const obj = raw as Record<string, any>;
    return {
        key: obj.key ?? obj.name ?? '',
        title: obj.title ?? obj.name ?? obj.key ?? '',
        localized: Boolean(obj.localized ?? false),
    };
}

export const ResolverStep = ({
    configName: _configName,
    columnHeaderOptions,
    isActive,
}: ResolverStepProps): React.JSX.Element => {
    const { t } = useTranslation();
    const settings = useSettings();

    const languageOptions = useMemo(
        () => (settings.validLanguages ?? []).map((locale: string) => ({ value: locale, label: locale })),
        [settings.validLanguages]
    );

    const { data: classDefinitions, isLoading: isLoadingClasses } = useClassDefinitionCollectionQuery(undefined, {
        skip: !isActive,
    });

    const classOptions = (classDefinitions?.items ?? []).map((cls) => ({
        value: cls.id,
        label: cls.name,
    }));

    // Watch values needed for conditional sub-panels
    const updateLocationType = Form.useWatch(['resolverConfig', 'locationUpdateStrategy', 'type']) as
        | string
        | undefined;
    const updateFindStrategy = Form.useWatch([
        'resolverConfig',
        'locationUpdateStrategy',
        'settings',
        'findStrategy',
    ]) as string | undefined;
    const updateFindParentClassId = Form.useWatch([
        'resolverConfig',
        'locationUpdateStrategy',
        'settings',
        'attributeDataObjectClassId',
    ]) as string | undefined;
    const updateFindParentAttrName = Form.useWatch([
        'resolverConfig',
        'locationUpdateStrategy',
        'settings',
        'attributeName',
    ]) as string | undefined;

    // Fetch attributes for findParent (update location strategy) — keyed by its own classId
    const { data: updateFindParentAttrData } = useBundleDataImporterDataTypeLoadClassAttributesQuery(
        { classId: updateFindParentClassId ?? '', systemRead: true },
        {
            skip:
                updateFindParentClassId === undefined ||
                updateFindParentClassId === '' ||
                updateFindStrategy !== 'attribute',
        }
    );
    const updateFindParentAttributes = useMemo(
        () => (updateFindParentAttrData?.attributes ?? []).map(parseClassAttribute),
        [updateFindParentAttrData]
    );
    const updateFindParentAttrOptions = updateFindParentAttributes.map((a) => ({ value: a.key, label: a.title }));
    const updateFindParentAttrIsLocalized =
        updateFindParentAttributes.find((a) => a.key === updateFindParentAttrName)?.localized ?? false;

    const locationUpdateStrategyOptions = [
        { value: 'noChange', label: t('data-importer.resolver.location-strategy.noChange') },
        { value: 'staticPath', label: t('data-importer.resolver.location-strategy.staticPath') },
        { value: 'findOrCreateFolder', label: t('data-importer.resolver.location-strategy.findOrCreateFolder') },
        { value: 'findParent', label: t('data-importer.resolver.location-strategy.findParent') },
    ];

    const findStrategyOptions = [
        { value: 'id', label: t('data-importer.resolver.location-strategy.find-strategy.id') },
        { value: 'path', label: t('data-importer.resolver.location-strategy.find-strategy.path') },
        { value: 'attribute', label: t('data-importer.resolver.location-strategy.find-strategy.attribute') },
    ];

    const registry = useMemo(
        () =>
            container.get<DynamicTypeResolverRegistry>(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Registry']),
        []
    );

    return (
        <FieldWidthProvider fieldWidthValues={{ medium: 600 }}>
            <>
                <StepHeading>{t('data-importer.resolver.title')}</StepHeading>

                <DataImporterPanel>
                    <Form.Item
                        label={t('data-importer.resolver.class')}
                        name={['resolverConfig', 'dataObjectClassId']}
                        required
                    >
                        <Select
                            filterOption={filterByLabel}
                            loadingSkeleton={isLoadingClasses}
                            options={classOptions}
                            placeholder={t('data-importer.resolver.class-placeholder')}
                            showSearch
                        />
                    </Form.Item>
                </DataImporterPanel>

                <LoadingPanel
                    registry={registry}
                    columnHeaderOptions={columnHeaderOptions}
                    languageOptions={languageOptions}
                />

                <CreationPanel
                    registry={registry}
                    columnHeaderOptions={columnHeaderOptions}
                    languageOptions={languageOptions}
                />

                <LocationUpdatePanel
                    classOptions={classOptions}
                    columnHeaderOptions={columnHeaderOptions}
                    findStrategyOptions={findStrategyOptions}
                    languageOptions={languageOptions}
                    locationUpdateStrategyOptions={locationUpdateStrategyOptions}
                    updateFindParentAttrIsLocalized={updateFindParentAttrIsLocalized}
                    updateFindParentAttrOptions={updateFindParentAttrOptions}
                    updateFindStrategy={updateFindStrategy}
                    updateLocationType={updateLocationType}
                />

                <PublishingPanel registry={registry} columnHeaderOptions={columnHeaderOptions} />
            </>
        </FieldWidthProvider>
    );
};
