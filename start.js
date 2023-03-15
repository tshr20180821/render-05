// package : log4js

const log4js = require('log4js');
log4js.configure('log4js.json');
        
const logger = log4js.getLogger();
logger.level = 'debug';
logger.addContext("DEPLOY_DATETIME", process.env.DEPLOY_DATETIME);

logger.info('START');

const mu = require('./MyUtils.js');

var message = process.env.RENDER_EXTERNAL_HOSTNAME + ' START ' + process.env.DEPLOY_DATETIME;
mu.send_mail(message, message);

logger.info('FINISH');
