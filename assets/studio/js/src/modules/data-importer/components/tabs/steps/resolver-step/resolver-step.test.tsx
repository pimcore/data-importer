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
import { Form, type FormInstance } from 'antd'
import { cleanup, render, renderHook, screen } from '@testing-library/react'
import { type DynamicTypeResolverRenderProps } from '../../../../dynamic-types/resolver/common/dynamic-type-resolver-abstract'
import { IdLoadingStrategyResolverSettings } from '../../../../dynamic-types/resolver/loading-strategy/id/id-loading-strategy-resolver-settings'
import { PathLoadingStrategyResolverSettings } from '../../../../dynamic-types/resolver/loading-strategy/path/path-loading-strategy-resolver-settings'
import { AttributeLoadingStrategyResolverSettings } from '../../../../dynamic-types/resolver/loading-strategy/attribute/attribute-loading-strategy-resolver-settings'
import { ResolverStep } from '.'

// the stand-ins render native fields named by their Form.Item label, so a test sees what is disabled;
// like antd's own fields, an explicit disabled prop wins over a disabled Form
jest.mock('@pimcore/studio-ui-bundle/components', () => {
  const antd = jest.requireActual('antd')
  const DisabledContext: React.Context<boolean> = jest.requireActual('antd/lib/config-provider/DisabledContext').default
  const LabelContext = React.createContext<string | undefined>(undefined)
  const Item = ({ label, ...props }: { label?: string }): React.JSX.Element => (
    <LabelContext.Provider value={ label }>
      <antd.Form.Item { ...props } />
    </LabelContext.Provider>
  )
  const Conditional = ({ children, condition }: { children?: React.ReactNode, condition: (values: object) => boolean }): React.JSX.Element => (
    <antd.Form.Item
      noStyle
      shouldUpdate
    >
      { (form: { getFieldsValue: (all: boolean) => object }) => condition(form.getFieldsValue(true)) ? children : null }
    </antd.Form.Item>
  )
  const Form = Object.assign((props: object) => <antd.Form { ...props } />, antd.Form, { Item, Conditional })
  const Children = ({ children }: { children?: React.ReactNode }): React.JSX.Element => <div>{ children }</div>

  return {
    Form,
    Space: Children,
    FormKit: { Panel: ({ children, title }: { children?: React.ReactNode, title?: string }) => <section aria-label={ title }>{ children }</section> },
    Select: ({ disabled }: { disabled?: boolean }) => (
      <select
        aria-label={ React.useContext(LabelContext) }
        disabled={ disabled ?? React.useContext(DisabledContext) }
      />
    ),
    Switch: ({ disabled, labelRight }: { disabled?: boolean, labelRight?: string }) => (
      <input
        aria-label={ labelRight }
        disabled={ disabled ?? React.useContext(DisabledContext) }
        type="checkbox"
      />
    )
  }
})
jest.mock('@pimcore/studio-ui-bundle/app', () => ({ useTranslation: () => ({ t: (key: string) => key }) }))
jest.mock('@pimcore/studio-ui-bundle/modules/app', () => ({ useSettings: () => ({ validLanguages: ['en', 'de'] }) }))
jest.mock('@pimcore/studio-ui-bundle/modules/element', () => ({
  FieldWidthProvider: ({ children }: { children: React.ReactNode }) => <>{ children }</>,
  useFieldWidth: () => ({ medium: 600 })
}))
jest.mock('@pimcore/studio-ui-bundle/api/class-definition', () => ({
  useClassDefinitionCollectionQuery: () => ({ data: { items: [{ id: 'Product', name: 'Product' }] }, isLoading: false })
}))
jest.mock('../../../../data-importer-api-slice.gen', () => ({
  useBundleDataImporterDataTypeLoadClassAttributesQuery: () => ({
    data: { attributes: [{ key: 'name', title: 'Name', localized: true }] },
    isLoading: false
  })
}))
jest.mock('../field-width-limiter/field-width-limiter.styles', () => ({ useStyles: () => ({ styles: {} }) }))
jest.mock('../step-heading/step-heading.styles', () => ({ useStyles: () => ({ styles: {} }) }))

