/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

import { container, type AbstractModule } from '@pimcore/studio-ui-bundle';
import { type DynamicTypeDataHubAdapterRegistry, bundleServiceIds as dataHubServiceIds } from '@pimcore/data-hub';
import { bundleServiceIds } from '../../config/service-ids';
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
    DynamicTypeTransformerImportAsset,
} from './dynamic-types/transformer';
import { DynamicTypeInterpreterRegistry } from './dynamic-types/interpreter/dynamic-type-interpreter-registry';
import { DynamicTypeInterpreterCsv } from './dynamic-types/interpreter/csv/dynamic-type-interpreter-csv';
import { DynamicTypeInterpreterJson } from './dynamic-types/interpreter/json/dynamic-type-interpreter-json';
import { DynamicTypeInterpreterSql } from './dynamic-types/interpreter/sql/dynamic-type-interpreter-sql';
import { DynamicTypeInterpreterXml } from './dynamic-types/interpreter/xml/dynamic-type-interpreter-xml';
import { DynamicTypeInterpreterXlsx } from './dynamic-types/interpreter/xlsx/dynamic-type-interpreter-xlsx';
import { DynamicTypeLoaderRegistry } from './dynamic-types/loader/dynamic-type-loader-registry';
import { DynamicTypeLoaderAsset } from './dynamic-types/loader/asset/dynamic-type-loader-asset';
import { DynamicTypeLoaderUpload } from './dynamic-types/loader/upload/dynamic-type-loader-upload';
import { DynamicTypeLoaderHttp } from './dynamic-types/loader/http/dynamic-type-loader-http';
import { DynamicTypeLoaderSftp } from './dynamic-types/loader/sftp/dynamic-type-loader-sftp';
import { DynamicTypeLoaderPush } from './dynamic-types/loader/push/dynamic-type-loader-push';
import { DynamicTypeLoaderSql } from './dynamic-types/loader/sql/dynamic-type-loader-sql';
import { DynamicTypeDataTargetRegistry } from './dynamic-types/data-target/dynamic-type-data-target-registry';
import { DynamicTypeDataTargetDirect } from './dynamic-types/data-target/direct/dynamic-type-data-target-direct';
import { DynamicTypeDataTargetClassificationstore } from './dynamic-types/data-target/classificationstore/dynamic-type-data-target-classificationstore';
import { DynamicTypeDataTargetClassificationstoreBatch } from './dynamic-types/data-target/classificationstore/dynamic-type-data-target-classificationstore-batch';
import { DynamicTypeDataTargetManyToManyRelation } from './dynamic-types/data-target/many-to-many-relation/dynamic-type-data-target-many-to-many-relation';
import { DynamicTypeResolverRegistry } from './dynamic-types/resolver/dynamic-type-resolver-registry';
import { DynamicTypeResolverNotLoad } from './dynamic-types/resolver/loading-strategy/not-load/dynamic-type-resolver-not-load';
import { DynamicTypeResolverId } from './dynamic-types/resolver/loading-strategy/id/dynamic-type-resolver-id';
import { DynamicTypeResolverPath } from './dynamic-types/resolver/loading-strategy/path/dynamic-type-resolver-path';
import { DynamicTypeResolverAttribute } from './dynamic-types/resolver/loading-strategy/attribute/dynamic-type-resolver-attribute';
import { DynamicTypeResolverNoChangeUnpublishNew } from './dynamic-types/resolver/publishing-strategy/no-change-unpublish-new/dynamic-type-resolver-no-change-unpublish-new';
import { DynamicTypeResolverNoChangePublishNew } from './dynamic-types/resolver/publishing-strategy/no-change-publish-new/dynamic-type-resolver-no-change-publish-new';
import { DynamicTypeResolverAlwaysPublish } from './dynamic-types/resolver/publishing-strategy/always-publish/dynamic-type-resolver-always-publish';
import { DynamicTypeResolverAttributeBasedPublishing } from './dynamic-types/resolver/publishing-strategy/attribute-based/dynamic-type-resolver-attribute-based-publishing';
import { DynamicTypeResolverStaticPathLocationCreation } from './dynamic-types/resolver/create-location-strategy/static-path/dynamic-type-resolver-static-path-location-creation';
import { DynamicTypeResolverFindOrCreateFolderLocationCreation } from './dynamic-types/resolver/create-location-strategy/find-or-create-folder/dynamic-type-resolver-find-or-create-folder-location-creation';
import { DynamicTypeResolverFindParentLocationCreation } from './dynamic-types/resolver/create-location-strategy/find-parent/dynamic-type-resolver-find-parent-location-creation';
import { DynamicTypeResolverDoNotCreateLocation } from './dynamic-types/resolver/create-location-strategy/do-not-create/dynamic-type-resolver-do-not-create-location';
import { DynamicTypeResolverNoChangeLocationUpdate } from './dynamic-types/resolver/update-location-strategy/no-change/dynamic-type-resolver-no-change-location-update';
import { DynamicTypeResolverStaticPathLocationUpdate } from './dynamic-types/resolver/update-location-strategy/static-path/dynamic-type-resolver-static-path-location-update';
import { DynamicTypeResolverFindOrCreateFolderLocationUpdate } from './dynamic-types/resolver/update-location-strategy/find-or-create-folder/dynamic-type-resolver-find-or-create-folder-location-update';
import { DynamicTypeResolverFindParentLocationUpdate } from './dynamic-types/resolver/update-location-strategy/find-parent/dynamic-type-resolver-find-parent-location-update';

