import { Form, Select, Switch } from '@pimcore/studio-ui-bundle/components';
import { filterByLabel } from '../../../../utils/select-utils';
import { DataImporterPanel } from '../../../../components/tabs/steps/data-importer-panel/data-importer-panel';
import React from 'react';
import { useTranslation } from '@pimcore/studio-ui-bundle/app';
import { DynamicTypeResolverRenderProps } from '../../common/dynamic-type-resolver-abstract';

export function AttributeLoadingStrategyResolverSettings({
    columnHeaderOptions,
    isLoadingLoadingAttrs,
    loadingAttributeOptions,
    loadingAttrIsLocalized,
    languageOptions,
}: DynamicTypeResolverRenderProps) {
    const { t } = useTranslation();

    return (
        <>
            <DataImporterPanel theme="fieldset" title={t('data-importer.resolver.loading-strategy.attribute')}>
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
                <Form.Item
                    label={t('data-importer.resolver.loading-strategy.attribute-name')}
                    name={['resolverConfig', 'loadingStrategy', 'settings', 'attributeName']}
                >
                    <Select
                        filterOption={filterByLabel}
                        loadingSkeleton={isLoadingLoadingAttrs}
                        options={loadingAttributeOptions}
                        placeholder={t('data-importer.resolver.loading-strategy.attribute-name-placeholder')}
                        showSearch
                    />
                </Form.Item>
                {loadingAttrIsLocalized && (
                    <Form.Item
                        label={t('data-importer.resolver.loading-strategy.language')}
                        name={['resolverConfig', 'loadingStrategy', 'settings', 'language']}
                    >
                        <Select
                            filterOption={filterByLabel}
                            options={languageOptions}
                            placeholder={t('data-importer.resolver.loading-strategy.language-placeholder')}
                            showSearch
                        />
                    </Form.Item>
                )}
                <Form.Item
                    name={['resolverConfig', 'loadingStrategy', 'settings', 'includeUnpublished']}
                    valuePropName="checked"
                >
                    <Switch
                        labelRight={t('data-importer.resolver.loading-strategy.include-unpublished')}
                        size="small"
                    />
                </Form.Item>
            </DataImporterPanel>
        </>
    );
}
