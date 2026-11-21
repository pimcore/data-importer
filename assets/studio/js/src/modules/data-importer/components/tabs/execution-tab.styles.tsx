/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { createStyles } from 'antd-style'

export const useStyles = createStyles(({ token, css }) => {
  return {
    progressLabel: css`
      font-size: 12px;
      font-weight: 400;
      line-height: 22px;
      color: ${token.colorText};
      margin: 0;
    `,

    progressWrapper: css`
      width: 100%;

      /* antd Progress root is inline-flex by default — override to fill full width */
      .ant-progress {
        display: block;
        width: 100%;
        margin-bottom: 0;
      }

      /* Ensure the inner bar line fills the wrapper */
      .ant-progress-inner {
        width: 100% !important;
      }

      /* Pad the inner text so it sits 8px from the left edge */
      .ant-progress-text {
        padding-inline-start: ${token.paddingXS}px;
      }
    `,

    /* Expose resolved token values as plain strings so the component can pass them to Progress props */
    colorFill: token.colorFill,
    colorBgLayout: token.colorBgLayout
  }
})