export const DataImporterModule: AbstractModule = {
    onInit: (): void => {
        // ── Data Hub adapter ────────────────────────────────────────────────────
        const adapterRegistry = container.get<DynamicTypeDataHubAdapterRegistry>(
            dataHubServiceIds['DataHub/DynamicTypes/Adapter/Registry']
        );
        adapterRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Adapter/DataImporterDataObject'])
        );

        // ── Interpreter registry ────────────────────────────────────────────────
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Registry'])
            .to(DynamicTypeInterpreterRegistry)
            .inSingletonScope();

        // Bind types
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Csv'])
            .to(DynamicTypeInterpreterCsv)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Json'])
            .to(DynamicTypeInterpreterJson)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Xml'])
            .to(DynamicTypeInterpreterXml)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Xlsx'])
            .to(DynamicTypeInterpreterXlsx)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Sql'])
            .to(DynamicTypeInterpreterSql)
            .inSingletonScope();

        // Register types to registry — order here determines the dropdown/panel order in the UI
        const interpreterRegistry = container.get<DynamicTypeInterpreterRegistry>(
            bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Registry']
        );
        interpreterRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Csv'])
        );
        interpreterRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Json'])
        );
        interpreterRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Xml'])
        );
        interpreterRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Xlsx'])
        );
        interpreterRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Interpreter/Sql'])
        );

        // ── Loader registry ─────────────────────────────────────────────────────
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Loader/Registry'])
            .to(DynamicTypeLoaderRegistry)
            .inSingletonScope();

        // Bind types
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Loader/Asset'])
            .to(DynamicTypeLoaderAsset)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Loader/Upload'])
            .to(DynamicTypeLoaderUpload)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Loader/Http'])
            .to(DynamicTypeLoaderHttp)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Loader/Sftp'])
            .to(DynamicTypeLoaderSftp)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Loader/Push'])
            .to(DynamicTypeLoaderPush)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Loader/Sql'])
            .to(DynamicTypeLoaderSql)
            .inSingletonScope();

        // Register types to registry
        const loaderRegistry = container.get<DynamicTypeLoaderRegistry>(
            bundleServiceIds['DataImporter/DynamicTypes/Loader/Registry']
        );
        loaderRegistry.registerDynamicType(container.get(bundleServiceIds['DataImporter/DynamicTypes/Loader/Asset']));
        loaderRegistry.registerDynamicType(container.get(bundleServiceIds['DataImporter/DynamicTypes/Loader/Upload']));
        loaderRegistry.registerDynamicType(container.get(bundleServiceIds['DataImporter/DynamicTypes/Loader/Http']));
        loaderRegistry.registerDynamicType(container.get(bundleServiceIds['DataImporter/DynamicTypes/Loader/Sftp']));
        loaderRegistry.registerDynamicType(container.get(bundleServiceIds['DataImporter/DynamicTypes/Loader/Push']));
        loaderRegistry.registerDynamicType(container.get(bundleServiceIds['DataImporter/DynamicTypes/Loader/Sql']));

        // ── Resolver registry ────────────────────────────────────────────────
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Registry'])
            .to(DynamicTypeResolverRegistry)
            .inSingletonScope();

        // Bind loading types
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Loading/NotLoad'])
            .to(DynamicTypeResolverNotLoad)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Loading/Id'])
            .to(DynamicTypeResolverId)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Loading/Path'])
            .to(DynamicTypeResolverPath)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Loading/Attribute'])
            .to(DynamicTypeResolverAttribute)
            .inSingletonScope();

        // Bind create location types
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Creation/StaticPath'])
            .to(DynamicTypeResolverStaticPathLocationCreation)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Creation/FindOrCreateFolder'])
            .to(DynamicTypeResolverFindOrCreateFolderLocationCreation)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Creation/FindParent'])
            .to(DynamicTypeResolverFindParentLocationCreation)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Creation/DoNotCreate'])
            .to(DynamicTypeResolverDoNotCreateLocation)
            .inSingletonScope();

        // Bind update location types
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Update/NoChange'])
            .to(DynamicTypeResolverNoChangeLocationUpdate)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Update/StaticPath'])
            .to(DynamicTypeResolverStaticPathLocationUpdate)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Update/FindOrCreateFolder'])
            .to(DynamicTypeResolverFindOrCreateFolderLocationUpdate)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Update/FindParent'])
            .to(DynamicTypeResolverFindParentLocationUpdate)
            .inSingletonScope();

        // Bind publishing types
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Publishing/NoChangeUnpublishNew'])
            .to(DynamicTypeResolverNoChangeUnpublishNew)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Publishing/NoChangePublishNew'])
            .to(DynamicTypeResolverNoChangePublishNew)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Publishing/AlwaysPublish'])
            .to(DynamicTypeResolverAlwaysPublish)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Publishing/AttributeBased'])
            .to(DynamicTypeResolverAttributeBasedPublishing)
            .inSingletonScope();

        // Register loading types to registry
        const resolverRegistry = container.get<DynamicTypeResolverRegistry>(
            bundleServiceIds['DataImporter/DynamicTypes/Resolver/Registry']
        );
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Loading/NotLoad'])
        );
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Loading/Id'])
        );
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Loading/Path'])
        );
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Loading/Attribute'])
        );

        // Register create location types to registry
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Creation/StaticPath'])
        );
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Creation/FindOrCreateFolder'])
        );
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Creation/FindParent'])
        );
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Creation/DoNotCreate'])
        );

        // Register update location types to registry
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Update/NoChange'])
        );
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Update/StaticPath'])
        );
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Update/FindOrCreateFolder'])
        );
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Location/Update/FindParent'])
        );

        // Register publishing types to registry
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Publishing/NoChangeUnpublishNew'])
        );
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Publishing/NoChangePublishNew'])
        );
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Publishing/AlwaysPublish'])
        );
        resolverRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/Resolver/Publishing/AttributeBased'])
        );

        // ── Transformer registry ────────────────────────────────────────────────
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Registry'])
            .to(DynamicTypeTransformerRegistry)
            .inSingletonScope();

        // Bind types with settings
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Trim'])
            .to(DynamicTypeTransformerTrim)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Combine'])
            .to(DynamicTypeTransformerCombine)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/StaticText'])
            .to(DynamicTypeTransformerStaticText)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/StringReplace'])
            .to(DynamicTypeTransformerStringReplace)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Date'])
            .to(DynamicTypeTransformerDate)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Numeric'])
            .to(DynamicTypeTransformerNumeric)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Explode'])
            .to(DynamicTypeTransformerExplode)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/ConditionalConversion'])
            .to(DynamicTypeTransformerConditionalConversion)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/ObjectField'])
            .to(DynamicTypeTransformerObjectField)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/LoadAsset'])
            .to(DynamicTypeTransformerLoadAsset)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/LoadDataObject'])
            .to(DynamicTypeTransformerLoadDataObject)
            .inSingletonScope();

        // Bind types without settings
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/FlattenArray'])
            .to(DynamicTypeTransformerFlattenArray)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/ReduceArrayKeyValuePairs'])
            .to(DynamicTypeTransformerReduceArrayKeyValuePairs)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/HtmlDecode'])
            .to(DynamicTypeTransformerHtmlDecode)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Boolean'])
            .to(DynamicTypeTransformerBoolean)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsArray'])
            .to(DynamicTypeTransformerAsArray)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsColor'])
            .to(DynamicTypeTransformerAsColor)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsCountries'])
            .to(DynamicTypeTransformerAsCountries)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/Gallery'])
            .to(DynamicTypeTransformerGallery)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/ImageAdvanced'])
            .to(DynamicTypeTransformerImageAdvanced)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/QuantityValue'])
            .to(DynamicTypeTransformerQuantityValue)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/QuantityValueArray'])
            .to(DynamicTypeTransformerQuantityValueArray)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/InputQuantityValue'])
            .to(DynamicTypeTransformerInputQuantityValue)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/InputQuantityValueArray'])
            .to(DynamicTypeTransformerInputQuantityValueArray)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsGeobounds'])
            .to(DynamicTypeTransformerAsGeobounds)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsGeopoint'])
            .to(DynamicTypeTransformerAsGeopoint)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsGeopolygon'])
            .to(DynamicTypeTransformerAsGeopolygon)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/AsGeopolyline'])
            .to(DynamicTypeTransformerAsGeopolyline)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/Transformer/ImportAsset'])
            .to(DynamicTypeTransformerImportAsset)
            .inSingletonScope();

        // Register all types into the registry
        const transformerRegistry = container.get<DynamicTypeTransformerRegistry>(
            bundleServiceIds['DataImporter/DynamicTypes/Transformer/Registry']
        );

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
            bundleServiceIds['DataImporter/DynamicTypes/Transformer/ImportAsset'],
        ] as const;

        for (const serviceId of allTransformerServiceIds) {
            transformerRegistry.registerDynamicType(container.get(serviceId));
        }

        // ── Data Target registry ────────────────────────────────────────────────
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/DataTarget/Registry'])
            .to(DynamicTypeDataTargetRegistry)
            .inSingletonScope();

        // Data Target types
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/DataTarget/Direct'])
            .to(DynamicTypeDataTargetDirect)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/DataTarget/Classificationstore'])
            .to(DynamicTypeDataTargetClassificationstore)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/DataTarget/ClassificationStoreBatch'])
            .to(DynamicTypeDataTargetClassificationstoreBatch)
            .inSingletonScope();
        container
            .bind(bundleServiceIds['DataImporter/DynamicTypes/DataTarget/ManyToManyRelation'])
            .to(DynamicTypeDataTargetManyToManyRelation)
            .inSingletonScope();

        // Register all types into the registry
        const targetRegistry = container.get<DynamicTypeDataTargetRegistry>(
            bundleServiceIds['DataImporter/DynamicTypes/DataTarget/Registry']
        );

        targetRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/DataTarget/Direct'])
        );
        targetRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/DataTarget/Classificationstore'])
        );
        targetRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/DataTarget/ClassificationStoreBatch'])
        );
        targetRegistry.registerDynamicType(
            container.get(bundleServiceIds['DataImporter/DynamicTypes/DataTarget/ManyToManyRelation'])
        );
    },
};
