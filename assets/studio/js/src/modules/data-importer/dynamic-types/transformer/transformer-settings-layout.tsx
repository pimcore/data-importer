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
import { Form } from '@pimcore/studio-ui-bundle/components'
import { FieldWidthProvider } from '@pimcore/studio-ui-bundle/modules/element'
import { useTransformerTypeStyles } from './transformer-type.styles'

type StylesType = ReturnType<typeof useTransformerTypeStyles>['styles']

interface TransformerSettingsLayoutProps {
  children: (styles: StylesType) => React.ReactNode
}

export const TransformerSettingsLayout = ({ children }: TransformerSettingsLayoutProps): React.JSX.Element => {
  const { styles } = useTransformerTypeStyles()

  return (
    <FieldWidthProvider>
      <Form
        colon={ false }
        layout="vertical"
      >
        { children(styles) }
      </Form>
    </FieldWidthProvider>
  )
}
