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

/**
 * Shared styles reused across the advanced-mapping-modal step components
 * (step-source, step-transformations, step-target).
 *
 * Each step file imports this hook alongside its own step-specific styles.
 */
export const useSharedStepStyles = createStyles(({ css, token }) => {
  return {
    /**
     * Standard 50/50 two-column layout used by step-source and step-target.
     * step-transformations uses its own variant (flex:1 columns + overflow:hidden).
     */
    twoColumnLayout: css`
      display: flex;
      gap: ${token.paddingXS}px;
    `,

    /**
     * Nav button row — right-aligned, used in every step footer.
     */
    navButtons: css`
      display: flex;
      justify-content: flex-end;
      gap: ${token.paddingXS}px;
    `,

    /**
     * Outline (secondary) action button — purple border, transparent background.
     * Used for "Back" and secondary actions.
     */
    outlineButton: css`
      height: 32px;
      padding: 0 15px;
      font-size: 12px;
      color: ${token.colorPrimary};
      background: ${token.colorBgContainer};
      border: 1px solid ${token.colorPrimaryBorder};
      border-radius: ${token.borderRadius}px;
      cursor: pointer;
      box-shadow: ${token.boxShadowTertiary};
    `,

    /**
     * Small box header bar (title + optional actions) used inside the purple
     * background boxes in step-transformations.
     */
    boxHeader: css`
      padding: ${token.paddingXXS}px ${token.paddingXS}px;
    `,

    boxHeaderTitle: css`
      font-size: 12px;
      font-weight: 600;
    `,

    /**
     * Full-width select helper — overrides the max-width: 9999px inline style
     * that Ant Design sets on .ant-select for multiple-mode selects, preventing
     * it from overflowing its flex container.
     */
    selectFull: css`
      width: 100%;
      &.ant-select {
        max-width: 100% !important;
      }
    `
  }
})
