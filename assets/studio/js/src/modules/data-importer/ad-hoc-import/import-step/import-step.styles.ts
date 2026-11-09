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
      position: relative;
      overflow: hidden;
    }
  `,
  resultBar: css`
    position: absolute;
    inset: 0 0 auto;
    display: flex;
    height: 4px;
  `,
  resultBarUpdated: css`
    background: ${token.colorSuccess};
  `,
  resultBarCreated: css`
    background: ${token.colorPrimary};
  `,
  resultBarSkipped: css`
    background: ${token.colorTextDisabled};
  `,
  resultContent: css`
    display: flex;
    gap: ${token.marginXL}px;
  `,
  complete: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXXS}px;
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
  dot: css`
    flex: none;
    align-self: center;
    width: 8px;
    height: 8px;
    border-radius: 50%;
  `,
  dotUpdated: css`
    background: ${token.colorSuccess};
  `,
  dotCreated: css`
    background: ${token.colorPrimary};
  `,
  dotSkipped: css`
    background: ${token.colorTextDisabled};
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
  notSaved: css`
    margin-bottom: ${token.marginSM}px;
  `,
  failedCount: css`
    border-color: ${token.colorErrorBorder};
    background: ${token.colorErrorBg};
  `,
  error: css`
    display: flex;
    align-items: center;
    width: 100%;
    height: 100%;
    padding: ${token.paddingXXS}px ${token.paddingXS}px;
    color: ${token.colorError};
    overflow-wrap: anywhere;
  `,
  branchStep: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    padding: ${token.paddingXS}px 0;
    border-bottom: 1px solid ${token.colorBorderSecondary};
  `,
  branchStepLabel: css`
    flex: 1 1 0;
  `,
  branchStepActive: css`
    font-weight: 700;
  `,
  branchStepDone: css`
    color: ${token.colorSuccess};
  `,
  branchStepPending: css`
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 1px solid ${token.colorBorder};
    border-radius: 50%;
  `
}))
