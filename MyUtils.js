const log4js = require('log4js');
log4js.configure('log4js.json');

const logger = log4js.getLogger();
logger.level = 'debug';

if (process.env.MAIL_ADDRESS != undefined) {
  logger.addContext("DEPLOY_DATETIME", process.env.DEPLOY_DATETIME);
} else {
  logger.addContext("DEPLOY_DATETIME", '');
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
