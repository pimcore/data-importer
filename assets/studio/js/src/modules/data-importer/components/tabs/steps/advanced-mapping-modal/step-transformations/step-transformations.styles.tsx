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
      width: 100%;
      overflow: hidden;
    `,

    /* ── Left column ──────────────────────────────────────────────────────── */

    leftColumn: css`
      flex: 1 0 0;
      min-width: 0;
    `,

    /**
     * Header row: "Transformations" title + "+ New" button.
     * No background — sits directly on the step content area.
     */
    listHeader: css`
      height: 32px;
      flex-shrink: 0;
    `,

    listHeaderTitle: css`
      font-size: 12px;
      font-weight: 600;
      color: ${token.colorPrimaryText};
      flex: 1;
    `,

    /* Scrollable list of transformer cards */
    itemsList: css`
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
      gap: ${token.paddingXXS}px;
      background: ${token.colorFillAdditional};
      box-shadow: ${token.boxShadowSecondary};
      cursor: grabbing;
    `,

    /* ── Right column ─────────────────────────────────────────────────────── */

    rightColumn: css`
      flex: 1 0 0;
      min-width: 0;
      padding-left: ${token.paddingXXS}px;
    `,

    sourceSectionHeader: css`
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
