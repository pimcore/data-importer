---
name: data-importer-configuration
description: >-
  Use when creating or changing a Pimcore Data Importer configuration. Covers the order the
  tools must be called in, the shape of a configuration, how to pick loaders, interpreters,
  resolver strategies, operators and data targets, and the mistakes that make a configuration
  invalid or silently inert.
---

# Data Importer Configuration

A Data Importer configuration describes how records from a file or endpoint become Pimcore data
objects. You build it as one document and write it in one call.

## Workflow

> **Step 0 - never compose from a guess.** Field names, class ids, operator types and strategy
> types are all install specific. Every one of them comes from a tool. Guessing them is the most
> common cause of a rejected configuration.

1. **`get_import_config_examples`** - start here, always. Three complete working configurations
   with a summary of what each uses. Copy the closest one; it is the cheapest way to learn the
   required top level structure. The class ids and field names in them are illustrative, so
   replace them.
2. **`get_import_config_context`** - reference data. It defaults to `classes`, `loaders` and
   `interpreters`, which is what you need to decide *what to import into* and *where the data
   comes from*. Request more sections only when you need them:
   - `resolver` before writing `resolverConfig`
   - `targets` and `operators` before writing `mappingConfig`
   - `field_type_matrix` (needs `classId`) to learn which field accepts which result type
   - `schema` only when a validation error is otherwise unexplainable; it is large.
3. **`list_import_configs`** - check whether the name is taken before creating.
4. **Build the configuration** (see the structure below).
5. **`enrich_import_config`** - computes `transformationResultType` for every mapping item.
   Returns `[{index, label, transformationResultType}]`; set each on the matching item.
6. **`validate_import_config`** - fix every error and validate again before writing.
7. **`create_import_config`** then **`save_import_config`** - create makes an empty entry, save
   writes the document.

To change an existing configuration, read it with `get_import_config`, modify it, then enrich,
validate and `save_import_config`.

## Structure

```yaml
general:
  type: dataImporterDataObject
  name: my-import
  active: true            # without this the import never runs
loaderConfig:             # where the data comes from
  type: asset
  settings:
    assetPath: /Import Test/car-export.json
interpreterConfig:        # how the file is read
  type: json
resolverConfig:           # which object a record maps to, and where it lives
  elementType: dataObject
  dataObjectClassId: 'CAR'
  loadingStrategy:        # how an existing object is found, or notLoad to always create
    type: attribute
    settings:
      dataSourceIndex: articleNumber
      attributeName: articleNumber
  createLocationStrategy:
    type: staticPath
    settings:
      path: /import/cars
  locationUpdateStrategy:
    type: noChange
  publishingStrategy:
    type: noChangePublishNew
processingConfig:
  executionType: sequential
mappingConfig:            # one entry per target field
  - label: Article Number
    dataSourceIndex: ['articleNumber']
    transformationResultType: default
    dataTarget:
      type: direct
      settings:
        fieldName: articleNumber
    transformationPipeline: []
```

`dataSourceIndex` is the source field. For CSV it is the column number as a string starting at
`'0'`; for JSON and XML it is the field name.

## Rules that decide whether it works

**Settings are nested structures, never JSON strings.** In YAML write

```yaml
settings:
  assetPath: /Import Test/car-export.json
```

not `settings: '{"assetPath":"..."}'`. A JSON string is rejected.

**Quote numeric class ids.** `dataObjectClassId: 6` is parsed as a number and rejected; write
`dataObjectClassId: '6'`.

**Enrich before validating.** Without `transformationResultType`, every field is checked as type
`default`, so numeric, date and relation targets report spurious incompatibilities.

**A field must accept the result type your pipeline produces.** `get_import_config_context` with
`field_type_matrix` lists, per result type, the fields that accept it. If validation says a field
cannot store a type, either change the target field or add an operator that converts the value.

**Chain operators by type.** Each operator in `transformationPipeline` declares
`acceptedInputTypes` and `outputTypes` (the `operators` section). The output of one must be
accepted by the next, and the last output is the item's `transformationResultType`.

**Loading by attribute needs a loadable field.** The attribute must be a top level field of the
class that Pimcore can filter on, or one of the system columns `id`, `key`, `path`. Object brick
paths like `attributes.Engine.cylinders` work too. A field type that is not filterable, and a
field that does not exist, are both rejected.

**`active` defaults to false.** A configuration that validates and saves but has
`general.active: false` will never run.

## When validation fails

The error `path` points at the exact place: `mappingConfig[2].dataTarget`, `resolverConfig`,
`processingConfig.cleanup.settings`. Fix that node and validate again rather than rewriting the
document. `Unrecognized option "x" under "y"` means the key does not belong there at all;
`Class \`X\` not found` means the class id is wrong, so re-read the `classes` section.
