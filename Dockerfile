FROM node:18-bullseye

RUN mkdir -p /usr/src/app
WORKDIR /usr/src/app
COPY package.json ./
RUN npm install
COPY . .
EXPOSE 3000

CMD ["node","crond.js"]
