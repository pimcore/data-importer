# Classification store round trip audit

Internal audit of what the importer can actually write into a classification store key, and what it
loses on the way. Not user documentation — it lives next to
`ClassificationStoreRoundTripAuditTest`, which produces its verdict table and asserts that this
document has not drifted away from the code.

## What this is evidence of, and what it is not

The round trip under audit is: **source value → factory-built transformation pipeline → data target →
object save → reload → compare**. It drives the real `MappingConfigurationFactory`, the real
`Classificationstore` and `ClassificationstoreBatch` data targets and a real object save against a
database.

It is **not** evidence about anything else that reads or writes a classification store key:

- it does not drive the **object editor**, so it says nothing about what the editor shows for a key
  the importer wrote, or about what the editor would have written for the same input;
- it does not drive the **Studio grid export**, so it says nothing about the CSV shape that export
  produces for a classification store column;
- a verdict of *survived* therefore means "the importer wrote it and the importer read it back", not
  "the value is correct everywhere in Pimcore".

The pipeline under audit is empty: each probe value is handed to the data target as the source
carries it. That is deliberate — the question is what the **target** does, and an operator in front
of it would answer a different one.

## Key types under audit

The set is derived at runtime from core's own acceptance rule for a classification store key rather
than hardcoded: a type is under audit when its field definition **normalises to something a single
value column can hold** (the declared return type of `normalize()` is not `array`), **or** a
`classificationstore_<type>` marshaller is registered for it, which takes the normalised value apart
into the two value columns. That is exactly the fork
`Classificationstore\Engine\EavEngine::save()` takes.

Against `pimcore/pimcore` 2026.3 / 2026.x that rule yields:

```
booleanSelect
calculatedValue
checkbox
country
countrymultiselect
date
datetime
email
encryptedField
firstname
gender
input
inputQuantityValue
language
languagemultiselect
lastname
multiselect
numeric
password
quantityValue
quantityValueRange
rgbaColor
select
slider
table
textarea
time
user
wysiwyg
```

`ClassificationStoreRoundTripAuditTest::testTheDerivedKeyTypeSetMatchesTheAuditDocument()` compares
the derived set against this list, so a core release that adds or drops an accepted type fails the
test rather than quietly leaving this document wrong. Types the rule rejects — the relation types,
the geo types, `block`, `localizedfields`, `structuredTable`, `hotspotimage`, `image`,
`imageGallery`, `link`, `video`, `urlSlug`, `consent`, `dateRange`, `numericRange` — cannot reach a
single value column at all and are out of scope here, even where the mapping editor currently offers
a key of that type.

## Verdict table

The table is written to `tests/_output/classification-store-round-trip-audit.md` on every run, with
one row per (key type, field variant, target):

| column | meaning |
| --- | --- |
| key type | the classification store key's own type, from the list above |
| field variant | `non-localized` (`csField`, no `language` setting) or `localized` (`csLocalizedField`, with one) |
| target | `classificationstore` (one key per mapping entry) or `classificationstoreBatch` (many keys per entry) |
| verdict | `survived` — read back equal to the source value; `differed` — written and read back as something else; `not reachable` — the write or the save raised |
| note | for `differed`, what it read back as; for `not reachable`, the exception |

Read the table from the run, not from here: pasting it in would be a second copy to keep in step
with the code, which is the failure mode this audit exists to catch. The verdicts worth knowing in
advance:

- **A plain date string in a mixed `array` batch entry does not survive.** See *Items for the
  follow-up ticket*.
- **A `quantityValue` or `inputQuantityValue` key in a mixed `array` batch entry reads back as a
  quantity value model object, not as the source scalar** — it is `differed` by the table's
  definition and is the intended behaviour of this change.
- **`password` reads back hashed and `calculatedValue` is computed rather than stored**, so both are
  `differed` by construction and not defects.

## What counts as empty

Both classification store targets use **one** empty test, and it is PHP falsiness — the set the
batch target's own `array_filter()` has always dropped:

| value | empty |
| --- | --- |
| `null` | yes |
| `false` | yes |
| `0` | yes |
| `0.0` | yes |
| `"0"` | yes |
| `""` | yes |
| `[]` | yes |
| anything else | no |

**`0` counting as empty is a known wart.** It is accepted deliberately as the migration-safe choice:
it is what `ClassificationstoreBatch` has always done, and narrowing the test would change what
existing unmarked entries import. A numeric key whose source legitimately carries `0` is written
only on an entry whose `Write If Source Is Empty` is in force and on — see *Two entries that look
alike*.

