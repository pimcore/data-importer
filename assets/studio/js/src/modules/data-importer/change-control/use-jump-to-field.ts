/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type RefObject, useCallback, useEffect, useRef, useState } from 'react'

/** how long the jumped-to field stays marked before the rail goes quiet again */
const FLASH_MS = 1800

/** frames to wait for the tab and step to lay out before measuring anything */
const LAYOUT_FRAMES = 2

/** a form path as an element id; dots are legal in an id but awkward in a selector */
export const fieldAnchorId = (formPath: string): string =>
  `di-field-${formPath.replace(/[^a-zA-Z0-9]+/g, '-')}`

interface JumpToField {
  /** the address currently marked as the jump target, for the rail to highlight */
  readonly target: string | null
  /** scroll the pane to a field once the tab and step it lives in have rendered */
  readonly jumpTo: (formPath: string, address: string) => void
}

/**
 * Scrolls a review pane to a field.
 *
 * The Data Setup steps are hidden with `display: none`, so a field in an inactive step has
 * no box to measure until the step switches AND the browser has laid it out again. Reading
 * offsetTop in the same tick returns 0 and the pane jumps to the top instead — hence the
 * wait for two frames rather than a single one.
 */
export function useJumpToField (paneRef: RefObject<HTMLDivElement | null>): JumpToField {
  const [target, setTarget] = useState<string | null>(null)
  const pending = useRef<string | null>(null)
  const timers = useRef<number[]>([])

  useEffect(() => () => {
    timers.current.forEach((id) => { window.clearTimeout(id) })
  }, [])

  const jumpTo = useCallback((formPath: string, address: string): void => {
    pending.current = fieldAnchorId(formPath)
    setTarget(address)

    let frames = 0
    const settle = (): void => {
      if (frames < LAYOUT_FRAMES) {
        frames += 1
        window.requestAnimationFrame(settle)
        return
      }

      const pane = paneRef.current
      const anchorId = pending.current
      if (pane === null || anchorId === null) return

      const anchor = pane.querySelector(`[data-field-anchor="${anchorId}"]`)
      // the annotation renders the anchor inside the item, so its own box is the field's
      const field = anchor?.closest('.ant-form-item') ?? anchor
      if (field === null || field === undefined) return

      const paneBox = pane.getBoundingClientRect()
      const fieldBox = field.getBoundingClientRect()
      pane.scrollTop += fieldBox.top - paneBox.top - 24
    }
    window.requestAnimationFrame(settle)

    timers.current.push(window.setTimeout(() => {
      setTarget((current) => (current === address ? null : current))
    }, FLASH_MS))
  }, [paneRef])

  return { target, jumpTo }
}
