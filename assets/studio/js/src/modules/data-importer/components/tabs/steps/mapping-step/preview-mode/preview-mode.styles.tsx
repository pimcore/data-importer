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

export const useStyles = createStyles(({ css, token }) => ({
  line: css`
    display: flex;
    align-items: center;
    gap: ${token.marginSM}px;
    min-height: 32px;
    padding: 0 ${token.paddingMD}px ${token.paddingXS}px 0;
    flex-shrink: 0;
  `,

  record: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    min-width: 0;
    color: ${token.colorTextDescription};
  `,

  recordValue: css`
    max-width: 240px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: ${token.colorTextDescription};
  `,

  separator: css`
    color: ${token.colorTextDescription};
  `,

  error: css`
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  `,

  collapseAll: css`
    margin-left: auto;
  `,

  pickerHeader: css`
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: ${token.paddingXS}px;
    font-weight: 600;
  `,

  pickerColumns: css`
    margin: 0;
    display: grid;
    grid-template-columns: max-content 1fr;
    gap: ${token.marginXXS}px ${token.marginSM}px;
  `,

  pickerLabel: css`
    color: ${token.colorTextDescription};
  `,

  pickerValue: css`
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  `
}))