## Divergence from Direct

`Direct::checkAssignData()` (`src/Mapping/DataTarget/Direct.php:167-168`) does **not** use PHP
falsiness. It asks the field definition instead:

```php
$currentDataIsEmpty = $fieldDefinition->isEmpty($currentData);
$newDataIsEmpty = $fieldDefinition->isEmpty($newData);
```

So the same source value can be empty for one target and not for the other. `0` is the clearest
case: `Data\Numeric::isEmpty(0)` answers per the field definition's own rule, while the
classification store targets drop `0` unconditionally. A `Direct` entry and a classification store
entry fed from the same column therefore disagree about whether that column was empty.

This is recorded as a **found difference**, not fixed here: aligning the two means changing what
either `Direct` or the classification store targets write for existing configurations, which is a
behaviour change of its own. See *Items for the follow-up ticket*.

A second, smaller divergence: `Direct` also offers `Write If Target Is Not Empty`, which the
classification store targets do not. It would need a per-key read-back they do not do today.

## Two entries that look alike

`writeIfSourceIsEmpty` is only in force on a mapping entry that carries the marker
`writeIfSourceIsEmptyConfigured`, which the editor writes when the control is changed or either
classification store target is selected. **Two mapping entries whose settings look identical
therefore import differently:**

| entry | marker | stored `writeIfSourceIsEmpty` | `Classificationstore` writes an empty value | `ClassificationstoreBatch` writes an empty value |
| --- | --- | --- | --- | --- |
| authored before this version | absent | `true` | yes | **no** |
| authored before this version | absent | `false` | **yes** | no |
| authored before this version | absent | not stored | yes | no |
| control saved, on | present | `true` | yes | yes |
| control saved, off | present | `false` | no | no |
| target type just selected | present | not stored | yes | yes |

The two rows in the middle are the point: with the marker absent the stored value is ignored
entirely, so an entry that says `writeIfSourceIsEmpty: false` still writes empty values through the
single-key target, and one that says `true` still drops them through the batch target. That is
today's behaviour, kept on purpose so that no stored configuration changes meaning on upgrade — and
it is why nothing in this change writes to a stored configuration: there is no migration, no
normalisation command and no code path that rewrites a Data Hub import configuration. A file-backed
configuration and a settings-store configuration behave identically, because the marker is read from
the configuration either way.

The editor shows the **effective** value while the marker is absent — off for the batch target, on
for the single-key target — rather than the stored one, which would state the opposite of what the
import does.

## Documentation check

Reported separately from the round-trip verdicts, because no gate in this repository reads a
markdown list, a TypeScript array and a PHP `switch` together. Checked by hand, against the code as
of this change:

| documented list | code it claims to describe | result |
| --- | --- | --- |
| `doc/03_Configuration/05_Mapping_Configuration/02_Data_Target/README.md:38-40` — supported field types of `Many-to-Many Relation`: `manyToManyRelation`, `manyToManyObjectRelation`, `advancedManyToManyRelation`, `advancedManyToManyObjectRelation` | `src/Mapping/DataTarget/ManyToManyRelation.php:56-60`, the four `case` labels of the `switch` | **matches** — same four, same spelling; every other type falls through to the configuration error the page describes |
| `README.md:42-43` — accepted result types of `Many-to-Many Relation`: `advancedDataObjectArray`, `dataObjectArray`, `assetArray`, `advancedAssetArray` | `assets/studio/js/src/modules/data-importer/dynamic-types/data-target/many-to-many-relation/dynamic-type-data-target-many-to-many-relation.tsx:24`, the `supportsType()` array | **matches** — same four |
| `README.md:75` and `01_Classification_Store_Batch_Details.md:109` — accepted result types of `Classification Store Batch`: `array`, `quantityValueArray`, `inputQuantityValueArray`, `dateArray` | `assets/studio/js/src/modules/data-importer/dynamic-types/data-target/classificationstore/dynamic-type-data-target-classificationstore-batch.tsx:26`, the `supportsType()` array | **matches** — same four, and the two pages agree with each other |

No correction was needed in any of the three. The option lists on the same page **were** wrong and
are corrected by this change: neither classification store section listed `Write If Source Is Empty`,
and the batch section listed no configuration options at all.

## Items for the follow-up ticket

Everything the audit found is either fixed here or listed below. Nothing is left unclassified.

**Fixed here, because it was trivial:**

