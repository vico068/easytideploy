FROM node:20-alpine AS builder

WORKDIR /app
COPY package*.json ./
RUN npm ci 2>/dev/null || npm install

COPY . .

ARG BUILD_COMMAND="npm run build"
RUN if [ -n "$BUILD_COMMAND" ]; then sh -c "$BUILD_COMMAND"; fi

FROM nginx:alpine
COPY --from=builder /app/dist /usr/share/nginx/html
COPY --from=builder /app/build /usr/share/nginx/html 2>/dev/null || true
COPY --from=builder /app/public /usr/share/nginx/html 2>/dev/null || true
COPY --from=builder /app/out /usr/share/nginx/html 2>/dev/null || true

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
