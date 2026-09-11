---
name: data-importer-configuration
description: >-
  Read and propose Pimcore Data Importer configurations — the document shape, the order the
  sections depend on each other in, and the mistakes that only show up when an import runs.
---

# Import configurations

An import configuration is one stored document describing a pipeline: where the file comes
from, how it is parsed, which object each row resolves to, how columns map onto fields, and
when it runs. It is configuration, not content — nothing you change here moves data until the
next import executes.

## The workflow

1. `list_import_configs` — find the exact name. Never guess one.
2. `get_import_config` — read the whole document. **Always.** You cannot propose a change to a
   document you have not read.
3. `propose_import_config` — send the **complete** document back with your changes applied.

Never ask the user to paste a configuration. If you need it, read it.

## The document

| Section | What it holds |
|---|---|
| `general` | `name`, `type`, `active`, `description`, `group`. `active: false` means the import never runs. |
| `loaderConfig` | where the file comes from — `{type, settings}`; type is `asset`, `sftp`, `http`, `upload`, `push` or `sql`. |
| `interpreterConfig` | how it is parsed — `{type, settings}`; type is `csv`, `json`, `xlsx`, `xml` or `sql`. |
| `resolverConfig` | which object a row becomes — `loadingStrategy`, `createLocationStrategy`, `locationUpdateStrategy`, `publishingStrategy`, each `{type, settings}`. |
| `processingConfig` | `executionType`, `idDataIndex`, `doDeltaCheckCheck`, `cleanup: {strategy}`. |
| `mappingConfig` | a **list**, one entry per column mapping (below). |
| `executionConfig` | `scheduleType` and `cronDefinition`. |

## Mappings

Each entry maps one source column onto one target field:

```yaml
label: mileage                       # what a person calls it
dataSourceIndex: [mileage]           # the column(s) in the source file
dataTarget:
  type: direct                       # direct | quantityValue | classificationstore | manyToManyRelation
  settings: { fieldName: saleInformation.SaleInformation.milage, language: '' }
transformationPipeline: []           # ordered transformers, applied before writing
mappingId: <uuid>                    # identity — see below
```

- **Keep `mappingId`.** It is the row's identity. Preserve it on every row you are not
  changing; a row that loses its id reads as "removed, then a different one added", and the
  reviewer sees a change nobody made. Only a genuinely new row gets a new id.
- **`dataSourceIndex` is a list**, even for one column.
- **Send `mappingConfig` as a plain list** — not wrapped in a key of its own, not keyed by index.
- A target field must exist on the class the resolver writes to. Use the data-object tools to
  check a field name you are not certain of rather than inventing one.

## What to be careful about

- **Dropping a mapping does not clear data.** The attribute keeps whatever it holds; it simply
  stops being imported. Say so when you propose a removal.
- **Changing a `dataTarget.type`** — for example `direct` to `quantityValue` — changes how every
  future row is written. Mention the unit, and that existing values are not migrated.
- **`general.active`** is the on/off switch for the whole pipeline. Never flip it as a side
  effect of another change.
- **Never propose a run.** Starting an import is a separate, explicit action.

## Proposing

`propose_import_config` writes nothing. It opens a change set the user reviews field by field,
and only what they tick is applied. So:

- Send the complete document. Anything you omit keeps its current value, but a section you
  rewrite wholesale loses the parts you left out of it.
- A key that is not part of an import configuration is rejected, not stored. That is the tool
  telling you the document came from memory rather than from `get_import_config`.
- Every `type` (loader, interpreter, the resolver strategies, cleanup, data targets, transformation
  operators) must be one this installation has. A rejected value comes back with the allowed
  list — pick from it, never invent a name that sounds right.
- Give a one-sentence `summary` naming what actually changes — "Maps co2_emission and fuel_type,
  drops the additional-images mapping, moves the run to 03:00."
- End your turn after a successful proposal. The review widget appears on its own; do not
  describe the diff in chat, and do not ask whether to apply it.