1. *A non-localized classification store field could not be imported at all.* Both targets assigned
   `$settings['language'] ?? null` to a non-nullable `private string $language`, and the editor
   stores no `language` for a non-localized field, so `setSettings()` raised a `TypeError` —
   which `ImportProcessingService::processQueueItem()` does not catch, because it catches
   `\Exception` and not `\Error` (`src/Processing/ImportProcessingService.php:137`). The property is
   now nullable, which is what
   `Direct.php:34` has always been in effect. Without this the non-localized half of this audit could
   not produce a single verdict.
2. *An `inputQuantityValue` key in a batch entry read unit **ids**, while the `Input Quantity Value`
   operator reads unit **abbreviations**.* **Found and fixed here, not deferred.** As first built, the
   dispatch took the shared `QuantityValueUnitSourceTrait` default `id` for both key types, while
   `Operator\Factory\QuantityValue` inherits `id` and
   `Operator\Factory\InputQuantityValue::getDefaultUnitSource()` overrides it to `abbr`
   (`src/Mapping/Operator/Factory/InputQuantityValue.php:23-26`). A source column of abbreviations
   therefore resolved fine through the operator and resolved to no unit inside a batch entry. This
   divergence was introduced by the dispatch rather than inherited from it, so it is not a
   pre-existing defect and does not ship: `ClassificationstoreBatch::UNIT_SOURCE_BY_TYPE` now
   mirrors each key type's own operator default — `id` for a `quantityValue` key, `abbr` for an
   `inputQuantityValue` key. No unit-source setting was added to the target; the key's type decides.
   All four cells of (key type × unit spelling) are asserted by
   `ClassificationstoreBatchQuantityValueTest::testTheUnitSourceFollowsEachKeyTypesOwnOperatorDefault()`.

**For the follow-up ticket:**

3. *A plain date string in a mixed `array` batch entry is lost.* `ClassificationstoreBatch` has no
   format handling of its own — `setSettings()` takes `fieldName` and `language` and nothing else —
   and `Data\Date::normalize()` keeps a value only when it is already a `Carbon`, returning `null`
   otherwise. A `date` key written as `['1-4' => '2021-07-22']` therefore saves as `null`. This is
   the outcome the audit found, asserted by
   `testAPlainDateStringDoesNotSurviveAMixedBatchEntry()`.
   **Date dispatch was deliberately not added here**: unlike a quantity value, a date string cannot
   be interpreted without knowing its format, and the format would be a new configuration surface on
   a target this ticket is not allowed to grow one on. Guessing the format would misread `02/03/2026`
   on half the world's data silently. The documented workaround — a separate mapping entry ending in
   the `Date` operator, which does have a format setting — keeps working and is the only correct
   route today.
4. *The empty test diverges from `Direct`.* See *Divergence from Direct*. Deciding whether the two
   should agree, and which one moves, is a behaviour change for existing configurations and belongs
   in its own ticket.
5. *A per-group unit override is stored but does not reach the importer.* The override **does**
   exist on this branch: `KeyGroupRelation` carries `$definitionOverride`, `defaultUnit` is one of
   the three properties an assignment may override, and `KeyGroupRelation::getEffectiveDefinition()`
   merges it over the key's own definition. What drops it is the accessor in between. The unit for a
   plain scalar is taken from `Classificationstore\Service::getFieldDefinitionFromKeyConfig()`, which
   reads `$keyConfig->getDefinition()` — not `getEffectiveDefinition()` — and memoises the result
   under the key id alone, so the same definition is returned for every group the key is assigned to.
   Core's own docblock on `getEffectiveDefinition()` says as much: nothing in core reads it yet.
   The importer is the consumer here, not the defect, and it is left as the consumer deliberately:
   it begins honouring the override once core resolves definitions through it and keys the definition
   cache on (key id, group id). Calling `getEffectiveDefinition()` from this bundle would duplicate
   the resolution that change centralises, and is not done here.
   The unit a plain scalar resolves to is therefore the key's own, whichever group it was written
   through; no test here pins that, because it is core's resolution and not this bundle's.
6. *The mapping editor offers classification store keys of types that cannot be stored.*
   `TransformationDataTypeService` maps `geoPoint`, `geoBounds`, `geoPolygon`, `geoPolyline`,
   `hotspotimage` and `imageGallery` to pimcore types, and
   `ClassificationStoreDataTypeService::listClassificationStoreKeyList()` offers classification store
   keys filtered by exactly that map — but none of those types is in the derived set above, because
   none normalises to a single value and none has a classification store marshaller. Selecting such a
   key is therefore offered and cannot work. Out of scope here; it is a defect of the key list, not of
   the data targets.
