/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { container, type AbstractModule } from '@pimcore/studio-ui-bundle'
import { type DynamicTypeDataHubAdapterRegistry, bundleServiceIds as dataHubServiceIds } from '@pimcore/data-hub'
import { bundleServiceIds } from '../../config/service-ids'
import {
  DynamicTypeTransformerRegistry,
  DynamicTypeTransformerTrim,
  DynamicTypeTransformerCombine,
  DynamicTypeTransformerStaticText,
  DynamicTypeTransformerStringReplace,
  DynamicTypeTransformerDate,
  DynamicTypeTransformerNumeric,
  DynamicTypeTransformerExplode,
  DynamicTypeTransformerConditionalConversion,
  DynamicTypeTransformerObjectField,
  DynamicTypeTransformerLoadAsset,
  DynamicTypeTransformerFlattenArray,
  DynamicTypeTransformerReduceArrayKeyValuePairs,
  DynamicTypeTransformerHtmlDecode,
  DynamicTypeTransformerBoolean,
  DynamicTypeTransformerAsArray,
  DynamicTypeTransformerAsColor,
  DynamicTypeTransformerAsCountries,
  DynamicTypeTransformerGallery,
  DynamicTypeTransformerImageAdvanced,
  DynamicTypeTransformerQuantityValue,
  DynamicTypeTransformerQuantityValueArray,
  DynamicTypeTransformerInputQuantityValue,
  DynamicTypeTransformerInputQuantityValueArray,
  DynamicTypeTransformerAsGeobounds,
  DynamicTypeTransformerAsGeopoint,
  DynamicTypeTransformerAsGeopolygon,
  DynamicTypeTransformerAsGeopolyline,
  DynamicTypeTransformerLoadDataObject,
  DynamicTypeTransformerImportAsset
} from './dynamic-types/transformer'
import { DynamicTypeInterpreterRegistry } from "./dynamic-types/interpreter/dynamic-type-interpreter-registry";
import { DynamicTypeInterpreterCsv } from "./dynamic-types/interpreter/csv/dynamic-type-interpreter-csv";
import { DynamicTypeInterpreterJson } from "./dynamic-types/interpreter/json/dynamic-type-interpreter-json";
import { DynamicTypeInterpreterSql } from "./dynamic-types/interpreter/sql/dynamic-type-interpreter-sql";
import { DynamicTypeInterpreterXml } from "./dynamic-types/interpreter/xml/dynamic-type-interpreter-xml";
import { DynamicTypeInterpreterXlsx } from "./dynamic-types/interpreter/xlsx/dynamic-type-interpreter-xlsx";

export const DataImporterModule: AbstractModule = {
  onInit: (): void => {
    // ── Data Hub adapter ────────────────────────────────────────────────────
    const adapterRegistry = container.get<DynamicTypeDataHubAdapterRegistry>(dataHubServiceIds['DataHub/DynamicTypes/Adapter/Registry'])
    adapterRegistry.registerDynamicType(container.get(bundleServiceIds['DataImporter/DynamicTypes/Adapter/DataImporterDataObject']))

    // ── Interpreter registry ────────────────────────────────────────────────
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Registry']).to(DynamicTypeInterpreterRegistry).inSingletonScope()

    // Bind types
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Csv']).to(DynamicTypeInterpreterCsv).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Json']).to(DynamicTypeInterpreterJson).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Sql']).to(DynamicTypeInterpreterSql).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Xlsx']).to(DynamicTypeInterpreterXlsx).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Xml']).to(DynamicTypeInterpreterXml).inSingletonScope()

    // Register types to registry
    const interpreterRegistry = container.get<DynamicTypeInterpreterRegistry>(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Registry'])
    interpreterRegistry.registerDynamicType(container.get(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Csv']))
    interpreterRegistry.registerDynamicType(container.get(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Json']))
    interpreterRegistry.registerDynamicType(container.get(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Sql']))
    interpreterRegistry.registerDynamicType(container.get(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Xlsx']))
    interpreterRegistry.registerDynamicType(container.get(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Xml']))

    // ── Transformer registry ────────────────────────────────────────────────
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Registry']).to(DynamicTypeTransformerRegistry).inSingletonScope()

    // Bind types with settings
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Trim']).to(DynamicTypeTransformerTrim).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Combine']).to(DynamicTypeTransformerCombine).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/StaticText']).to(DynamicTypeTransformerStaticText).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/StringReplace']).to(DynamicTypeTransformerStringReplace).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Date']).to(DynamicTypeTransformerDate).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Numeric']).to(DynamicTypeTransformerNumeric).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Explode']).to(DynamicTypeTransformerExplode).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/ConditionalConversion']).to(DynamicTypeTransformerConditionalConversion).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/ObjectField']).to(DynamicTypeTransformerObjectField).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/LoadAsset']).to(DynamicTypeTransformerLoadAsset).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/LoadDataObject']).to(DynamicTypeTransformerLoadDataObject).inSingletonScope()

    // Bind types without settings
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/FlattenArray']).to(DynamicTypeTransformerFlattenArray).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/ReduceArrayKeyValuePairs']).to(DynamicTypeTransformerReduceArrayKeyValuePairs).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/HtmlDecode']).to(DynamicTypeTransformerHtmlDecode).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Boolean']).to(DynamicTypeTransformerBoolean).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsArray']).to(DynamicTypeTransformerAsArray).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsColor']).to(DynamicTypeTransformerAsColor).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsCountries']).to(DynamicTypeTransformerAsCountries).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Gallery']).to(DynamicTypeTransformerGallery).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/ImageAdvanced']).to(DynamicTypeTransformerImageAdvanced).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/QuantityValue']).to(DynamicTypeTransformerQuantityValue).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/QuantityValueArray']).to(DynamicTypeTransformerQuantityValueArray).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/InputQuantityValue']).to(DynamicTypeTransformerInputQuantityValue).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/InputQuantityValueArray']).to(DynamicTypeTransformerInputQuantityValueArray).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsGeobounds']).to(DynamicTypeTransformerAsGeobounds).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsGeopoint']).to(DynamicTypeTransformerAsGeopoint).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsGeopolygon']).to(DynamicTypeTransformerAsGeopolygon).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsGeopolyline']).to(DynamicTypeTransformerAsGeopolyline).inSingletonScope()
    container.bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/ImportAsset']).to(DynamicTypeTransformerImportAsset).inSingletonScope()

    // Register all types into the registry
    const transformerRegistry = container.get<DynamicTypeTransformerRegistry>(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Registry'])

    const allTransformerServiceIds = [
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/Trim'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/Combine'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/StaticText'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/StringReplace'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/Date'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/Numeric'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/Explode'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/ConditionalConversion'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/ObjectField'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/LoadAsset'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/FlattenArray'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/ReduceArrayKeyValuePairs'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/HtmlDecode'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/Boolean'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsArray'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsColor'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsCountries'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/Gallery'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/ImageAdvanced'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/QuantityValue'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/QuantityValueArray'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/InputQuantityValue'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/InputQuantityValueArray'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsGeobounds'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsGeopoint'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsGeopolygon'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsGeopolyline'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/LoadDataObject'],
      bundleServiceIds['DataImporter/DynamicTypes/Transformer/ImportAsset']
    ] as const

    for (const serviceId of allTransformerServiceIds) {
      transformerRegistry.registerDynamicType(container.get(serviceId))
    }
  }
}
