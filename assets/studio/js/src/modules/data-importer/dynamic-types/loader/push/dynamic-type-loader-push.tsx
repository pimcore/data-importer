/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from 'react'
import { injectable } from '@pimcore/studio-ui-bundle/app'
import { DynamicTypeLoaderAbstract } from '../dynamic-type-loader-abstract'
import { PushLoaderSettings } from './push-loader-settings'

@injectable()
export class DynamicTypeLoaderPush extends DynamicTypeLoaderAbstract {
    readonly id = 'push'
    readonly label = 'Push'

    renderSettings (_configName: string): React.JSX.Element | null {
        return <PushLoaderSettings />
    }
}
