/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

export interface ConfigUserPermissions {
  update?: boolean
  delete?: boolean
}

export interface ConfigGeneralSettings {
  writeable?: boolean
}

export interface ConfigCapabilities {
  /** The configuration may be edited and persisted. */
  canSaveConfig: boolean
  /** The configuration may be removed. */
  canDeleteConfig: boolean
  /** Import executions may be triggered: uploading a file, starting and cancelling a run. */
  canRunImport: boolean
  /** Translation key explaining why saving is unavailable. */
  saveDisabledTooltipKey: string
}

/**
 * Derives what the current user may do with a configuration.
 *
 * Two independent aspects are combined here and must not be conflated:
 *
 * - the per-configuration permission of the user (`update` / `delete`), and
 * - whether the configuration storage can be written at all (`general.writeable`),
 *   which is `false` for configurations coming from the Symfony configuration
 *   unless the instance runs in debug mode.
 *
 * Editing the configuration needs both. Executing an import only needs the
 * permission, because it does not persist the configuration — this mirrors the
 * authorisation the API endpoints for upload, start and cancel actually apply.
 */
export const resolveConfigCapabilities = (
  userPermissions: ConfigUserPermissions | undefined,
  general: ConfigGeneralSettings | undefined
): ConfigCapabilities => {
  const hasUpdatePermission = userPermissions?.update === true
  const hasDeletePermission = userPermissions?.delete === true
  const isStorageWriteable = general?.writeable !== false

  return {
    canSaveConfig: hasUpdatePermission && isStorageWriteable,
    canDeleteConfig: hasDeletePermission && isStorageWriteable,
    canRunImport: hasUpdatePermission,
    saveDisabledTooltipKey: isStorageWriteable && !hasUpdatePermission
      ? 'data-hub.config.no-update-permission'
      : 'config_not_writeable'
  }
}
