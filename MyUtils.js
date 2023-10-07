// package : nodemailer log4js

const log4js = require('log4js');
log4js.configure('log4js.json');

const logger = log4js.getLogger();
logger.level = 'debug';

if (process.env.DEPLOY_DATETIME != undefined) {
  logger.addContext("DEPLOY_DATETIME", process.env.DEPLOY_DATETIME);
} else {
  logger.addContext("DEPLOY_DATETIME", '');
}

module.exports.get_logger = function ()
{
  return logger;
}

module.exports = class MyLog {
  request = null;
  constructor() {
      this.request = require('https').request('https://logs-01.loggly.com/inputs/' + process.env.LOGGLY_TOKEN
                                         + '/tag/' + process.env.RENDER_EXTERNAL_HOSTNAME + ',' + process.env.RENDER_EXTERNAL_HOSTNAME + '_' + process.env.DEPLOY_DATETIME + '/',
                                         {
                                           method: 'POST',
                                           headers: {
                                             'content-type': 'text/plain; charset=utf-8',
                                           }
                                         });
  }
  
  info(message_) {
    this.request.write(message_);
    this.request.end();
  }
  
  warn(message_) {
    this.request.write(message_);
    this.request.end();
  }
}

module.exports.send_slack_message = function (message_)
{
  const sleep_ms = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  
  const http_options = {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer ' + process.env.SLACK_TOKEN,
      'Content-type': 'application/json'
    }
  };
  
  [process.env.SLACK_CHANNEL_01, process.env.SLACK_CHANNEL_02].forEach(channel => {
    var post_data = JSON.stringify({
      text: message_,
      channel: channel
    });
    var request = require('https').request('https://slack.com/api/chat.postMessage', http_options, response => {
      logger.info('Slack Post Message Result : ' + response.statusCode);
    });
    request.write(post_data);
    request.end();
    sleep_ms(1000);
  });
}

module.exports.send_mail = function (subject_, body_)
{
  if (process.env.MAIL_ADDRESS == undefined) {
    logger.warn('UNSET ENV MAIL_ADDRESS');
    return;
  }
  
  if (process.env.SMTP_SERVER == undefined
     || process.env.SMTP_USER == undefined
     || process.env.SMTP_PASSWORD == undefined) {
    logger.warn('UNSET ENV SMTP PARAM');
    return;
  }
  
  const smtp_options = {
    host: process.env.SMTP_SERVER,
    port: 465,
    secure: true,
    auth: {
      user: process.env.SMTP_USER,
      pass: process.env.SMTP_PASSWORD,
    },
  };

  const mail = {
    from: process.env.SMTP_USER,
    to: process.env.MAIL_ADDRESS,
    subject: subject_,
    text: body_
  };

  (async () => {
    const smtp = require('nodemailer').createTransport(smtp_options);
    const result = await smtp.sendMail(mail, function(err, info) {
      if (err) {
        logger.warn(err.toString());
      } else {
        logger.info(info.messageId);
      }
    });
  })();
}
