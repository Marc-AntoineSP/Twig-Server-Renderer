# Contract schemas

JSON Schema draft 2020-12. V1 release versions are stable x.y.z only; prerelease/build
metadata support can be added in a new validated contract revision.
package.schema.json supports component, block, layout and theme envelopes. For themes,
entry points to meta/theme.json and propsSchema/defaults are absent; controllers are empty.
Non-theme packages require propsSchema/defaults and a Twig entry. These conditional
rules are semantic checks in v1. File inventories exclude manifest.json itself.
Package filenames and byte/hash fields are verified from actual bytes, not just schemas.
The schema path expression is only the first check; SECURITY.md defines full normalization
and containment requirements. Do not treat a matching string as safe for extraction.

check_kit.py validates the subset of JSON Schema keywords used by these fixtures and
checks their cross-file integrity. The product must select a maintained full JSON Schema
validator, disable remote references, and implement the additional semantic rules.
