/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import type { BackendConfiguration } from '../utils/transformers'
import { humanize, translated } from './field-labels'

type Translate = (key: string, options?: Record<string, unknown>) => string

const T = 'data-importer.review.outline'

export interface BriefStop {
  /** a Studio icon name */
  readonly icon: string
  readonly label: string
  readonly note?: string
}

export interface BriefTag {
  readonly key: string
  readonly label: string
  /** an antd tag colour; none reads as neutral */
  readonly colour?: string
}

/** a new configuration in one glance: where the data comes from, what it becomes, how it runs */
export interface ConfigBrief {
  readonly source: BriefStop
  readonly target: BriefStop
  readonly tags: BriefTag[]
}

/** the loader's own icon in Studio's set, so the source reads at a glance */
const LOADER_ICON: Record<string, string> = {
  asset: 'asset',
  upload: 'upload',
  http: 'world',
  sftp: 'cloud',
  push: 'webhook',
  sql: 'table'
}

/** an adapter's or strategy's name as the editor shows it in its select, else its key spelt out */
const named = (t: Translate, prefix: string, type: string | undefined): string | undefined =>
  type === undefined || type === '' ? undefined : translated(t, `${prefix}.${type}`) ?? humanize(type)

const present = (parts: Array<string | undefined>): string[] =>
  parts.filter((part): part is string => part !== undefined && part !== '')

export function configBrief (configuration: BackendConfiguration, t: Translate): ConfigBrief {
  const loaderType = configuration.loaderConfig?.type
  const source: BriefStop = {
    icon: LOADER_ICON[loaderType ?? ''] ?? 'import',
    label: present([
      named(t, 'data-importer.loader', loaderType),
      named(t, 'data-importer.interpreter', configuration.interpreterConfig?.type)
    ]).join(' · ')
  }

  const rows = configuration.mappingConfig?.length ?? 0
  const target: BriefStop = {
    icon: 'data-object',
    label: configuration.resolverConfig?.dataObjectClassId ?? '',
    note: rows === 0 ? undefined : t(`${T}.mappings`, { count: rows })
  }

  const active = configuration.general?.active === true
  const execution = named(t, 'data-importer.processing.execution-type', configuration.processingConfig?.executionType)
  const schedule = configuration.executionConfig
  const scheduled = (schedule?.cronDefinition ?? '') !== '' ||
    (schedule?.scheduleType === 'job' && (schedule.scheduledAt ?? '') !== '')
  const tags: BriefTag[] = [
    { key: 'state', label: t(`${T}.${active ? 'active' : 'inactive'}`), colour: active ? 'green' : undefined },
    ...(execution === undefined ? [] : [{ key: 'execution', label: execution }]),
    {
      key: 'schedule',
      label: scheduled
        ? t(`${T}.scheduled`)
        : translated(t, 'data-importer.execution.manual-execution') ?? humanize('manualExecution')
    }
  ]

  return { source, target, tags }
}
