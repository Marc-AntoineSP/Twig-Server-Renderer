# Example source packages

packages/alert: real Twig + Stimulus source and schema/defaults.
packages/notice-grid: two alert instances in a composition with exact dependency.
packages/ember: canonical theme and generated CSS source references.

Each manifest inventories actual payload hashes and byte counts. These fixtures
validate structurally and provide expected implementation inputs. They do not
establish that rendering, CSS compilation, isolation or installation is implemented.
The checks in tools/check_kit.py verify fixture integrity and graph references.
