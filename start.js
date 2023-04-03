// package :

const mu = require('./MyUtils.js');
const logger = mu.get_logger();

logger.info('START');

var message = process.env.RENDER_EXTERNAL_HOSTNAME + ' START ' + process.env.DEPLOY_DATETIME;
// mu.send_mail(message, message);
mu.send_slack_message(message);

logger.info('FINISH');
