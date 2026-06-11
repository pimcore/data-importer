import { Form, Input, Select } from '@pimcore/studio-ui-bundle/components';
import { DataImporterPanel } from '../../../../components/tabs/steps/data-importer-panel/data-importer-panel';
import React from 'react';
import { useTranslation } from '@pimcore/studio-ui-bundle/app';
import { filterByLabel } from '../../../../utils/select-utils';
import { DynamicTypeResolverRenderProps } from '../../common/dynamic-type-resolver-abstract';

export function FindOrCreateFolderLocationUpdateSettings({ columnHeaderOptions }: DynamicTypeResolverRenderProps) {
    const { t } = useTranslation();

    return (
        <>
            <DataImporterPanel
                theme="fieldset"
                title={t('data-importer.resolver.location-strategy.findOrCreateFolder')}
            >
                <Form.Item
                    label={t('data-importer.resolver.location-strategy.data-source-index')}
                    name={['resolverConfig', 'locationUpdateStrategy', 'settings', 'dataSourceIndex']}
                >
                    <Select
                        filterOption={filterByLabel}
                        options={columnHeaderOptions}
                        placeholder={t('data-importer.resolver.location-strategy.data-source-index-placeholder')}
                        showSearch
                    />
                </Form.Item>
                <Form.Item
                    label={t('data-importer.resolver.location-strategy.fallback-path')}
                    name={['resolverConfig', 'locationUpdateStrategy', 'settings', 'fallbackPath']}
                    tooltip={t('data-importer.resolver.location-strategy.fallback-path.tooltip')}
                >
                    <Input placeholder={t('data-importer.resolver.location-strategy.fallback-path-placeholder')} />
                </Form.Item>
            </DataImporterPanel>
        </>
    );
}
