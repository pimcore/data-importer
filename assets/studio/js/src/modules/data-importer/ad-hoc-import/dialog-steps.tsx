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
import { createStyles, useTranslation } from '@pimcore/studio-ui-bundle/app'
import { Steps } from '@pimcore/studio-ui-bundle/components'

const STEPS_WIDTH = 720

export const STEPS = ['file', 'mapping', 'preview', 'import']

// the modal's content padding, which the band spans so its line runs across the dialog
const DIALOG_PADDING = 24

const useStyles = createStyles(({ token, css }) => ({
  band: css`
    margin: ${token.marginSM}px -${DIALOG_PADDING}px ${token.marginSM}px;
    padding: 0 ${DIALOG_PADDING}px ${token.padding}px;
    border-bottom: ${token.lineWidth}px solid ${token.colorBorderContainer};
  `,
  steps: css`
    max-width: ${STEPS_WIDTH}px;

    .ant-steps-item-title {
      font-size: ${token.fontSizeLG}px;
    }

    .ant-steps-item-process .ant-steps-item-title {
      font-weight: ${token.fontWeightStrong};
    }
  `
}))

export interface DialogStepsProps {
  // past the last step once the import has ended
  current: number
}

/**
 * The steps belong to the dialog's header: Studio's Modal has no place for them there, so they open the body, set off
 * from the step below by the header's line.
 */
export const DialogSteps = ({ current }: DialogStepsProps): React.JSX.Element => {
  const { t } = useTranslation()
  const { styles } = useStyles()

  return (
    <div className={ styles.band }>
      <div className={ styles.steps }>
        <Steps
          current={ current }
          items={ STEPS.map((name) => ({ title: t(`data-importer.ad-hoc-import.step.${name}`) })) }
          size="small"
        />
      </div>
    </div>
  )
}
