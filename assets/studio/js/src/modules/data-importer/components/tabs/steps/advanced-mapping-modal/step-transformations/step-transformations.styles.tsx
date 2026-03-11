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

export const useStyles = createStyles(({ css, token }) => {
  return {
    twoColumnLayout: css`
      display: flex;
      gap: ${token.paddingXS}px;
      width: 100%;
      overflow: hidden;
    `,

    /* ── Left column ──────────────────────────────────────────────────────── */

    leftColumn: css`
      flex: 1 0 0;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: ${token.paddingXS}px;
    `,

    /**
     * Header row: "Transformations" title + "+ New" button + "Collapse all" link.
     * No background — sits directly on the step content area.
     */
    listHeader: css`
      display: flex;
      align-items: center;
      gap: ${token.paddingXS}px;
      height: 32px;
      flex-shrink: 0;
    `,

    listHeaderTitle: css`
      font-size: 12px;
      font-weight: 600;
      color: ${token.colorPrimaryText};
      flex: 1;
    `,

    collapseAllLink: css`
      font-size: 12px;
      color: ${token.colorPrimaryText};
      cursor: pointer;
      white-space: nowrap;

      &:hover {
        text-decoration: underline;
      }
    `,

    /* Scrollable list of transformer cards */
    itemsList: css`
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1;
      overflow-y: auto;
    `,

    emptyState: css`
      font-size: 12px;
      color: ${token.colorTextQuaternary};
    `,

    /* ── Transformer card DragOverlay wrapper ────────────────────────────── */
    /* Card-specific styles (transformerCard, header, handle, etc.) now live  */
    /* in transformer-card/transformer-card.styles.tsx                        */

    transformerCardOverlay: css`
      border: 1px solid ${token.colorBorderSecondary};
      border-radius: ${token.borderRadiusSM}px;
      padding: 6px ${token.paddingXS}px;
      display: flex;
      flex-direction: column;
      gap: ${token.paddingXXS}px;
      background: ${token.colorFillAdditional};
      box-shadow: ${token.boxShadowSecondary};
      cursor: grabbing;
    `,

    /* ── Right column ─────────────────────────────────────────────────────── */

    rightColumn: css`
      flex: 1 0 0;
      min-width: 0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: ${token.paddingXS}px;
      padding-left: ${token.paddingXXS}px;
    `,

    rightColumnTop: css`
      display: flex;
      flex-direction: column;
      gap: ${token.paddingXS}px;
    `,

    /**
     * Source Attribute(s) section — plain read-only display, no colored box.
     */
    sourceSection: css`
      display: flex;
      flex-direction: column;
      gap: ${token.paddingXXS}px;
    `,

    sourceSectionHeader: css`
      display: flex;
      align-items: center;
      gap: ${token.paddingXXS}px;
      height: 32px;
    `,

    sourceSectionTitle: css`
      font-size: 12px;
      font-weight: 600;
      color: ${token.colorPrimaryText};
    `,

    /** Pipe-separated list of selected source attribute values */
    sourceValues: css`
      font-size: 12px;
      color: ${token.colorText};
      line-height: 22px;
      flex-wrap: wrap;
      display: flex;
      gap: 0;
    `,

    sourceSeparator: css`
      color: ${token.colorTextSecondary};
      margin: 0 4px;
    `,

    previewWrapper: css`
      flex: 1;
      overflow: hidden;
    `
  }
})
