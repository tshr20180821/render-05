FROM node:18-bullseye

RUN apt-get update \
 && apt-get -y upgrade \
 && apt-get -y install apache2 php \
 && apt-get clean
 
RUN mkdir -p /usr/src/app
WORKDIR /usr/src/app
COPY package.json ./
RUN npm install \
 && npm update -g
COPY . .
EXPOSE 3000

RUN php --version
RUN systemctl restart apache2

CMD ["node","crond.js"]
