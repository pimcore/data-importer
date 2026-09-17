/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import type React from 'react'
import { injectable } from '@pimcore/studio-ui-bundle/app'
import { DynamicTypeAbstract } from '@pimcore/studio-ui-bundle/modules/element'

export interface DynamicTypeResolverRenderProps {
  columnHeaderOptions: Array<{ value: string, label: string }>
  languageOptions?: Array<{ value: string, label: string }>
  classOptions?: Array<{ value: string, label: string }>
  isLoadingClasses?: boolean
}

export type ResolverGroup = 'loading' | 'createLocation' | 'updateLocation' | 'publishing'

@injectable()
export abstract class DynamicTypeResolverAbstract extends DynamicTypeAbstract {
  abstract readonly id: string
  abstract readonly type: string
  abstract readonly label: string
  abstract readonly group: ResolverGroup
  abstract renderSettings (props: DynamicTypeResolverRenderProps): React.JSX.Element | null
}
