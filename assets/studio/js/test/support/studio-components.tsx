/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

// Plain stand-ins for the Studio components the mapping step and dialog render; Form is antd's own
import React from 'react'

export { Form } from 'antd'

interface WithChildren {
  children?: React.ReactNode
}

export const Flex = ({ children }: WithChildren): React.JSX.Element => <div>{ children }</div>
export const Space = Flex
export const Box = Flex
export const Text = ({ children }: WithChildren): React.JSX.Element => <span>{ children }</span>
export const Spin = (): React.JSX.Element => <progress />
export const Checkbox = (): null => null
export const Select = (): null => null

export const Content = ({ children, loading }: WithChildren & { loading?: boolean }): React.JSX.Element => (
  <div
    aria-busy={ loading === true }
    data-testid="content"
  >
    { children }
  </div>
)

export const Panel = ({ children, title }: WithChildren & { title?: React.ReactNode }): React.JSX.Element => (
  <section>
    { title }
    { children }
  </section>
)

export const Modal = ({ children, footer, open, title }: WithChildren & { footer?: React.ReactNode, open?: boolean, title?: string }): React.JSX.Element | null => {
  if (open !== true) return null

  return (
    <dialog
      aria-label={ title }
      open
    >
      { children }
      { footer }
    </dialog>
  )
}

export const Button = ({ children, disabled, onClick }: WithChildren & { disabled?: boolean, onClick?: () => void }): React.JSX.Element => (
  <button
    disabled={ disabled }
    onClick={ onClick }
    type="button"
  >
    { children }
  </button>
)

export const IconButton = ({ disabled, icon, onClick }: { disabled?: boolean, icon: { value: string }, onClick?: () => void }): React.JSX.Element => (
  <button
    aria-label={ icon.value }
    disabled={ disabled }
    onClick={ onClick }
    type="button"
  />
)

export const SearchInput = ({ onChange, placeholder, value }: { onChange?: React.ChangeEventHandler<HTMLInputElement>, placeholder?: string, value?: string }): React.JSX.Element => (
  <input
    onChange={ onChange }
    placeholder={ placeholder }
    value={ value }
  />
)

export const useFormModal = (): { confirm: () => void } => ({ confirm: () => undefined })
export const useMessage = (): { warning: () => Promise<void> } => ({ warning: async () => { await Promise.resolve() } })
