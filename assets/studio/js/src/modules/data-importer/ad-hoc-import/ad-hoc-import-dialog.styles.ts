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

    /* the board draws the step numbers at the small font size */
    && .ant-steps-item-icon {
      font-size: ${token.fontSizeSM}px;
      line-height: 1;
    }

    && .ant-steps-item-icon .ant-steps-icon {
      font-size: inherit;
      line-height: inherit;
    }

    && .ant-steps-item-process .ant-steps-item-title {
      font-weight: ${token.fontWeightStrong};
    }

    /* a finished step is a light circle with the check in the primary colour, as on the board;
       Studio's Steps sets the check white with !important for its filled circle */
    && .ant-steps-item-finish .ant-steps-item-icon {
      background-color: ${token.colorPrimaryBg};
      border-color: ${token.colorPrimaryBg};
    }

    && .ant-steps-item-finish .ant-steps-item-icon .ant-steps-icon {
      color: ${token.colorPrimary} !important;
    }
  `
}))
