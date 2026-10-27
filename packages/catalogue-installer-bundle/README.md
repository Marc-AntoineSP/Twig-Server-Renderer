# Catalogue installer bundle

Local package version 0.1.0. Not published to Packagist. Configure a Composer path
repository for this package and ../catalogue-contracts with explicit 0.1.0 versions,
require styleguide/catalogue-installer-bundle:0.1.0 and register
Styleguide\Installer\CatalogueInstallerBundle in the consumer's config/bundles.php.

The runnable fresh Symfony fixture and both lockfiles are in ../../tests/Consumer/project.
The complete setup, credentials, commands, review and recovery procedure is in
../../docs/styleguide/DEVELOPMENT.md, “Local consumer installer”. Run `make consumer`
in the studio repository for the real registry, filesystem, crash and production-browser gate.

The consumer owns its trusted toolchain and deployment. No imported code executes
server-side. Browser controllers become trusted consumer code: inspect the staged dry-run
plan before deployment. Do not serve the consumer while files are being installed; prepare
an isolated release, compile AssetMapper, clear cache, then switch releases.
