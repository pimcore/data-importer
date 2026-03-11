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
    header: css`
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: ${token.paddingXS}px;
    `,
    headerLeft: css`
      display: flex;
      align-items: center;
      gap: ${token.paddingXS}px;
    `,
    fullWidth: css`
      width: 100%;
    `
  }
})
