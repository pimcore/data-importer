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
import { BOLD, DIALOG_PADDING } from './dialog-layout'

const STEPS_WIDTH = 720

export const STEPS = ['file', 'mapping', 'preview', 'import']

const useStyles = createStyles(({ token, css }) => ({
  band: css`
    margin: ${token.marginSM}px -${DIALOG_PADDING}px ${token.marginSM}px;
    padding: 0 ${DIALOG_PADDING}px ${token.padding}px;
    border-bottom: ${token.lineWidth}px solid ${token.colorBorderContainer};
  `,
  // the small steps keep their 24px circles; the numbers and titles take the default size's text, which antd's
  // small-size rules would otherwise win over
  steps: css`
    max-width: ${STEPS_WIDTH}px;

    .ant-steps.ant-steps-small .ant-steps-item-icon {
      font-size: ${token.fontSize}px;
    }

    .ant-steps.ant-steps-small .ant-steps-item-title {
      font-size: ${token.fontSizeLG}px;
    }

    .ant-steps.ant-steps-small .ant-steps-item-process .ant-steps-item-title {
      font-weight: ${BOLD};
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
