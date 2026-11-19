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
    { /* as Studio's Content: a spinner instead of the children while loading */ }
    { loading !== true && children }
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

// antd's number field as a plain input: the typed number (null when empty) on change, Enter as onPressEnter,
// the error status as aria-invalid
export const InputNumber = ({ disabled, id, onBlur, onChange, onPressEnter, status, value, ...props }: {
  'aria-label'?: string
  disabled?: boolean
  id?: string
  onBlur?: React.FocusEventHandler<HTMLInputElement>
  onChange?: (value: number | null) => void
  onPressEnter?: React.KeyboardEventHandler<HTMLInputElement>
  status?: 'error' | 'warning'
  value?: number | null
}): React.JSX.Element => (
  <input
    aria-invalid={ status === 'error' }
    aria-label={ props['aria-label'] }
    disabled={ disabled }
    id={ id }
    onBlur={ onBlur }
    onChange={ (event) => { onChange?.(event.target.value === '' ? null : Number(event.target.value)) } }
    onKeyDown={ (event) => { if (event.key === 'Enter') onPressEnter?.(event) } }
    value={ value ?? '' }
  />
)

export const SearchInput = ({ onChange, placeholder, value }: { onChange?: React.ChangeEventHandler<HTMLInputElement>, placeholder?: string, value?: string }): React.JSX.Element => (
  <input
    onChange={ onChange }
    placeholder={ placeholder }
    value={ value }
  />
)

export const IconTextButton = ({ children, onClick }: WithChildren & { onClick?: () => void }): React.JSX.Element => (
  <button
    onClick={ onClick }
    type="button"
  >
    { children }
  </button>
)

export const Divider = (): React.JSX.Element => <hr />

export const Switch = ({ checked, disabled, labelRight, onChange, ...props }: { 'aria-label'?: string, checked?: boolean, disabled?: boolean, labelRight?: React.ReactNode, onChange?: (checked: boolean) => void }): React.JSX.Element => (
  <label>
    <input
      aria-label={ props['aria-label'] }
      checked={ checked === true }
      disabled={ disabled }
      onChange={ (event) => { onChange?.(event.target.checked) } }
      role="switch"
      type="checkbox"
    />
    { labelRight }
  </label>
)

// the tooltip's content shows beside its target, so tests can read it
export const Tooltip = ({ children, title }: WithChildren & { title?: React.ReactNode }): React.JSX.Element => (
  <span>
    { children }
    <span role="tooltip">{ title }</span>
  </span>
)

// a small real preview-mode context, as Studio's PreviewModeProvider keeps it
interface PreviewModeItem { data: { id?: string | number, [key: string]: any } }
interface PreviewModeValue {
  enabled: boolean
  setEnabled: (enabled: boolean) => void
  item: PreviewModeItem | null
  setItem: (item: PreviewModeItem | null) => void
  fallbackItem: { id: string | number } | null
}

const PreviewModeContext = React.createContext<PreviewModeValue | undefined>(undefined)

export const PreviewModeProvider = ({ children, defaultEnabled = false, fallbackItem = null }: WithChildren & { defaultEnabled?: boolean, fallbackItem?: { id: string | number } | null }): React.JSX.Element => {
  const [enabled, setEnabled] = React.useState(defaultEnabled)
  const [item, setItem] = React.useState<PreviewModeItem | null>(null)
  const value = React.useMemo(() => ({ enabled, setEnabled, item, setItem, fallbackItem }), [enabled, item, fallbackItem?.id])

  return <PreviewModeContext.Provider value={ value }>{ children }</PreviewModeContext.Provider>
}

export const usePreviewMode = (): PreviewModeValue => {
  const value = React.useContext(PreviewModeContext)
  if (value === undefined) throw new Error('usePreviewMode must be used within a PreviewModeProvider')
  return value
}

export const useFormModal = (): { confirm: () => void } => ({ confirm: () => undefined })
export const useMessage = (): { warning: () => Promise<void> } => ({ warning: async () => { await Promise.resolve() } })

// an upload dialog whose upload a test finishes with one click
export const ImportModal = ({ open, title, onUploadSuccess }: { open?: boolean, title?: string, onUploadSuccess?: () => void }): React.JSX.Element | null => {
  if (open !== true) return null

  return (
    <dialog
      aria-label={ title }
      open
    >
      <button
        onClick={ () => { onUploadSuccess?.() } }
        type="button"
      >
        finish upload
      </button>
    </dialog>
  )
}
