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
    // the theme's success text is the light green, below AA on white; green 7 reaches it
    color: ${token.green7};
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
    // the theme's error text is the light red, below AA on white; red 7 reaches it
    color: ${token.red7};
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
  // a filled circle with a white check, as no Studio icon draws one
  doneIcon: css`
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: ${token.colorSuccess};
    color: ${token.colorTextLightSolid};
  `,
  // the spinner takes the box of the other steps' icons, so every label starts at the same place
  branchStepActiveIcon: css`
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    font-size: 14px;
    color: ${token.colorPrimary};
  `,
  // a cell of one's own loses the grid's padding
  rowNumber: css`
    display: block;
    padding: ${token.paddingXXS}px ${token.paddingXS}px;
    color: ${token.colorTextTertiary};
  `,
  branchStepPending: css`
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 1px solid ${token.colorBorder};
    border-radius: 50%;
  `,
  // antd draws a normal bar in colorInfo; the canvas draws it in the primary colour
  barColor: token.colorPrimary
}))
