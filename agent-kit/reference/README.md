# Development infrastructure references

M00 adapts these files into the real repository at corresponding paths. They are
not automatically installed and are not a production deployment.
The Docker build assumes docker/frankenphp/Caddyfile and conf.d/app.ini have been
placed in the project. The app service assumes Symfony public/index.php exists.
Create/merge the actual skeleton first, using containerized Composer if needed.
Run make install only after the repository's real composer.json exists.

package.json is a starting toolchain request, not a lockfile. Resolve actual versions
with npm inside Docker and commit package-lock.json. Use npm ci after that.
The reference does not include the renderer/compiler services required by M02, nor
production services/images required by M08. Its credentials are local development
examples only. M00 must address UID/GID and existing project configuration.
