# https://github.com/puppeteer/puppeteer/pkgs/container/puppeteer
FROM ghcr.io/puppeteer/puppeteer:24.10.1

# https://betterstack.com/community/guides/scaling-nodejs/dockerize-nodejs/

WORKDIR /build

COPY package*.json ./

RUN npm install-clean --omit=dev && npm cache clean --force

COPY . .

# Document the port that may need to be published
EXPOSE 3000

# Start the application
CMD ["node", "app.js"]
