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

export interface LoadingPanelProps {
    registry: DynamicTypeResolverRegistry;
    columnHeaderOptions: Array<{ value: string; label: string }>;
    languageOptions: Array<{ value: string; label: string }>;
}

export const LoadingPanel = ({ registry, ...props }: LoadingPanelProps): React.JSX.Element => {
    const { t } = useTranslation();

    const loadingResolvers = useMemo(() => registry.getDynamicTypesForGroup('loading'), [registry]);
    const options = useMemo(
        () => loadingResolvers.map(({ value, label }) => ({ value, label: t(label) })),
        [loadingResolvers, t]
    );

    return (
        <DataImporterPanel title={t('data-importer.resolver.element-loading')}>
            <Form.Item
                label={t('data-importer.resolver.loading-strategy')}
                name={['resolverConfig', 'loadingStrategy', 'type']}
                tooltip={t('data-importer.resolver.loading-strategy.tooltip')}
            >
                <Select filterOption={filterByLabel} options={options} showSearch />
            </Form.Item>

            {loadingResolvers.map((resolver) => (
                <Form.Conditional
                    condition={(values) =>
                        (values as unknown as DataImporterFormValues).resolverConfig?.loadingStrategy === resolver.id
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
