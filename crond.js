// package : cron nodemailer

const log_prefix = process.env.DEPLOY_DATETIME + ' ' + process.pid + ' ';
const CronJob = require('cron').CronJob;
try {
  const job = new CronJob(
    '0 * * * * *',
    function() {
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
      
      console.log(log_prefix + 'START ' + __filename);
      try {
        const fs = require('fs');
        const stop_file = '/tmp/STOP_FILE';
        if (fs.existsSync(stop_file)) {
          console.log(log_prefix + 'STOP FILE EXISTS');
          console.log(log_prefix + 'FINISH ' + __filename);
          return;
        }
        var data_buffer = [];
        require('https').request(http_options, (res) => {
          res.on('data', (chunk) => {
            data_buffer.push(chunk);
          });
          res.on('end', () => {
            console.log(log_prefix + 'RESPONSE BODY : ' + Buffer.concat(data_buffer));
            var num = Number(Buffer.concat(data_buffer));
            if (!Number.isNaN(num) && Number(process.env.DEPLOY_DATETIME) < num) {
              console.log(log_prefix + 'MAKE STOP FILE');
              fs.closeSync(fs.openSync(stop_file, 'w'));
            }
          });
          
          console.log(log_prefix + 'HTTP STATUS CODE : ' + res.statusCode + ' ' + http_options['hostname']);

          const send_mail_file = '/tmp/SEND_MAIL';
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
              'HTTP STATUS CODE : ' + res.statusCode + ' ' + http_options['hostname'],
              'HTTP STATUS CODE : ' + res.statusCode + ' ' + http_options['hostname']);
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
  console.log(log_prefix + 'START job.start()');
  job.start();
  console.log(log_prefix + 'FINISH job.start()');
} catch (err) {
  console.log(log_prefix + err.toString());
}

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
          console.log(log_prefix + err.toString());
        }
      });
      console.log(log_prefix + ' Send Mail Result : ' + result);
    } catch (err) {
      console.log(log_prefix + err.toString());
    }
  })();
}
