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
import { DataImporterPanel } from '../data-importer-panel/data-importer-panel';
import { filterByLabel } from '../../../../utils/select-utils';
import { DynamicTypeResolverRegistry } from '../../../../dynamic-types/resolver/dynamic-type-resolver-registry';
import type { DataImporterFormValues } from '../../../../types';

export interface CreationPanelProps {
    registry: DynamicTypeResolverRegistry;
    columnHeaderOptions: Array<{ value: string; label: string }>;
    languageOptions: Array<{ value: string; label: string }>;
}

export const CreationPanel = ({ registry, ...props }: CreationPanelProps): React.JSX.Element => {
    const { t } = useTranslation();

    const resolvers = useMemo(() => registry.getDynamicTypesForGroup('createLocation'), [registry]);
    const options = useMemo(() => resolvers.map(({ value, label }) => ({ value, label: t(label) })), [resolvers, t]);

    return (
        <DataImporterPanel title={t('data-importer.resolver.element-creation')}>
            <Form.Item
                label={t('data-importer.resolver.create-location-strategy')}
                name={['resolverConfig', 'createLocationStrategy', 'type']}
                tooltip={t('data-importer.resolver.create-location-strategy.tooltip')}
            >
                <Select filterOption={filterByLabel} options={options} showSearch />
            </Form.Item>

            {resolvers.map((resolver) => (
                <Form.Conditional
                    condition={(values) =>
                        (values as unknown as DataImporterFormValues).resolverConfig?.createLocationStrategy ===
                        resolver.id
                    }
                    key={resolver.id}
                >
                    <DataImporterPanel theme="fieldset" title={t(resolver.label)}>
                        {resolver.renderSettings(props)}
                    </DataImporterPanel>
                </Form.Conditional>
            ))}
        </DataImporterPanel>
    );
};
