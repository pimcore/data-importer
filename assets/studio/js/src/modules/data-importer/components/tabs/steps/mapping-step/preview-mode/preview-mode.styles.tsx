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

  recordInput: css`
    width: 64px;
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

  hint: css`
    display: flex;
    flex: 1 1 auto;
    flex-wrap: wrap;
    align-items: center;
    gap: 0 ${token.marginXXS}px;
    min-width: 0;
  `,

  hintText: css`
    min-width: 0;
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
  `
}))
