/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

// Test stand-ins for the Studio components of the ad hoc import, and the English catalogue behind t()
import fs from 'node:fs'
import path from 'node:path'
import React from 'react'

const catalogue: Record<string, string> = Object.fromEntries(
  fs.readFileSync(path.resolve(__dirname, '../../../../../src/Resources/translations/studio.en.yaml'), 'utf8')
    .split('\n')
    .map((line) => /^([\w.-]+): (.*)$/.exec(line))
    .filter((match): match is RegExpExecArray => match !== null)
    .map((match) => [match[1], match[2].replace(/^'(.*)'$/, '$1')])
)

export const translate = (key: string, values: Record<string, unknown> = {}): string => {
  const text = catalogue[key]
  if (text === undefined) {
    throw new Error(`No text for ${key} in the catalogue`)
  }

  return text.replace(/{{(\w+)}}/g, (_, name: string) => String(values[name]))
}

export const useTranslation = (): { t: typeof translate, i18n: { language: string } } => ({ t: translate, i18n: { language: 'en' } })

interface WithChildren {
  children?: React.ReactNode
}

interface SelectProps {
  id?: string
  disabled?: boolean
  placeholder?: string
  value?: string
  options?: Array<{ value: string, label: string, disabled?: boolean }>
  onChange?: (value: string) => void
  'aria-label'?: string
}

export const components = {
  Alert: ({ message, description }: { message: string, description?: string }) => (
    <div role="alert">
      <strong>{ message }</strong>
      <span>{ description }</span>
    </div>
  ),
  Button: ({ children, disabled, onClick, 'aria-label': label }: WithChildren & { disabled?: boolean, onClick?: () => void, 'aria-label'?: string }) => (
    <button
      aria-label={ label }
      disabled={ disabled }
      onClick={ onClick }
      type="button"
    >
      { children }
    </button>
  ),
  Flex: ({ children, role, 'aria-label': label }: WithChildren & { role?: string, 'aria-label'?: string }) => (
    <div
      aria-label={ label }
      role={ role }
    >
      { children }
    </div>
  ),
  Form: Object.assign(({ children }: WithChildren) => <form>{ children }</form>, {
    Item: ({ children, label, extra, htmlFor }: WithChildren & { label?: React.ReactNode, extra?: React.ReactNode, htmlFor?: string }) => (
      <div>
        <label htmlFor={ htmlFor }>{ label }</label>
        { children }
        <small>{ extra }</small>
      </div>
    )
  }),
  FormKit: {
    Panel: ({ children, title }: WithChildren & { title?: string }) => (
      <section aria-label={ title }>{ children }</section>
    )
  },
  Grid: ({ columns, data, isLoading }: { columns: Array<{ id: string, header: string, accessorFn: (row: unknown) => string }>, data: unknown[], isLoading?: boolean }) => (
    <table aria-busy={ isLoading }>
      <thead>
        <tr>{ columns.map((column) => <th key={ column.id }>{ column.header }</th>) }</tr>
      </thead>
      <tbody>
        { data.map((row) => (
          <tr key={ JSON.stringify(row) }>{ columns.map((column) => <td key={ column.id }>{ column.accessorFn(row) }</td>) }</tr>
        )) }
      </tbody>
    </table>
  ),
  Icon: () => null,
  IconButton: ({ onClick, 'aria-label': label }: { onClick?: () => void, 'aria-label'?: string }) => (
    <button
      aria-label={ label }
      onClick={ onClick }
      type="button"
    />
  ),
  Input: ({ id, value, onBlur, onChange }: { id?: string, value?: string, onBlur?: React.FocusEventHandler<HTMLInputElement>, onChange?: React.ChangeEventHandler<HTMLInputElement> }) => (
    <input
      id={ id }
      onBlur={ onBlur }
      onChange={ onChange }
      value={ value }
    />
  ),
  ManyToOneRelationPath: ({ value, onChange }: { value?: string | null, onChange?: (value: string | null) => void }) => (
    <input
      aria-label="folder"
      onChange={ (event) => { onChange?.(event.target.value === '' ? null : event.target.value) } }
      value={ value ?? '' }
    />
  ),
  Modal: ({ children, footer, title, onCancel }: WithChildren & { footer?: React.ReactNode, title?: string, onCancel?: () => void }) => (
    <dialog
      aria-label={ title }
      open
    >
      <button
        aria-label="close dialog"
        onClick={ onCancel }
        type="button"
      />
      { children }
      { footer }
    </dialog>
  ),
  ModalFooter: ({ children }: WithChildren) => <footer>{ children }</footer>,
  Progress: ({ percent }: { percent?: number }) => (
    <progress
      max={ 100 }
      value={ percent }
    />
  ),
  Segmented: ({ options, value, onChange }: { options: Array<{ value: string, label: string, disabled?: boolean }>, value: string, onChange?: (value: string) => void }) => (
    <div role="radiogroup">
      { options.map((option) => (
        <button
          aria-checked={ option.value === value }
          disabled={ option.disabled }
          key={ option.value }
          onClick={ () => { onChange?.(option.value) } }
          role="radio"
          type="button"
        >
          { option.label }
        </button>
      )) }
    </div>
  ),
  Select: ({ id, disabled, placeholder, value, options, onChange, 'aria-label': label }: SelectProps) => (
    <select
      aria-label={ label }
      disabled={ disabled }
      id={ id }
      onChange={ (event) => { onChange?.(event.target.value) } }
      value={ value ?? '' }
    >
      <option value="">{ placeholder }</option>
      { options?.map((option) => (
        <option
          disabled={ option.disabled }
          key={ option.value }
          value={ option.value }
        >
          { option.label }
        </option>
      )) }
    </select>
  ),
  SearchInput: ({ value, placeholder, onChange, 'aria-label': label }: { value?: string, placeholder?: string, onChange?: React.ChangeEventHandler<HTMLInputElement>, 'aria-label'?: string }) => (
    <input
      aria-label={ label }
      onChange={ onChange }
      placeholder={ placeholder }
      type="search"
      value={ value }
    />
  ),
  Skeleton: () => <div aria-busy="true" />,
  Space: ({ children }: WithChildren) => <div>{ children }</div>,
  Steps: ({ current, items }: { current: number, items: Array<{ title: string }> }) => (
    <ol>
      { items.map((item, index) => (
        <li
          aria-current={ index === current ? 'step' : undefined }
          key={ item.title }
        >
          { item.title }
        </li>
      )) }
    </ol>
  ),
  Tag: ({ children }: WithChildren) => <mark>{ children }</mark>,
  Text: ({ children }: WithChildren) => <span>{ children }</span>,
  Switch: ({ checked, labelRight, onChange }: { checked?: boolean, labelRight?: string, onChange?: (checked: boolean) => void }) => (
    <label>
      <input
        checked={ checked }
        onChange={ (event) => { onChange?.(event.target.checked) } }
        type="checkbox"
      />
      { labelRight }
    </label>
  )
}

export const formatDataUnit = (bytes: number, precision: number): string => {
  if (bytes === 0) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  const power = Math.min(Math.floor(Math.log(bytes) / Math.log(1000)), units.length - 1)

  return `${(bytes / Math.pow(1000, power)).toFixed(precision)} ${units[power]}`
}
