FROM mwaeckerlin/nodejs-build AS app-build
WORKDIR /build
COPY --chown=${BUILD_USER} package*.json ./
RUN npm install --no-audit --no-fund

COPY --chown=${BUILD_USER} parlwin/ ./parlwin/
RUN npm run build:app \
    && rm -rf node_modules

FROM mwaeckerlin/nextcloud:php-fpm AS php-fpm
COPY --from=app-build /build/parlwin /app/custom_apps/parlwin

FROM mwaeckerlin/nextcloud:nginx AS nginx
COPY --from=app-build /build/parlwin /app/custom_apps/parlwin
