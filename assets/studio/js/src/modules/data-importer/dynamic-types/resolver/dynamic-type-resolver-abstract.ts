/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import type React from 'react';
import { injectable } from '@pimcore/studio-ui-bundle/app';
import { DynamicTypeAbstract } from '@pimcore/studio-ui-bundle/modules/element';

export type ResolverGroup = 'loading' | 'location' | 'publishing';

@injectable()
export abstract class DynamicTypeResolverAbstract extends DynamicTypeAbstract {
    abstract readonly id: string;
    abstract readonly label: string;
    abstract readonly group: ResolverGroup;
    abstract renderSettings(): React.JSX.Element | null;
}
