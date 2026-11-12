/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { type Config } from 'jest'

const transform: Config['transform'] = {
  '^.+\\.(t|j)sx?$': ['@swc/jest', {
    jsc: {
      parser: { syntax: 'typescript', tsx: true, decorators: true },
      transform: { react: { runtime: 'automatic' } }
    }
  }]
}

// Studio and Data Hub are federated remotes at runtime; tests mock what they use from them, one stub per entry
const moduleNameMapper: Config['moduleNameMapper'] = {
  '^@pimcore/studio-ui-bundle$': '<rootDir>/js/test/__mocks__/studio-ui-bundle/index',
  '^@pimcore/studio-ui-bundle/(.+)$': '<rootDir>/js/test/__mocks__/studio-ui-bundle/$1',
  '^@pimcore/data-hub$': '<rootDir>/js/test/__mocks__/data-hub/index'
}

const config: Config = {
  collectCoverageFrom: ['js/src/**/*.{ts,tsx}', '!js/src/**/*.test.{ts,tsx}'],
  projects: [
    { displayName: 'node', testEnvironment: 'node', testMatch: ['<rootDir>/js/**/*.test.ts'], transform, moduleNameMapper },
    // components render into a DOM
    {
      displayName: 'dom',
      testEnvironment: '<rootDir>/js/test/support/dom-environment.ts',
      testMatch: ['<rootDir>/js/**/*.test.tsx'],
      transform,
      moduleNameMapper
    }
  ]
}

export default config
