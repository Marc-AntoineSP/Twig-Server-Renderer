# Composition instance properties

Select an immutable reference in the composition tree. **Properties form** shows its
exact release's schema and current instance values. String textareas preserve line breaks.
Boolean, integer/number,
scalar enum and constant controls share their field descriptions with source and
catalogue previews. Labels, descriptions, required state, numeric/length hints and
default guidance come from validated metadata. Descriptions and values are plain text.

**Tree**, **Properties** and **Preview** links move keyboard focus to the corresponding
heading without changing the selection or edits. On wide screens the preview sits beside
the inspector and follows page scrolling. On smaller screens the sections stack; the
preview viewport scrolls horizontally with keyboard access. Frame dimensions measure
the content viewport separately from its border, keeping resize reports stable and the
selected width exact. There is no nested vertical
inspector scroller.

## Presence and defaults

The canonical working value is the selected tree node's `props` object. Form edits clone
that complete object and change only the named key. **Advanced JSON** edits the same
object, including nested and additional properties. Unknown keys remain until an explicit
edit removes them; server rules still decide whether they are allowed.

| Action or value | Meaning |
| --- | --- |
| Uncheck **Include property** | Delete the key; no override is saved |
| Check **Include property** | Deliberately include the guidance value, or an initial value for that control |
| `null` / **Set … to null** | Include an explicit null; distinct from omission |
| `false`, `0`, `""`, arrays and objects | Preserve the exact JSON value and structure |
| **Use default for …** | Explicitly set that key to the release default, or its schema default annotation if the release omits it |
| **Use release defaults for this instance** | Deliberately replace the whole instance object with the exact release defaults, removing other overrides |

Opening the inspector or switching views does not fill defaults. JSON Schema `default`
is guidance, not automatic insertion during validation; see the
[JSON Schema annotations reference](https://json-schema.org/understanding-json-schema/reference/annotations).
The composition renderer passes the instance object as supplied; it does not merge
omitted keys with release defaults. Omitting a required key can therefore produce a
validation error. Adding a new reference retains the existing command's behavior of
copying release defaults when no explicit props object is supplied.

## Structured schemas and invalid text

The application supports its existing bounded JSON Schema draft 2020-12 subset:
`$schema`, `$defs`, `$ref`, `type`, `properties`, `required`, `additionalProperties`,
`enum`, `const`, `default`, `minimum`, `maximum`, `minLength`, `maxLength`, `minItems`,
`maxItems`, `items`, `title` and `description`. The root is an object. Only nonrecursive
local `#/$defs/name` references are allowed. Existing 64 KiB, depth 20, 500 schema nodes,
100 properties/definitions and 100 enum values remain enforced on the server.

The shared browser renderer describes controls; it does not implement another schema
validator. Simple local references resolve for controls. Constraint-bearing reference
siblings, unions without one scalar type, arrays, nested objects and structured enums
use a JSON value textarea. The full object is always available in **Advanced JSON**.
The existing server validator checks the complete schema and URL safety on preview and
Save. Browser input hints cannot replace that validation.

Invalid JSON, an empty numeric control or malformed structured value keeps its text and
the last valid canonical object. Draft text belongs to the stable node ID, so selecting
another node and returning retains it. Switching views and saving are blocked until it
is corrected or **Discard invalid property text** is deliberately chosen. That action
restores the last valid object, not release defaults. Navigation uses the existing unsaved
editing guard; temporary drafts are memory-only and are cleared when the editor leaves.

Server schema errors appear beside supported fields and in a plain-text path summary.
Complex/root errors remain in the summary. **Go to invalid properties** selects the
affected node and focuses its errors. Save is disabled while known property errors or
invalid text remain. Editing a value triggers the existing debounced validation/preview
lifecycle; explicit Save persists it. Field edits never publish a release.

## Identity, replacement and server parity

Schema reads are scoped to the selected stable node ID and exact `name@version`.
Aborted or obsolete metadata, validation and preview replies cannot write into another
instance or replace a newer preview. Each iframe still uses the existing opaque-origin
sandbox, sequence and random-channel checks.

Changing the immutable reference preserves the complete current props object only if
the shared command accepts it under the new exact schema and dependency graph. If any
value is incompatible, the replacement fails and the previous reference and all edits
remain available for resolution. There is no partial dropping or coercion. The picker's
explicit release-default checkbox submits the new defaults instead. Labels and controls
refresh only after an accepted replacement. See [reference selection](REFERENCE_PICKER.md).

Manual and MCP commands use the same CompositionWorkspace and PropsValidator. Reference
property errors identify `nodes.<stable-node-id>.props` followed by the validator path,
for example `nodes.n_00000000000000000000000000000001.props/count`. Legacy trees
without IDs retain the earlier `reference.<name@version>` prefix. Browser commands alter
the unsaved tree until Save; MCP commands retain their existing revision/receipt-based
persistence. Neither path modifies the referenced immutable release.

No migration, dependency update, new schema dialect or operator setting is required.
The existing JavaScript requirement for composition editing remains. Shared preview
controls also preserve omission/falsy values and reject incomplete field text before
rendering; choosing an example or reset deliberately replaces transient preview props.

## Verification

`tests/Browser/instance_properties.spec.js` extends the real repository alert fixture
with `tests/Browser/fixtures/instance-properties.json` inside verified disposable storage.
It checks saved values, defaults/omission, invalid-text recovery, held validation and
preview replies, schema replacement, shared preview fields and keyboard/axe layouts at
390/1440 in light/dark modes. `ComposeMcpTest::testInstancePropertyParityPreservesOmissionFalsyStructuredValuesAndNodeScopedErrors`
checks persisted manual/MCP values and node-scoped errors through real services.
Exact executed commands, results and remaining manual checks are recorded in
[STATUS.md](../../agent-kit/STATUS.md).
