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
    toolbar: css`
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: ${token.paddingSM}px;
      margin-bottom: ${token.paddingSM}px;
    `,

    search: css`
      width: 320px;
      max-width: 100%;
    `,

    paginationRow: css`
      display: flex;
      justify-content: flex-end;
      margin-top: ${token.paddingSM}px;
    `,

    footer: css`
      display: flex;
      justify-content: flex-end;
      gap: ${token.paddingXS}px;
      margin-top: ${token.paddingSM}px;
    `
  }
})
