/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { createStyles } from '@pimcore/studio-ui-bundle/app'

export const STEPS_WIDTH = 720

// the board sets the steps apart from the title and closes the header with a line across the dialog
export const useStyles = createStyles(({ token, css }) => ({
  header: css`
    margin: ${token.margin}px -${token.paddingLG}px ${token.margin}px;
    padding: 0 ${token.paddingLG}px ${token.padding}px;
    border-bottom: ${token.lineWidth}px solid ${token.colorBorderSecondary};
  `,
  steps: css`
    max-width: ${STEPS_WIDTH}px;

    && .ant-steps-item-title {
      font-size: ${token.fontSizeLG}px;
    }

    && .ant-steps-item-process .ant-steps-item-title {
      font-weight: ${token.fontWeightStrong};
    }
  `
}))
