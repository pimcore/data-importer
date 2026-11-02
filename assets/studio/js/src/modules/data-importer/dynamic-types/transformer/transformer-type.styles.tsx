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

export const useTransformerTypeStyles = createStyles(({ css }) => {
  return {
    label: css`
      font-size: 11px;
      white-space: nowrap;
    `,

    formItem: css`
      margin-bottom: 6px;
    `,

    formItemSwitch: css`
      margin-bottom: 0 !important;
      .ant-form-item-row {
        row-gap: 0;
      }
    `,

    formItemLast: css`
      margin-bottom: 0 !important;
      .ant-form-item-row {
        row-gap: 0;
      }
    `,

    noSettings: css`
      font-size: 11px;
    `
  }
})
