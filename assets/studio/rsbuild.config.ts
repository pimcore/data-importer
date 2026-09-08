import { defineConfig } from '@rsbuild/core'
import { pluginReact } from '@rsbuild/plugin-react'
import { pluginModuleFederation } from '@module-federation/rsbuild-plugin';
import { pluginGenerateEntrypoints, pluginWriteBuildId } from '@pimcore/studio-ui-bundle/rsbuild/plugins';
import { createDynamicRemote } from '@pimcore/studio-ui-bundle/rsbuild/utils';
import path from 'node:path'
import fs from 'node:fs';
import { getBuildGroupId } from '@pimcore/studio-ui-bundle/bundler/build-id';
import packages from './package.json'

const buildId = getBuildGroupId(__dirname);
const buildPath = path.resolve(__dirname, '..', '..', 'src', 'Resources', 'public', 'studio', 'build', buildId);

if (!fs.existsSync(buildPath)) {
  fs.mkdirSync(buildPath, { recursive: true });
}

let nodeEnv = process.env.NODE_ENV;
let env: 'development' | 'production' = 'production';

const isDevServer = nodeEnv === 'dev-server';
if (nodeEnv !== env) {
  env = 'development';
}

export default defineConfig({
  mode: env,
  server: {
    port: 3038,
  },
  dev: {
    ...(isDevServer ? {} : {assetPrefix: '/bundles/pimcoredataimporter/studio/build/' + buildId}),
    client: {
      host: 'localhost',
      port: 3038,
      protocol: 'ws'
    }
  },
  source: {
    entry: {
      main: './js/src/main.ts'
    },
    decorators: {
      version: 'legacy'
    }
  },
  output: {
    manifest: true,
    assetPrefix: '/bundles/pimcoredataimporter/studio/build/' + buildId,
    distPath: {
      root: buildPath
    },
  },
  tools: {
    bundlerChain: (chain, { env }) => {
      chain.output.uniqueName('pimcore_dataimporter_bundle');
    },
  },
  plugins: [
    pluginWriteBuildId({ buildId }),
    pluginGenerateEntrypoints(),
    pluginReact(),
    pluginModuleFederation({
      name: 'pimcore_dataimporter_bundle',
      filename: 'static/js/remoteEntry.js',
      exposes: {
        '.': './js/src/plugins.ts',
      },
      dts: false,
      remotes: {
        '@pimcore/studio-ui-bundle': createDynamicRemote('pimcore_studio_ui_bundle'),
        '@pimcore/data-hub': createDynamicRemote('pimcore_datahub_bundle'),
      },
      shared: {
        ...packages.dependencies,
        react: {
          singleton: true,
          eager: true,
          requiredVersion: false,
        },
        'react-dom': {
          singleton: true,
          eager: true,
          requiredVersion: false,
        },
        'inversify': {
          // singleton: true,
          eager: true,
          version: '6.1.x',
          requiredVersion: '6.1.x',
        },
      },
    })
  ]
})
