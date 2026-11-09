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

export const useStyles = createStyles(({ token, css }) => ({
  card: css`
    max-width: 640px;
  `,
  header: css`
    display: flex;
    justify-content: space-between;
    margin-bottom: ${token.marginXS}px;
  `,
  title: css`
    font-weight: 700;
  `,
  muted: css`
    color: ${token.colorTextSecondary};
  `,
  step: css`
    display: flex;
    justify-content: space-between;
    padding: ${token.paddingXS}px 0;
    margin-top: ${token.marginSM}px;
    border-bottom: 1px solid ${token.colorBorderSecondary};
    font-weight: 700;
  `,
  hint: css`
    margin-top: ${token.marginSM}px;
    color: ${token.colorTextSecondary};
  `,
  result: css`
    &.ant-card {
      border-top: 4px solid ${token.colorSuccess};
    }
  `,
  resultContent: css`
    display: flex;
    gap: ${token.marginXL}px;
  `,
  complete: css`
    color: ${token.colorSuccess};
    font-weight: 700;
  `,
  imported: css`
    font-size: 36px;
    line-height: 1.2;
    font-weight: 700;
  `,
  lines: css`
    flex: 1 1 0;
  `,
  line: css`
    display: flex;
    gap: ${token.marginSM}px;
    align-items: baseline;
    padding: ${token.paddingSM}px 0;
    border-bottom: 1px solid ${token.colorBorderSecondary};
  `,
  lineLabel: css`
    flex: 1 1 0;
  `,
  lineCount: css`
    font-weight: 700;
  `,
  footnote: css`
    margin-top: ${token.marginSM}px;
    color: ${token.colorTextSecondary};
  `,
  notSaved: css`
    margin-bottom: ${token.marginSM}px;
  `,
  failedCount: css`
    border-color: ${token.colorErrorBorder};
    background: ${token.colorErrorBg};
  `,
  error: css`
    color: ${token.colorError};
  `
}))
