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

export const useStyles = createStyles(({ css, token }) => {
  return {
    fullWidth: css`
      width: 100%;
    `,
    loggingGroups: css`
      display: flex;
      flex-direction: column;
      gap: ${token.paddingSM}px;
      width: 100%;
    `,
    loggingGroup: css`
      width: 100%;
    `,
    loggingGroupTitle: css`
      margin-bottom: ${token.paddingXS}px;
      font-size: ${token.fontSize}px;
      font-weight: 600;
      color: ${token.colorTextHeading};
    `,
    loggingItem: css`
      margin-bottom: ${token.paddingXS}px;
    `,
    loggingItemLast: css`
      margin-bottom: 0;
    `
  }
})
