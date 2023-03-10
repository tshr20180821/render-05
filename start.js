// package : nodemailer log4js

const log4js = require('log4js');
log4js.configure('log4js.json');
        
const logger = log4js.getLogger();
logger.level = 'debug';
logger.addContext("DEPLOY_DATETIME", process.env.DEPLOY_DATETIME);
const log_prefix = '';

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
    from: process.env.SMTP_USER,
    to: process.env.MAIL_ADDRESS,
    subject: subject_,
    text: body_
  };

  (async () => {
    const smtp = require('nodemailer').createTransport(smtp_options);
    const result = await smtp.sendMail(mail, function(err, info) {
      if (err) {
        logger.warn(log_prefix + err.toString());
      } else {
        logger.info(log_prefix + info.messageId);
      }
    });
  })();
}
