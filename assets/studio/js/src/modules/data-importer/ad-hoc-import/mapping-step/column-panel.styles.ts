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

const PANEL_WIDTH = 440

export const useStyles = createStyles(({ token, css }) => ({
  backdrop: css`
    position: absolute;
    inset: 0;
    z-index: 1100;
    border: 0;
    padding: 0;
    background: rgba(0, 0, 0, 0.15);
    cursor: default;
  `,
  panel: css`
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 1100;
    display: flex;
    flex-direction: column;
    width: ${PANEL_WIDTH}px;
    background: ${token.colorBgContainer};
    box-shadow: -6px 0 16px rgba(0, 0, 0, 0.12);
    outline: none;
  `,
  head: css`
    display: flex;
    flex-direction: column;
    gap: ${token.marginXS}px;
    padding: ${token.paddingSM}px ${token.padding}px;
  `,
  caption: css`
    font-size: ${token.fontSizeSM}px;
    color: ${token.colorTextTertiary};
  `,
  name: css`
    font-size: ${token.fontSizeLG}px;
    font-weight: ${token.fontWeightStrong};
  `,
  chain: css`
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: ${token.marginXXS}px;
    padding: 6px 10px;
    border: 1px solid ${token.colorBorderSecondary};
    border-radius: ${token.borderRadius}px;
    background: ${token.Colors.Neutral.Fill.colorFillTertiary};
  `,
  chip: css`
    padding: 0 6px;
    border-radius: ${token.borderRadiusSM}px;
    background: ${token.colorPrimary};
    color: ${token.colorTextLightSolid};
  `,
  chipConversion: css`
    padding: 0 6px;
    border: 1px dashed ${token.colorBorder};
    border-radius: ${token.borderRadiusSM}px;
    background: ${token.colorBgContainer};
    color: ${token.colorTextSecondary};
  `,
  chipResult: css`
    padding: 0 6px;
    border: 1px solid ${token.colorSuccessBorder};
    border-radius: ${token.borderRadiusSM}px;
    background: ${token.colorSuccessBg};
    color: ${token.colorSuccessText};
  `,
  chipFailed: css`
    padding: 0 6px;
    border: 1px solid ${token.colorErrorBorder};
    border-radius: ${token.borderRadiusSM}px;
    background: ${token.colorErrorBg};
    color: ${token.colorErrorText};
  `,
  chipOpen: css`
    padding: 0 6px;
    border: 1px solid ${token.colorWarningBorder};
    border-radius: ${token.borderRadiusSM}px;
    background: ${token.colorWarningBg};
    color: ${token.Colors.Brand.Warning.colorWarningText};
  `,
  // the field tree fills the panel and scrolls itself; the other tabs scroll in their pane.
  // As in the design, the tab row spans the panel between two rules and its tabs sit side by side.
  tabs: css`
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;

    &.ant-tabs > .ant-tabs-nav {
      margin: 0;
      padding: 0 ${token.paddingXS}px;
      border-top: 1px solid ${token.colorBorderSecondary};
    }

    &.ant-tabs > .ant-tabs-nav::before {
      border-bottom-color: ${token.colorBorderSecondary};
    }

    &.ant-tabs > .ant-tabs-nav .ant-tabs-tab {
      padding: ${token.paddingXS}px ${token.padding}px;
    }

    &.ant-tabs > .ant-tabs-nav .ant-tabs-tab + .ant-tabs-tab {
      margin-inline-start: 0;
    }

    .ant-tabs-content-holder {
      flex: 1 1 auto;
      min-height: 0;
      padding: ${token.paddingSM}px ${token.padding}px 0;
    }

    .ant-tabs-content, .ant-tabs-tabpane-active {
      height: 100%;
    }

    .ant-tabs-tabpane-active {
      overflow: auto;
    }

    .ant-tabs-tab-active .ant-tabs-tab-btn {
      font-weight: ${token.fontWeightStrong};
    }
  `,
  actions: css`
    display: flex;
    gap: ${token.marginXS}px;
    padding: ${token.paddingSM}px ${token.padding}px;
    border-top: 1px solid ${token.colorBorderSecondary};
  `,
  grow: css`
    flex-grow: 1;
  `,
  // the design's compact previous and next buttons
  stepButton: css`
    &.ant-btn {
      width: 28px;
      min-width: 28px;
      height: 28px;
    }
  `
}))
