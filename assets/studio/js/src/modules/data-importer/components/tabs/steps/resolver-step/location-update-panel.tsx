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

export interface LocationUpdatePanelProps {
    registry: DynamicTypeResolverRegistry;
    columnHeaderOptions: Array<{ value: string; label: string }>;
    languageOptions: Array<{ value: string; label: string }>;
    classOptions?: Array<{ value: string; label: string }>;
    isLoadingClasses?: boolean;
}

export const LocationUpdatePanel = ({ registry, ...props }: LocationUpdatePanelProps): React.JSX.Element => {
    const { t } = useTranslation();

    const resolvers = useMemo(() => registry.getDynamicTypesForGroup('updateLocation'), [registry]);
    const options = useMemo(
        () => resolvers.map(({ type, label }) => ({ value: type, label: t(label) })),
        [resolvers, t]
    );

    return (
        <DataImporterPanel title={t('data-importer.resolver.element-location-update')}>
            <Form.Item
                label={t('data-importer.resolver.location-update-strategy')}
                name={['resolverConfig', 'locationUpdateStrategy', 'type']}
                tooltip={t('data-importer.resolver.location-update-strategy.tooltip')}
            >
                <Select filterOption={filterByLabel} options={options} showSearch />
            </Form.Item>

            {resolvers.map((resolver) => (
                <Form.Conditional
                    condition={(values) =>
                        (values as unknown as DataImporterFormValues).resolverConfig?.locationUpdateStrategy?.type ===
                        resolver.type
                    }
                    key={resolver.id}
                >
                    {resolver.renderSettings(props)}
                </Form.Conditional>
            ))}
        </DataImporterPanel>
    );
};
