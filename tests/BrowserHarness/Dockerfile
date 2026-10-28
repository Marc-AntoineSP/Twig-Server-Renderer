FROM node:24-trixie-slim@sha256:43b9ba1142b8dcf33fa8f922ca1be85714adc444e0586c7cf7726daaeabc1bea AS browser-fonts
WORKDIR /app
COPY tools/library ./tools/library
COPY resources/library/fonts.json resources/library/fonts.lock.json ./resources/library/
RUN npm ci --prefix tools/library --ignore-scripts --no-audit --no-fund
RUN for family in inter lora jetbrains-mono atkinson-hyperlegible manrope; do node tools/library/fonts-download.mjs --family=$family || exit 1; done

FROM mcr.microsoft.com/playwright:v1.64.1-noble@sha256:47b510dd9230bf0b9bfc9b20102241e577ea7a99334ab4c3ca19f19770e9b8ea
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund
COPY playwright*.js ./
COPY tests/Browser ./tests/Browser
COPY tests/BrowserHarness ./tests/BrowserHarness
COPY tests/Mcp ./tests/Mcp
COPY agent-kit/examples ./agent-kit/examples
COPY fixtures ./fixtures
COPY resources ./resources
COPY --from=browser-fonts /app/resources/library/fonts.lock.json ./resources/library/fonts.lock.json
COPY --from=browser-fonts /app/var/library-staging ./font-fixtures
