/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { Form, Input, Select, Switch } from '@pimcore/studio-ui-bundle/components';
import { DataImporterPanel } from '../../../../components/tabs/steps/data-importer-panel/data-importer-panel';
import React, { useMemo } from 'react';
import { useTranslation } from '@pimcore/studio-ui-bundle/app';
import { filterByLabel } from '../../../../utils/select-utils';
import { DynamicTypeResolverRenderProps } from '../../common/dynamic-type-resolver-abstract';
import { useBundleDataImporterDataTypeLoadClassAttributesQuery } from '../../../../data-importer-api-slice.gen';
import { parseClassAttribute } from '../../../../components/tabs/steps/mapping-step/hooks/use-mapping-step-loader.types';

export function FindParentLocationUpdateSettings({
    columnHeaderOptions,
    classOptions,
    languageOptions = [],
}: DynamicTypeResolverRenderProps) {
    const { t } = useTranslation();

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

    const findStrategyOptions = [
        { value: 'id', label: t('data-importer.resolver.location-strategy.find-strategy.id') },
        { value: 'path', label: t('data-importer.resolver.location-strategy.find-strategy.path') },
        { value: 'attribute', label: t('data-importer.resolver.location-strategy.find-strategy.attribute') },
    ];

    return (
        <>
            <DataImporterPanel theme="fieldset" title={t('data-importer.resolver.location-strategy.findParent')}>
                <Form.Item
                    label={t('data-importer.resolver.location-strategy.find-strategy')}
                    name={['resolverConfig', 'locationUpdateStrategy', 'settings', 'findStrategy']}
                >
                    <Select filterOption={filterByLabel} options={findStrategyOptions} showSearch />
                </Form.Item>
                {updateFindStrategy === 'attribute' && (
                    <>
                        <Form.Item
                            label={t('data-importer.resolver.location-strategy.attribute-class')}
                            name={[
                                'resolverConfig',
                                'locationUpdateStrategy',
                                'settings',
                                'attributeDataObjectClassId',
                            ]}
                        >
                            <Select filterOption={filterByLabel} options={classOptions} showSearch />
                        </Form.Item>
                        <Form.Item
                            label={t('data-importer.resolver.location-strategy.attribute-name')}
                            name={['resolverConfig', 'locationUpdateStrategy', 'settings', 'attributeName']}
                        >
                            <Select filterOption={filterByLabel} options={updateFindParentAttrOptions} showSearch />
                        </Form.Item>
                        {updateFindParentAttrIsLocalized && (
                            <Form.Item
                                label={t('data-importer.resolver.location-strategy.attribute-language')}
                                name={['resolverConfig', 'locationUpdateStrategy', 'settings', 'attributeLanguage']}
                            >
                                <Select filterOption={filterByLabel} options={languageOptions} showSearch />
                            </Form.Item>
                        )}
                    </>
                )}
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
                <Form.Item
                    name={['resolverConfig', 'locationUpdateStrategy', 'settings', 'asVariant']}
                    valuePropName="checked"
                >
                    <Switch labelRight={t('data-importer.resolver.location-strategy.as-variant')} size="small" />
                </Form.Item>
            </DataImporterPanel>
        </>
    );
}
