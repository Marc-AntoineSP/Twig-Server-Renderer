FROM node:24-trixie-slim@sha256:43b9ba1142b8dcf33fa8f922ca1be85714adc444e0586c7cf7726daaeabc1bea
WORKDIR /toolchain
COPY workers/build/package.json workers/build/package-lock.json ./
RUN npm ci --omit=dev
COPY workers/build/server.mjs workers/build/build.mjs ./
USER node
CMD ["node", "--max-old-space-size=64", "server.mjs"]
