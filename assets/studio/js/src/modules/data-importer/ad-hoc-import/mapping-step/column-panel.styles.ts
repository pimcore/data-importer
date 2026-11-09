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
    background: ${token.colorBgLayout};
  `,
  chip: css`
    padding: 0 6px;
    border-radius: ${token.borderRadiusSM}px;
    background: ${token.colorPrimary};
    color: ${token.colorTextLightSolid};
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
    color: ${token.colorWarningText};
  `,
  tabs: css`
    flex: 1 1 auto;
    min-height: 0;
    padding: 0 ${token.padding}px;
    overflow: auto;
  `,
  actions: css`
    display: flex;
    gap: ${token.marginXS}px;
    padding: ${token.paddingSM}px ${token.padding}px;
    border-top: 1px solid ${token.colorBorderSecondary};
  `,
  grow: css`
    flex-grow: 1;
  `
}))
