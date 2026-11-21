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
  stepDetail: css`
    color: ${token.colorTextSecondary};
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
  // light grey, as the design draws it: the theme's disabled text is darkened for contrast, which a bar needs not
  resultBarSkipped: css`
    background: ${token.colorBorder};
  `,
  resultContent: css`
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr);
    gap: ${token.marginXXL}px;
    padding: ${token.paddingLG}px ${token.paddingXL}px;
  `,
  summary: css`
    display: flex;
    flex-direction: column;
    gap: ${token.marginXXS}px;
  `,
  resultFootnote: css`
    padding: ${token.paddingSM}px ${token.paddingXL}px;
    border-top: 1px solid ${token.colorBorderSecondary};
    background: ${token.Table.headerBg};
    color: ${token.colorTextSecondary};
  `,
  complete: css`
    display: flex;
    align-items: center;
    gap: ${token.marginXS}px;
    color: ${token.colorSuccessText};
    font-weight: 700;
  `,
  imported: css`
    font-size: ${token.fontSizeHeading1}px;
    line-height: ${token.lineHeightHeading1};
    font-weight: 700;
    letter-spacing: -0.5px;
  `,
  objectsFrom: css`
    font-size: ${token.fontSizeLG}px;
    line-height: ${token.lineHeightLG};
  `,
  lines: css`
    display: flex;
    flex-direction: column;
    justify-content: center;
  `,
  dot: css`
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
    background: ${token.colorBorder};
  `,
  // dot, label, count and action each in their own column, so the counts and actions line up
  line: css`
    display: grid;
    grid-template-columns: 10px minmax(0, 1fr) 48px 120px;
    gap: 10px;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid ${token.colorBorderSecondary};
  `,
  lineCount: css`
    font-weight: 700;
    text-align: right;
  `,
  lineAction: css`
    display: flex;
    justify-content: flex-end;

    .ant-btn {
      padding: 0;
      height: auto;
    }
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
    white-space: normal;
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
  `,
  // antd draws a normal bar in colorInfo; the canvas draws it in the primary colour
  barColor: token.colorPrimary
}))
