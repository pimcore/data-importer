import { Form, Input, Select, Switch } from '@pimcore/studio-ui-bundle/components';
import { DataImporterPanel } from '../../../../components/tabs/steps/data-importer-panel/data-importer-panel';
import React, { useMemo } from 'react';
import { useTranslation } from '@pimcore/studio-ui-bundle/app';
import { filterByLabel } from '../../../../utils/select-utils';
import { DynamicTypeResolverRenderProps } from '../../common/dynamic-type-resolver-abstract';
import { useClassDefinitionCollectionQuery } from '@pimcore/studio-ui-bundle/api/class-definition';
import { useBundleDataImporterDataTypeLoadClassAttributesQuery } from '../../../../data-importer-api-slice.gen';
import { parseClassAttribute } from '../../../../components/tabs/steps/mapping-step/hooks/use-mapping-step-loader.types';

export function FindParentLocationCreationSettings({
    columnHeaderOptions,
    languageOptions = [],
}: DynamicTypeResolverRenderProps) {
    const { t } = useTranslation();

    const createFindStrategy = Form.useWatch([
        'resolverConfig',
        'createLocationStrategy',
        'settings',
        'findStrategy',
    ]) as string | undefined;
    const createFindParentClassId = Form.useWatch([
        'resolverConfig',
        'createLocationStrategy',
        'settings',
        'attributeDataObjectClassId',
    ]) as string | undefined;
    const createFindParentAttrName = Form.useWatch([
        'resolverConfig',
        'createLocationStrategy',
        'settings',
        'attributeName',
    ]) as string | undefined;

    const findStrategyOptions = [
        { value: 'id', label: t('data-importer.resolver.location-strategy.find-strategy.id') },
        { value: 'path', label: t('data-importer.resolver.location-strategy.find-strategy.path') },
        { value: 'attribute', label: t('data-importer.resolver.location-strategy.find-strategy.attribute') },
    ];

    const { data: classDefinitions, isLoading: isLoadingClasses } = useClassDefinitionCollectionQuery(undefined);

    const classOptions = (classDefinitions?.items ?? []).map((cls) => ({
        value: cls.id,
        label: cls.name,
    }));

    const { data: createFindParentAttrData, isLoading: isLoadingCreateFindParentAttrs } =
        useBundleDataImporterDataTypeLoadClassAttributesQuery(
            { classId: createFindParentClassId ?? '', systemRead: true },
            {
                skip:
                    createFindParentClassId === undefined ||
                    createFindParentClassId === '' ||
                    createFindStrategy !== 'attribute',
            }
        );
    const createFindParentAttributes = useMemo(
        () => (createFindParentAttrData?.attributes ?? []).map(parseClassAttribute),
        [createFindParentAttrData]
    );
    const createFindParentAttrOptions = createFindParentAttributes.map((a) => ({ value: a.key, label: a.title }));
    const createFindParentAttrIsLocalized =
        createFindParentAttributes.find((a) => a.key === createFindParentAttrName)?.localized ?? false;

    return (
        <>
            <DataImporterPanel theme="fieldset" title={t('data-importer.resolver.location-strategy.findParent')}>
                <Form.Item
                    label={t('data-importer.resolver.location-strategy.find-strategy')}
                    name={['resolverConfig', 'createLocationStrategy', 'settings', 'findStrategy']}
                >
                    <Select filterOption={filterByLabel} options={findStrategyOptions} showSearch />
                </Form.Item>
                {createFindStrategy === 'attribute' && (
                    <>
                        <Form.Item
                            label={t('data-importer.resolver.location-strategy.attribute-class')}
                            name={[
                                'resolverConfig',
                                'createLocationStrategy',
                                'settings',
                                'attributeDataObjectClassId',
                            ]}
                        >
                            <Select
                                filterOption={filterByLabel}
                                loadingSkeleton={isLoadingClasses}
                                options={classOptions}
                                showSearch
                            />
                        </Form.Item>
                        <Form.Item
                            label={t('data-importer.resolver.location-strategy.attribute-name')}
                            name={['resolverConfig', 'createLocationStrategy', 'settings', 'attributeName']}
                        >
                            <Select
                                filterOption={filterByLabel}
                                loadingSkeleton={isLoadingCreateFindParentAttrs}
                                options={createFindParentAttrOptions}
                                showSearch
                            />
                        </Form.Item>
                        {createFindParentAttrIsLocalized && (
                            <Form.Item
                                label={t('data-importer.resolver.location-strategy.attribute-language')}
                                name={['resolverConfig', 'createLocationStrategy', 'settings', 'attributeLanguage']}
                            >
                                <Select filterOption={filterByLabel} options={languageOptions} showSearch />
                            </Form.Item>
                        )}
                    </>
                )}
                <Form.Item
                    label={t('data-importer.resolver.location-strategy.data-source-index')}
                    name={['resolverConfig', 'createLocationStrategy', 'settings', 'dataSourceIndex']}
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
                    name={['resolverConfig', 'createLocationStrategy', 'settings', 'fallbackPath']}
                    tooltip={t('data-importer.resolver.location-strategy.fallback-path.tooltip')}
                >
                    <Input placeholder={t('data-importer.resolver.location-strategy.fallback-path-placeholder')} />
                </Form.Item>
                <Form.Item
                    name={['resolverConfig', 'createLocationStrategy', 'settings', 'asVariant']}
                    valuePropName="checked"
                >
                    <Switch labelRight={t('data-importer.resolver.location-strategy.as-variant')} size="small" />
                </Form.Item>
            </DataImporterPanel>
        </>
    );
}
