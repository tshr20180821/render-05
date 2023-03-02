// package : cron nodemailer

const log_prefix = process.env.DEPLOY_DATETIME + ' ' + process.pid + ' ';
const CronJob = require('cron').CronJob;
try {
  const job = new CronJob(
    '0 * * * * *',
    function() {
      let options = {
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
      
      console.log(log_prefix + 'START ' + __filename );
      try {
        var data_buffer = [];
        require('https').request(options, (res) => {
          res.on('data', (chunk) => {
            data_buffer.push(chunk);
          });
          res.on('end', () => {
            console.log(log_prefix + 'RESPONSE BODY : ' + Buffer.concat(data_buffer));
          });
          
          console.log(log_prefix + 'HTTP STATUS CODE : ' + res.statusCode + ' ' + options['hostname']);

          const send_mail_file = '/tmp/SEND_MAIL';
          const fs = require('fs');
          if (!fs.existsSync(send_mail_file)) {
            fs.closeSync(fs.openSync(send_mail_file, 'w'));
          }
          console.log(log_prefix + 'FILE UPDATE TIME : ' + fs.statSync(send_mail_file).mtime);
          const dt = new Date();
          if (res.statusCode != 200
              && process.env.MAIL_ADDRESS != undefined
              && (dt.getTime() - fs.statSync(send_mail_file).mtimeMs) > 5 * 60 * 1000
              ) {
            fs.utimes(send_mail_file, dt, dt);
            
            send_mail(
              'HTTP STATUS CODE : ' + res.statusCode + ' ' + options['hostname'],
              'HTTP STATUS CODE : ' + res.statusCode + ' ' + options['hostname']);
          }
        }).end();
      } catch (err) {
        console.log(log_prefix + err.toString());
      }
      console.log(log_prefix + 'FINISH ' + __filename);
    },
    null,
    true,
    'Asia/Tokyo'
  );
  job.start();
} catch (err) {
  console.log(log_prefix + err.toString());
}

function send_mail(subject_, body_)
{
  const options = {
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
      const smtp = require('nodemailer').createTransport(options);
      const result = await smtp.sendMail(mail, function(err, info) {
        if (err) {
          console.log(log_prefix + err.toString());
        }
      });
      console.log(log_prefix + ' Send Mail Result : ' + result);
    } catch (err) {
      console.log(log_prefix + err.toString());
    }
  })();
}
