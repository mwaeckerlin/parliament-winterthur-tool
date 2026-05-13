FROM mwaeckerlin/nodejs-build AS app-build
WORKDIR /app
COPY --chown=${BUILD_USER} parlwin/package*.json ./
RUN npm install --no-audit --no-fund

COPY --chown=${BUILD_USER} parlwin/ ./
RUN npm run build \
    && rm -rf node_modules

FROM mwaeckerlin/nextcloud:php-fpm AS php-fpm
COPY --from=app-build /app /app/custom_apps/parlwin

FROM mwaeckerlin/nextcloud:nginx AS nginx
COPY docker/nginx/default.conf /etc/nginx.template/server.d/default.conf
COPY --from=app-build /app /app/custom_apps/parlwin
