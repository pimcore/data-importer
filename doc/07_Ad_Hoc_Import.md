---
title: Ad Hoc Import
description: Import a CSV or XLSX file into a data object folder in Pimcore Studio, without an import configuration.
---

# Ad Hoc Import

An ad hoc import brings one CSV or XLSX file into a data object folder, straight from the folder's context menu in
Pimcore Studio. It needs no stored import configuration: the columns are mapped in the dialog, every row is checked
before anything is written, and the import runs in the background. For recurring imports, other sources or more than
one column per field, use an [import configuration](./03_Configuration/README.md) instead; an ad hoc import can also use
an existing one for its mapping.

## Who Can Use It

- The `plugin_data_importer_ad_hoc_import` permission ("Ad-hoc import"), in the Datahub permission category. The
  installer creates it, and on an existing installation the bundle's migrations add it.
- On the folder: the rights to create and save objects. Each row is checked again when it is written, with the rights
  the user has at that moment: saving or publishing an object that exists, creating one in the folder, and editing each
  language of a localized field. A row the user may not write fails with its reason; the other rows are saved.
- Only objects the user may view can be matched, and only classes the user may create objects of can be chosen.

The import writes as the user who started it, so the version history shows that user.

## The Dialog

Right-click a data object folder and choose **Import file…**. The dialog has four steps.

### File

Drop a CSV or XLSX file, or choose one. A file over 5,000 rows, a file that cannot be read, or a file of another type
is refused; for larger files use an import configuration.

- **CSV:** the delimiter is detected and can be changed, as can the enclosure, the escape character and whether the
  column names are in the first row.
- **XLSX:** choose the sheet and whether the column names are in the first row.

The first rows of the file follow every change. Under **Objects**, choose:

- **Class:** the class of the objects to import.
- **Match existing objects by:** **Object ID** or a **Field** of the class, each read from a column of the file. A found
  object is updated where it is. With **Don't match**, every row creates a new object, keyed by the column's value.
- **New objects in** (optional): the folder for new objects. Empty means the folder you clicked.

Objects are matched only in the folder you clicked and below it.

### Mapping

**Map the columns here** lists one row per column of the file. Choose the field each column goes into, or
**Don't import** to leave it out. Columns left out are listed under **Not imported** and can be brought back.

- Fields are grouped as in the class layout. Localized fields take a language, object bricks and classification
  store keys can be chosen as well.
- Suggestions come from comparing the column names with the fields, as in the
  [mapping autofill](./03_Configuration/05_Mapping_Configuration/README.md); a column named `Title DE` is suggested for
  the localized field `title` in German. Suggestions taken when the file was read are marked **auto**; **Use all
  suggestions** takes the rest. The dialog does not ask the Pimcore agent for suggestions.
- **Transform…** applies steps to a column's value, top to bottom: trim, replace text, map values, split into a list,
  load an object or an asset by its ID or full path. After them the value is converted to the field's type (number,
  date as year-month-day, yes or no, a list or a gallery). A column whose values cannot go into its field is marked
  and needs a transformation or another field.

The footer counts the columns that are not decided yet: give each a field or **Don't import**.

**Use a Data Importer configuration** takes the mapping of an existing configuration as it is, read only. Only
configurations the user may read are listed, and only those that fit an ad hoc import: their class must be one the
user may create objects of, they must find objects by their ID or a field in all folders and create new objects in one
folder the user may create objects in, and the file must have the columns they read. The configuration's class,
matching and folder replace those of the file step.

### Preview

The whole file is checked before anything is written: what each row will do (create or update, with old and new
values) and every error, with its row and column. **Errors only** shows the rows with errors. Rows with errors include:

- a match value that is empty, appears twice in the file, matches several objects, or is found outside the folder;
- an Object ID that does not exist or belongs to a folder or an object of another class;
- a key for a new object that the folder or another row of the file already uses;
- a value that does not fit its field: no number, no date, neither yes nor no, no option of the field, or a required
  field left empty.

Choose what happens with the rows with errors: **Skip them**, or **Import them without the invalid values**, which
leaves the invalid values unchanged and still skips rows that cannot be matched or saved. Skipped rows can be
downloaded afterwards, by the user who uploaded the file only.

New objects are created unpublished; updated objects keep their published state. An empty cell clears the mapped
field.

### Import

The import runs in the background. The dialog shows its progress and can be closed: Studio sends a notification when
the import is done. The result lists how many objects were updated and created, which rows were skipped in the preview,
and which rows could not be saved, with the reason for each and a download.

## With Change Control

When Change Control is installed, an ad hoc import writes its rows into a branch of its own and merges it at once, as
the user who started it. Before the merge, every row is checked for rights, keys and values. When rows cannot be saved,
nothing is merged yet and the dialog asks for a decision:

- **Merge … rows, leave out …:** merge the other rows and leave those out.
- **Back to mapping:** change the mapping and check again.
- **Discard import:** discard the branch; nothing is written.

The user who started the import gets a notification when a decision is needed; **Import file…** on the folder opens it
again. An import waiting for a decision is discarded after a week. Without Change Control, rows are written directly to
the objects.

## Operations

- **Queue:** an ad hoc import runs through the Data Importer's queue, one row after the other, so
  [queue processing](./01_Installation/README.md#queue-processing) must be set up.
- **Events:** the import fires the events of a normal import (`PostPreparationEvent`, `DataObject\PreSaveEvent`,
  `DataObject\PostSaveEvent`, `DataObject\ProcessElementExceptionEvent`), see [Events](./06_Extending/02_Events.md).
  For an ad hoc import their configuration name is an empty string. A `PreSaveEvent` listener that skips the element
  leaves its row out of the import.
- **Files:** an uploaded file is deleted after its import ran or its dialog was cancelled. A file whose dialog was
  closed without cancelling is removed after a day.
