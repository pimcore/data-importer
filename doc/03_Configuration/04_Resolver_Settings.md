---
title: Resolver Settings
description: Decide which data object a record belongs to, where it is stored, and whether it is published.
---

# Resolver Settings

The resolver answers three questions for every imported record, in this order:

1. Does a matching data object already exist? (**Element Loading**)
2. If not, where is the new object created? (**Element Creation**) - if yes, does it move? (**Element Location Update**)
3. Is the object published afterwards? (**Element Publishing**)

<div class="image-as-lightbox"></div>

![Resolver Settings](../img/resolver_settings.png)

Several strategies need a **Data Source Index**: the field of the import record that holds the value to look up.

## Data Object Class

The Pimcore data object class the records are imported into. It determines which fields the
[mapping](./05_Mapping_Configuration/README.md) can write to.

## Element Loading

How the importer finds an existing data object so it updates it instead of creating a duplicate.

### `No Loading`

Never looks for an existing object. Every record creates a new data object.

### `Id`

Looks up the object by its Pimcore ID.

- **Data Source Index**: field holding the ID.

### `Path`

Looks up the object by its full path.

- **Data Source Index**: field holding the path.

### `Attribute`

Looks up the object by one of its attributes, for example a remote ID or an EAN.

- **Data Source Index**: field holding the attribute value.
- **Attribute Name**: attribute of the data object to match against.
- **Language**: for localized attributes, the language to search in.
- **Include unpublished objects**: also match unpublished objects. Disabled by default, so an unpublished object is not
  found and the import creates a second one.

## Element Creation

Where a new data object is placed. This applies only to records for which **Element Loading** found nothing.

### `Static Path`

Puts every new object into one fixed folder.

- **Path**: target folder.

### `Find or Create Folder`

Reads a folder path from the record and uses it. If the path does not exist, the folders are created.

- **Data Source Index**: field holding the folder path.
- **Fallback Path**: folder used when the record holds no path.

### `Find Parent`

Looks up an existing data object and uses it as the parent.

- **Find Strategy**: how the parent is located.
  - `By ID`: by Pimcore ID.
  - `By Path`: by full path.
  - `By Attribute`: by an attribute value, with these extra settings:
    - **Class**: data object class to search, may differ from the imported class.
    - **Attribute Name**: attribute to match against.
    - **Language**: for localized attributes, the language to search in.
- **Data Source Index**: field holding the value to look up.
- **Fallback Path**: folder used when the parent cannot be found.
- **As Variant**: creates the new object as a variant of the resolved parent instead of a child.

### `Do Not Create`

Creates nothing. Records without a matching existing object are skipped, which turns the import into an update-only
import.

## Element Location Update

Whether an object that **Element Loading** found is moved.

The available strategies are `No Change`, `Static Path`, `Find or Create Folder` and `Find Parent`, with the same
settings as under Element Creation. `No Change` leaves the object where it is.

:::note

Element Creation applies to newly created objects, Element Location Update applies to existing ones. A record never runs
through both.

A variant cannot change its parent. If a location update strategy would move a variant, the record fails with an error.

:::

## Element Publishing

The published state of an imported object.

| Strategy | Existing objects | New objects |
|---|---|---|
| `Always Publish` | published | published |
| `Attribute Based` | set from a field of the record | set from a field of the record |
| `No Change / Publish New` | unchanged | published |
| `No Change / Unpublish New` | unchanged | unpublished |

`Attribute Based` needs a **Data Source Index**: the field holding the published state. When the record does not contain
that field, the object is unpublished.
