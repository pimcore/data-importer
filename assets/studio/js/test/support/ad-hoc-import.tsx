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
import { PickerGroupView, type PickerGroup, type PickerItem } from './ad-hoc-import-picker'

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
  Button: ({ children, disabled, onClick, href, 'aria-label': label }: WithChildren & { disabled?: boolean, onClick?: () => void, href?: string, 'aria-label'?: string }) => href !== undefined ? <a href={ href }>{ children }</a> : (
    <button
      aria-label={ label }
      disabled={ disabled }
      onClick={ onClick }
      type="button"
    >
      { children }
    </button>
  ),
  Card: ({ children, contentPadding, footer, ...props }: WithChildren & React.HTMLAttributes<HTMLDivElement> & { contentPadding?: unknown, footer?: React.ReactNode }) => (
    <div
      data-content-padding={ JSON.stringify(contentPadding) }
      data-testid="studio-card"
      { ...props }
    >
      { children }
      { footer }
    </div>
  ),
  ColumnPicker: ({ groups, onSelect, emptyText }: { groups: PickerGroup[], onSelect: (item: PickerItem) => void, emptyText?: string }) => (
    <div role="tree">
      { groups.length === 0 && <span>{ emptyText }</span> }
      { groups.map((group) => (
        <PickerGroupView
          group={ group }
          key={ group.key }
          onSelect={ onSelect }
        />
      )) }
    </div>
  ),
  Flex: ({ children, role, onClick, onFocus, 'aria-label': label, 'aria-labelledby': labelledBy, 'aria-current': current }: WithChildren & { role?: string, onClick?: () => void, onFocus?: () => void, 'aria-label'?: string, 'aria-labelledby'?: string, 'aria-current'?: 'true' }) => (
    <div
      aria-current={ current }
      aria-label={ label }
      aria-labelledby={ labelledBy }
      onClick={ onClick }
      onFocus={ onFocus }
      onKeyDown={ onClick }
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
  Grid: ({ columns, data, isLoading, setRowId }: {
    columns: Array<{
      id: string
      header: string
      accessorFn: (row: unknown) => string
      cell?: (context: { getValue: () => string, row: { original: unknown } }) => React.ReactNode
    }>
    data: unknown[]
    isLoading?: boolean
    setRowId?: (row: unknown) => string
  }) => (
    <table aria-busy={ isLoading }>
      <thead>
        <tr>{ columns.map((column) => <th key={ column.id }>{ column.header }</th>) }</tr>
      </thead>
      <tbody>
        { data.map((row) => (
          <tr key={ setRowId === undefined ? JSON.stringify(row) : setRowId(row) }>
            { columns.map((column) => (
              <td key={ column.id }>
                { column.cell === undefined
                  ? column.accessorFn(row)
                  : column.cell({ getValue: () => column.accessorFn(row), row: { original: row } }) }
              </td>
            )) }
          </tr>
        )) }
      </tbody>
    </table>
  ),
  Icon: () => null,
  Spin: () => <span role="status" />,
  IconButton: ({ onClick, 'aria-label': label }: { onClick?: () => void, 'aria-label'?: string }) => (
    <button
      aria-label={ label }
      onClick={ onClick }
      type="button"
    />
  ),
  LanguageSelection: ({ languages, selectedLanguage, onSelectLanguage }: { languages: string[], selectedLanguage: string, onSelectLanguage: (language: string) => void }) => (
    <select
      aria-label="Language"
      onChange={ (event) => { onSelectLanguage(event.target.value) } }
      value={ selectedLanguage }
    >
      { languages.map((language) => (
        <option
          key={ language }
          value={ language }
        >
          { language }
        </option>
      )) }
    </select>
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
  Pagination: ({ current = 1, total = 0, defaultPageSize = 10, showTotal, onChange }: { current?: number, total?: number, defaultPageSize?: number, showTotal?: (total: number) => string, onChange?: (page: number, pageSize: number) => void }) => (
    <nav aria-label="pages">
      <span>{ showTotal?.(total) }</span>
      <span>{ `${current} / ${Math.max(1, Math.ceil(total / defaultPageSize))}` }</span>
      <button
        aria-label="next page"
        onClick={ () => { onChange?.(current + 1, defaultPageSize) } }
        type="button"
      />
    </nav>
  ),
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
  Text: ({ children, id }: WithChildren & { id?: string }) => <span id={ id }>{ children }</span>,
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
