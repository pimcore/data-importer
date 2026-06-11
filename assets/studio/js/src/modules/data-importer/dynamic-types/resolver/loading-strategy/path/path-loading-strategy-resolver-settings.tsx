import { Form, Select } from '@pimcore/studio-ui-bundle/components';
import { filterByLabel } from '../../../../utils/select-utils';
import { DataImporterPanel } from '../../../../components/tabs/steps/data-importer-panel/data-importer-panel';
import React from 'react';
import { useTranslation } from '@pimcore/studio-ui-bundle/app';
import { DynamicTypeResolverRenderProps } from '../../common/dynamic-type-resolver-abstract';

export function PathLoadingStrategyResolverSettings({ columnHeaderOptions }: DynamicTypeResolverRenderProps) {
    const { t } = useTranslation();

    return (
        <>
            <DataImporterPanel theme="fieldset" title={t('data-importer.resolver.loading-strategy.path')}>
                <Form.Item
                    label={t('data-importer.resolver.loading-strategy.data-source-index')}
                    name={['resolverConfig', 'loadingStrategy', 'settings', 'dataSourceIndex']}
                >
                    <Select
                        filterOption={filterByLabel}
                        options={columnHeaderOptions}
                        placeholder={t('data-importer.resolver.loading-strategy.data-source-index-placeholder')}
                        showSearch
                    />
                </Form.Item>
            </DataImporterPanel>
        </>
    );
}
