// package : nodemailer log4js

const logger = require('log4js').getLogger();
logger.level = 'debug';
const log_prefix = process.env.DEPLOY_DATETIME + ' ' + process.pid + ' ';

logger.info(log_prefix + 'START ' + __filename);

send_mail(process.env.RENDER_EXTERNAL_HOSTNAME + ' START ' + process.env.DEPLOY_DATETIME,
          process.env.RENDER_EXTERNAL_HOSTNAME + ' START ' + process.env.DEPLOY_DATETIME);

logger.info(log_prefix + 'FINISH ' + __filename);

function send_mail(subject_, body_)
{
  if (process.env.MAIL_ADDRESS == undefined) {
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
    from: process.env.MAIL_ADDRESS,
    to: process.env.MAIL_ADDRESS,
    subject: subject_,
    text: body_
  };

  (async () => {
    try {
      const smtp = require('nodemailer').createTransport(smtp_options);
      const result = await smtp.sendMail(mail, function(err, info) {
        if (err) {
          logger.warn(log_prefix + err.toString());
        }
      });
      logger.info(log_prefix + ' Send Mail Result : ' + result);
    } catch (err) {
      logger.warn(log_prefix + err.toString());
    }
  })();
}
