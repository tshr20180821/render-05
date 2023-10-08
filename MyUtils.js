// package : nodemailer log4js

const https = require("https");

const log4js = require('log4js');
log4js.configure('log4js.json');

const logger = log4js.getLogger();
logger.level = 'debug';

if (process.env.DEPLOY_DATETIME != undefined) {
  logger.addContext("DEPLOY_DATETIME", process.env.DEPLOY_DATETIME);
} else {
  logger.addContext("DEPLOY_DATETIME", '');
}

class MyLog {
  _regex;
  _loggly_options;
  _deploy_datetime; // unix time
  
  constructor() {
    this._regex = /(.+) .+\/(.+?):(\d+)/;

    this._loggly_options = {
      protocol: 'https:',
      port: 443,
      hostname: 'logs-01.loggly.com',
      path: '/inputs/' + process.env.LOGGLY_TOKEN
        + '/tag/' + process.env.RENDER_EXTERNAL_HOSTNAME + ',' + process.env.RENDER_EXTERNAL_HOSTNAME + '_' + process.env.DEPLOY_DATETIME + '/',
      method: 'POST',
      headers: {
        'content-type': 'text/plain; charset=utf-8',
      }
    };
    this._loggly_options.agent = new https.Agent({ keepAlive: true });
    
    var tmp = process.env.DEPLOY_DATETIME.match(/.{2}/g);
    this._deploy_datetime = (new Date(tmp[0] + tmp[1], Number(tmp[2]) - 1, tmp[3], tmp[4], tmp[5], tmp[6])).getTime();
  }
  
  info(message_) {
    this.#output('INFO', message_);
  }
  
  warn(message_) {
    this.#output('WARN', message_);
  }
  
  #output(level_, message_) {
    new Promise((resolve) => {
      const match = (new Error()).stack.split("\n")[3].substring(7).match(this._regex);
      
      const dt = new Date();
      const log_header = dt.getFullYear() + '-' + ('0' + (dt.getMonth() + 1)).slice(-2) + '-' + ('0' + dt.getDate()).slice(-2) + ' '
        + ('0' + dt.getHours()).slice(-2) + ':' +  ('0' + dt.getMinutes()).slice(-2) + ':' +  ('0' + dt.getSeconds()).slice(-2) + '.'
        + ('00' + dt.getMilliseconds()).slice(-3) + ' ' + process.env.RENDER_EXTERNAL_HOSTNAME + ' ' + process.env.DEPLOY_DATETIME + ' '
        + process.pid + ' ' + level_ + ' ' + match[2] + ' ' + match[3] + ' [' + match[1] + ']';
      const request = https.request(this._loggly_options);
      request.write(log_header + ' ' + message_);
      request.end();
      if (level_ != 'INFO' || dt.getTime() - this._deploy_datetime < 60 * 5) {
        console.log(log_header + ' ' + message_);
      }
      resolve();
    });
  }
}

module.exports.get_logger = function ()
{
  // return logger;
  return new MyLog();
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
