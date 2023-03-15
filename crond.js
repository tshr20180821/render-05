// package : cron log4js

const log4js = require('log4js');
log4js.configure('log4js.json');
        
const logger = log4js.getLogger();
logger.level = 'debug';
logger.addContext('DEPLOY_DATETIME', process.env.DEPLOY_DATETIME);

const mu = require('./MyUtils.js');

const CronJob = require('cron').CronJob;
try {
  const job = new CronJob(
    '0 * * * * *',
    function() {
      logger.info('START');
      
      try {
        let http_options = {
          hostname: process.env.RENDER_EXTERNAL_HOSTNAME,
          port: 443,
          path: '/auth/crond.php',
          method: 'GET',
          headers: {
            'Authorization': 'Basic ' + Buffer.from(process.env.BASIC_USER + ':' + process.env.BASIC_PASSWORD).toString('base64'),
            'User-Agent': 'cron ' + process.pid + ' ' + process.env.DEPLOY_DATETIME,
            'X-Deploy-DateTime': process.env.DEPLOY_DATETIME
          }
        };
        
        var data_buffer = [];
        require('https').request(http_options, (res) => {
          res.on('data', (chunk) => {
            data_buffer.push(chunk);
          });
          res.on('end', () => {
            logger.info('RESPONSE BODY : ' + Buffer.concat(data_buffer).toString().substring(0, 100));
            var num = Number(Buffer.concat(data_buffer));
            if (!Number.isNaN(num) && Number(process.env.DEPLOY_DATETIME) < num) {
              logger.warn('CRON STOP');
              this.stop();
            }
          });
          
          logger.info('HTTP STATUS CODE : ' + res.statusCode + ' ' + http_options['hostname']);

          const fs = require('fs');
          const send_mail_file = '/tmp/SEND_MAIL';
          if (!fs.existsSync(send_mail_file)) {
            fs.closeSync(fs.openSync(send_mail_file, 'w'));
          }
          logger.info('FILE UPDATE TIME : ' + fs.statSync(send_mail_file).mtime);
          const dt = new Date();
          if (res.statusCode != 200
              && process.env.MAIL_ADDRESS != undefined
              && (dt.getTime() - fs.statSync(send_mail_file).mtimeMs) > 5 * 60 * 1000
              ) {
            fs.utimes(send_mail_file, dt, dt);
            
            mu.send_mail(
              'HTTP STATUS CODE : ' + res.statusCode + ' ' + http_options['hostname'],
              'HTTP STATUS CODE : ' + res.statusCode + ' ' + http_options['hostname']);
          }
        }).end();
      } catch (err) {
        logger.warn(err.toString());
      }
      logger.info('FINISH');
    },
    null,
    true,
    'Asia/Tokyo'
  );
  job.start();
} catch (err) {
  logger.warn(err.toString());
}

/*
function send_mail(subject_, body_)
{
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
*/
