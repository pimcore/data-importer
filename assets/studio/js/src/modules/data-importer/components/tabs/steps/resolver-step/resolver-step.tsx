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
import { DataImporterPanel } from '../data-importer-panel/data-importer-panel';
import { StepHeading } from '../step-heading/step-heading';
import { filterByLabel } from '../../../../utils/select-utils';
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
                    classOptions={classOptions}
                    isLoadingClasses={isLoadingClasses}
                />

                <LocationUpdatePanel
                    registry={registry}
                    columnHeaderOptions={columnHeaderOptions}
                    languageOptions={languageOptions}
                    classOptions={classOptions}
                    isLoadingClasses={isLoadingClasses}
                />

                <PublishingPanel registry={registry} columnHeaderOptions={columnHeaderOptions} />
            </>
        </FieldWidthProvider>
    );
};