// the importer's loading strategies, without the container they are registered in
const loadingStrategies = [
  { id: 'loading.id', type: 'id', label: 'id', Settings: IdLoadingStrategyResolverSettings },
  { id: 'loading.path', type: 'path', label: 'path', Settings: PathLoadingStrategyResolverSettings },
  { id: 'loading.attribute', type: 'attribute', label: 'attribute', Settings: AttributeLoadingStrategyResolverSettings }
].map(({ Settings, ...strategy }) => ({
  ...strategy,
  renderSettings: (props: DynamicTypeResolverRenderProps) => <Settings { ...props } />
}))
jest.mock('@pimcore/studio-ui-bundle', () => ({
  container: {
    get: () => ({ getDynamicTypesForGroup: (group: string) => group === 'loading' ? loadingStrategies : [] })
  }
}))

const loadingFields = [
  'data-importer.resolver.loading-strategy',
  'data-importer.resolver.loading-strategy.data-source-index'
]
const attributeFields = [
  ...loadingFields,
  'data-importer.resolver.loading-strategy.attribute-name',
  'data-importer.resolver.loading-strategy.language',
  'data-importer.resolver.loading-strategy.include-unpublished'
]

const loadingSettings = { dataSourceIndex: 'sku', attributeName: 'name' }

const Host = ({ loadingStrategy, locked, form, formDisabled }: { loadingStrategy: string, locked?: boolean, form?: FormInstance, formDisabled?: boolean }): React.JSX.Element => (
  <Form
    disabled={ formDisabled }
    form={ form }
    initialValues={ {
      resolverConfig: {
        dataObjectClassId: 'Product',
        loadingStrategy: { type: loadingStrategy, settings: loadingSettings }
      }
    } }
  >
    <ResolverStep
      columnHeaderOptions={ [{ value: 'sku', label: 'sku' }] }
      isActive
      isElementLoadingLocked={ locked }
    />
  </Form>
)

const field = (name: string): HTMLInputElement | HTMLSelectElement => screen.getByLabelText<HTMLInputElement | HTMLSelectElement>(name, { exact: true })

afterEach(cleanup)

describe('ResolverStep', () => {
  it.each(['id', 'path'])('disables the loading strategy and its column with the lock (%s)', async (strategy) => {
    render(<Host
      loadingStrategy={ strategy }
      locked
           />)

    expect(await screen.findByLabelText(loadingFields[1])).toBeTruthy()
    expect(loadingFields.map(name => field(name).disabled)).toEqual([true, true])
  })

  it('disables every field of the attribute loading strategy with the lock', async () => {
    render(<Host
      loadingStrategy="attribute"
      locked
           />)

    expect(await screen.findByLabelText(attributeFields[3])).toBeTruthy()
    expect(attributeFields.map(name => field(name).disabled)).toEqual([true, true, true, true, true])
  })

  it.each(['id', 'path'])('keeps the loading strategy and its column editable without the lock (%s)', async (strategy) => {
    render(<Host loadingStrategy={ strategy } />)

    expect(await screen.findByLabelText(loadingFields[1])).toBeTruthy()
    expect(loadingFields.map(name => field(name).disabled)).toEqual([false, false])
  })

  it('keeps every field of the attribute loading strategy editable without the lock', async () => {
    render(<Host loadingStrategy="attribute" />)

    expect(await screen.findByLabelText(attributeFields[3])).toBeTruthy()
    expect(attributeFields.map(name => field(name).disabled)).toEqual([false, false, false, false, false])
  })

  it.each(['id', 'path', 'attribute'])('keeps the loading fields read-only in a read-only form without the lock (%s)', async (strategy) => {
    render(<Host
      formDisabled
      loadingStrategy={ strategy }
           />)

    const fields = strategy === 'attribute' ? attributeFields : loadingFields
    expect(await screen.findByLabelText(loadingFields[1])).toBeTruthy()
    expect(fields.map(name => field(name).disabled)).toEqual(fields.map(() => true))
  })

  it('keeps the stored loading strategy and its settings in the form values under the lock', async () => {
    const { result } = renderHook(() => Form.useForm()[0])
    render(<Host
      form={ result.current }
      loadingStrategy="attribute"
      locked
           />)

    expect(await screen.findByLabelText(attributeFields[3])).toBeTruthy()
    expect(result.current.getFieldsValue(true).resolverConfig.loadingStrategy).toEqual({ type: 'attribute', settings: loadingSettings })
  })

  it('leaves the class editable under the lock', async () => {
    render(<Host
      loadingStrategy="id"
      locked
           />)

    expect((await screen.findByLabelText<HTMLSelectElement>('data-importer.resolver.class')).disabled).toBe(false)
  })
})
