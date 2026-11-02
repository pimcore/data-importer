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
    // height:100% resolves from the ant-tabs-tabpane → formWrapper chain above.
    // min-height:0 prevents flex children from overflowing.
    tabLayout: css`
      height: 100%;
      min-height: 0;
    `,

    // Non-mapping steps: normal scrollable content
    stepContent: css`
      flex: 1;
      min-height: 0;
      overflow-y: auto;
      padding: ${token.paddingXS}px ${token.paddingSM}px;
    `,

    stepContentHidden: css`
      display: none;
    `,

    // Mapping step: fills all remaining height.
    // height:0 + flex:1 is the canonical trick that makes height:100% on
    // children resolve against the flex-grown size rather than 'auto'.
    // overflow:auto (not hidden) allows a horizontal scrollbar when the
    // mapping layout's min-width:800px exceeds the available viewport width.
    stepContentMapping: css`
      flex: 1;
      height: 0;
      overflow: auto;
    `,

    // Hides the mapping step container when another step is active,
    // without unmounting it — preserving all loaded state and avoiding re-fetches.
    stepContentMappingHidden: css`
      display: none;
    `
  }
})
