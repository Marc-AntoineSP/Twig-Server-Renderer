# Catalogue contracts

Local Composer library used by the studio and installer. It is not published to Packagist.
The root path repository fixes version 0.1.0; the root Composer lock resolves dependencies.
Schemas are versioned copies of the normative agent-kit schemas, checked for equality.

`PackageValidator`, `StrictJson`, `SafeArchive`, `PackageArchive` and `PackageGraph` enforce
one text-only v1 profile: stable exact versions, owned portable paths, complete inventories,
UTF-8 text, 100 files/package, 1 MiB/file, 2 MiB/source closure, finite props/compositions,
registered system-font themes and known generated theme CSS. Archive caps: 10 MiB compressed,
50 MiB inflated, 500 entries, 100:1 expansion. Unknown formats fail with upgrade guidance.
Binary assets/SVG/font uploads and remote refs are unsupported. JSON duplicate keys, YAML
aliases/custom tags, nested-controller identity conflicts and executable CSS directives
are rejected. No imported Composer/npm hooks or JavaScript run in this library.

Graph resolution takes an explicit exact-package callback, so imports can validate a staged
closure before any database/filesystem mutation. Rendering/bundling remain separate bounded
workers in the studio. The consumer must review origin and browser code before installation:
installed browser behavior executes in the receiving application's own document.
