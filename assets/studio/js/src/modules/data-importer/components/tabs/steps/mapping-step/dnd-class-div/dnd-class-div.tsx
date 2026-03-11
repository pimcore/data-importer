/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import React from 'react'
import { useDroppable } from '@pimcore/studio-ui-bundle/components'

// Generic div that reads the nearest Droppable context and applies the
// appropriate dnd--drag-active/valid/error CSS classes to itself.
// Use it wherever you want visual DnD feedback inside a <Droppable>.

export interface DndClassDivProps {
  children: React.ReactNode
  className?: string
}

export const DndClassDiv = ({ children, className }: DndClassDivProps): React.JSX.Element => {
  const { getStateClasses } = useDroppable()
  const combinedClass = [className, ...getStateClasses()].filter(Boolean).join(' ')

  return (
    <div className={ combinedClass }>
      { children }
    </div>
  )
}
