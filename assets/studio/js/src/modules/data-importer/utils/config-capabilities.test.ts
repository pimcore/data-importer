/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { resolveConfigCapabilities } from './config-capabilities'

describe('resolveConfigCapabilities', () => {
  it('allows running imports for a read-only configuration when the user may update it', () => {
    // A configuration stored in symfony-config is reported as not writeable unless
    // the instance runs in debug mode. Executing an import must not depend on that.
    const capabilities = resolveConfigCapabilities(
      { update: true, delete: true },
      { writeable: false }
    )

    expect(capabilities.canRunImport).toBe(true)
  })

  it('does not allow saving or deleting a configuration that is not writeable', () => {
    const capabilities = resolveConfigCapabilities(
      { update: true, delete: true },
      { writeable: false }
    )

    expect(capabilities.canSaveConfig).toBe(false)
    expect(capabilities.canDeleteConfig).toBe(false)
  })

  it('does not allow running imports without the update permission', () => {
    const capabilities = resolveConfigCapabilities(
      { update: false, delete: false },
      { writeable: true }
    )

    expect(capabilities.canRunImport).toBe(false)
  })

  it('grants every capability for a writeable configuration the user may update and delete', () => {
    const capabilities = resolveConfigCapabilities(
      { update: true, delete: true },
      { writeable: true }
    )

    expect(capabilities).toMatchObject({
      canSaveConfig: true,
      canDeleteConfig: true,
      canRunImport: true
    })
  })

  it('denies every capability when the permissions are unknown', () => {
    const capabilities = resolveConfigCapabilities(undefined, undefined)

    expect(capabilities).toMatchObject({
      canSaveConfig: false,
      canDeleteConfig: false,
      canRunImport: false
    })
  })

  it('reports the missing update permission instead of the read-only storage as save blocker', () => {
    const capabilities = resolveConfigCapabilities({ update: false }, { writeable: true })

    expect(capabilities.saveDisabledTooltipKey).toBe('data-hub.config.no-update-permission')
  })

  it('reports the read-only storage as save blocker when the configuration cannot be written', () => {
    const capabilities = resolveConfigCapabilities({ update: true }, { writeable: false })

    expect(capabilities.saveDisabledTooltipKey).toBe('config_not_writeable')
  })
})
