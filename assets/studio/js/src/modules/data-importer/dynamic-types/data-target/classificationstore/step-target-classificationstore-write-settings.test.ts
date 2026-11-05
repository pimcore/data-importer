/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type ReactElement, isValidElement } from 'react'
import { StepTargetClassificationstoreWriteSettings } from './step-target-classificationstore-write-settings'
import { DataTargetClassificationstoreSettings } from './data-target-classificationstore-settings'
import { DataTargetClassificationstoreBatchSettings } from './data-target-classificationstore-batch-settings'
import {
  WRITE_IF_SOURCE_IS_EMPTY_CONFIGURED,
  type ClassificationStoreTargetId
} from './classification-store-write-settings'
import { type DataTargetConfig } from '../../../types'

const SWITCH_LABEL_KEY = 'data-importer.mapping.advanced-modal.write-if-source-empty'
const SWITCH_TOOLTIP_KEY = 'data-importer.mapping.advanced-modal.step-target.write-if-source-empty.tooltip'

// The studio UI bundle is a module federation remote that rsbuild aliases at build time and jest
// cannot resolve, so the two components this tree takes from it stand in as named element types.
jest.mock('@pimcore/studio-ui-bundle/components', () => ({ Switch: 'Switch', Select: 'Select' }), { virtual: true })

jest.mock('@pimcore/studio-ui-bundle/app', () => ({
  useTranslation: () => ({ t: (key: string) => key })
}), { virtual: true })

jest.mock('../../../components/tabs/steps/advanced-mapping-modal/step-target/step-target.styles', () => ({
  useStyles: () => ({ styles: {} })
}))

jest.mock('./use-classification-store-settings', () => ({
  useClassificationStoreSettings: () => ({ attributes: [], options: [], isFetching: false, isLocalized: false })
}))

// the panels' other controls are not under test; mocking them keeps their own studio imports out
jest.mock('../common/step-target-attribute-select', () => ({ StepTargetAttributeSelect: 'AttributeSelect' }))

jest.mock('../common/step-target-attribute-language-select', () => ({
  StepTargetAttributeLanguageSelect: 'LanguageSelect'
}))

jest.mock('./step-target-classificationstore-key-search', () => ({
  StepTargetClassificationstoreKeySearch: 'KeySearch'
}))

/** Every element of a rendered tree, through children and through element-valued props alike. */
function elements (node: unknown): ReactElement[] {
  if (Array.isArray(node)) {
    return node.reduce<ReactElement[]>((found, child) => found.concat(elements(child)), [])
  }

  if (!isValidElement(node)) {
    return []
  }

  return Object.values(node.props as Record<string, unknown>)
    .reduce<ReactElement[]>((found, value) => found.concat(elements(value)), [node])
}

/** Every translation key the rendered tree asks for, label and tooltip alike. */
function translationKeys (node: unknown): string {
  const strings = (value: unknown): string[] => {
    if (typeof value === 'string') {
      return [value]
    }

    if (Array.isArray(value)) {
      return value.reduce<string[]>((found, child) => found.concat(strings(child)), [])
    }

    if (!isValidElement(value)) {
      return []
    }

    return Object.values(value.props as Record<string, unknown>)
      .reduce<string[]>((found, child) => found.concat(strings(child)), [])
  }

  return strings(node)
    .filter((value) => value.startsWith('data-importer.'))
    .join(' ')
}

function theSwitch (node: unknown): Record<string, unknown> {
  const switches = elements(node).filter((element) => element.type === 'Switch')

  expect(switches).toHaveLength(1)

  return switches[0].props as Record<string, unknown>
}

function control (
  settings: DataTargetConfig['settings'],
  target: ClassificationStoreTargetId,
  onChange: (settings: DataTargetConfig['settings']) => void = () => {}
): unknown {
  return StepTargetClassificationstoreWriteSettings({ settings, target, onChange })
}

describe('StepTargetClassificationstoreWriteSettings', () => {
  it('renders the shipped Write If Source Is Empty label and tooltip', () => {
    const rendered = control({ fieldName: 'csField' }, 'classificationstore')

    expect(translationKeys(rendered)).toContain(SWITCH_LABEL_KEY)
    expect(theSwitch(rendered).tooltip).toBe(SWITCH_TOOLTIP_KEY)
  })

  it('offers neither of Direct\'s other two write options', () => {
    const keys = translationKeys(control({ fieldName: 'csField' }, 'classificationstore'))

    expect(keys).not.toContain('write-if-target-not-empty')
    expect(keys).not.toContain('overwrite-mode')
    expect(elements(control({}, 'classificationstore')).filter((e) => e.type === 'Select')).toHaveLength(0)
  })

  it('shows the effective legacy value of its target while the marker is absent', () => {
    // the stored value is not in force without the marker, so showing it would state the
    // opposite of what the import does on a batch entry that is dropping empty values
    const stored = { fieldName: 'csField', writeIfSourceIsEmpty: true }

    expect(theSwitch(control(stored, 'classificationstoreBatch')).checked).toBe(false)
    expect(theSwitch(control(stored, 'classificationstore')).checked).toBe(true)
  })

  it('shows the stored value once the marker is present', () => {
    const marked = { [WRITE_IF_SOURCE_IS_EMPTY_CONFIGURED]: true, writeIfSourceIsEmpty: false }

    expect(theSwitch(control(marked, 'classificationstore')).checked).toBe(false)
  })

  it('emits the marker alongside the new value when the switch is changed', () => {
    const onChange = jest.fn()
    const onSwitchChange = theSwitch(
      control({ fieldName: 'csField' }, 'classificationstoreBatch', onChange)
    ).onChange as (checked: boolean) => void

    onSwitchChange(true)

    expect(onChange).toHaveBeenCalledWith({
      fieldName: 'csField',
      [WRITE_IF_SOURCE_IS_EMPTY_CONFIGURED]: true,
      writeIfSourceIsEmpty: true
    })
  })
})

describe.each([
  ['DataTargetClassificationstoreSettings', DataTargetClassificationstoreSettings, 'classificationstore', true],
  [
    'DataTargetClassificationstoreBatchSettings',
    DataTargetClassificationstoreBatchSettings,
    'classificationstoreBatch',
    false
  ]
] as const)('%s', (_name, Panel, target: ClassificationStoreTargetId, legacyChecked) => {
  /** The panel as the advanced mapping modal renders it, one entry carrying no marker. */
  const settings = { fieldName: 'csField', writeIfSourceIsEmpty: true }
  const panel = (): unknown =>
    Panel({ classFieldOptions: [], isLocalized: false, settings, onChange: () => {} })

  const mountedControl = (): { target: ClassificationStoreTargetId, settings: DataTargetConfig['settings'] } => {
    const mounted = elements(panel()).filter((e) => e.type === StepTargetClassificationstoreWriteSettings)

    expect(mounted).toHaveLength(1)

    return mounted[0].props as { target: ClassificationStoreTargetId, settings: DataTargetConfig['settings'] }
  }

  it('mounts the Write If Source Is Empty control for its own target', () => {
    expect(mountedControl().target).toBe(target)
    expect(mountedControl().settings).toBe(settings)
  })

  it('shows the control in the state this target actually imports in', () => {
    // rendered from what the panel hands the control, so that a panel passing the other target's
    // id fails here as well as on the assertion above
    const mounted = mountedControl()

    expect(theSwitch(control(mounted.settings, mounted.target)).checked).toBe(legacyChecked)
  })
})
