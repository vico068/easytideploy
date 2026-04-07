FROM node:20-alpine AS builder

WORKDIR /app
ENV NODE_OPTIONS="--max-old-space-size=8192" \
    NPM_CONFIG_AUDIT="false" \
    NPM_CONFIG_FUND="false" \
    NPM_CONFIG_UPDATE_NOTIFIER="false" \
    npm_config_jobs="1" \
    NPM_CONFIG_MAX_CONCURRENT=1 \
    UV_THREADPOOL_SIZE=1 \
    GOMAXPROCS=1

COPY package*.json ./
RUN npm ci --maxsockets 1 2>/dev/null || npm install --maxsockets 1

COPY . .

ARG BUILD_COMMAND="npm run build"
RUN if [ -n "$BUILD_COMMAND" ]; then sh -c "$BUILD_COMMAND"; fi

# Aggregate build output — try common output dirs in order of preference
RUN mkdir -p /output && \
    ([ -d /app/dist ]   && cp -r /app/dist/.   /output/ || true) && \
    ([ -d /app/build ]  && cp -r /app/build/.  /output/ || true) && \
    ([ -d /app/out ]    && cp -r /app/out/.    /output/ || true) && \
    ([ -d /app/public ] && cp -r /app/public/. /output/ || true) && \
    ls /output/ | head -5

FROM nginx:alpine
COPY --from=builder /output /usr/share/nginx/html
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
