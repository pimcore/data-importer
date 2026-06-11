import { Form, ManyToOneRelationPath } from '@pimcore/studio-ui-bundle/components';
import { DataImporterPanel } from '../../../../components/tabs/steps/data-importer-panel/data-importer-panel';
import React from 'react';
import { useTranslation } from '@pimcore/studio-ui-bundle/app';

export function StaticPathCreateLocationStrategyResolverSettings() {
    const { t } = useTranslation();

    return (
        <>
            <DataImporterPanel theme="fieldset" title={t('data-importer.resolver.location-strategy.staticPath')}>
                <Form.Item
                    label={t('data-importer.resolver.location-strategy.path')}
                    name={['resolverConfig', 'createLocationStrategy', 'settings', 'path']}
                    required
                    rules={[
                        {
                            required: true,
                            message: t('data-importer.validation.required', {
                                field: t('data-importer.resolver.location-strategy.path'),
                            }),
                        },
                    ]}
                >
                    <ManyToOneRelationPath allowPathTextInput allowedDataObjectTypes={['folder']} dataObjectsAllowed />
                </Form.Item>
            </DataImporterPanel>
        </>
    );
}
