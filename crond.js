// package : cron sendmail

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
        require('https').request(options, (response) => {
          console.log(log_prefix + 'HTTP STATUS CODE : ' + response.statusCode + ' ' + options['hostname']);

          const send_mail_file = '/tmp/SEND_MAIL';
          const fs = require('fs');
          if (!fs.existsSync(send_mail_file)) {
            fs.closeSync(fs.openSync(send_mail_file, 'w'));
          }
          console.log(log_prefix + 'FILE UPDATE TIME : ' + fs.statSync(send_mail_file).mtime);
          const dt = new Date();
          if (response.statusCode != 200
              && process.env.MAIL_ADDRESS != undefined
              && (dt.getTime() - fs.statSync(send_mail_file).mtimeMs) > 5 * 60 * 1000
              ) {
            console.log(log_prefix + '5 minutes later');
            fs.utimes(send_mail_file, dt, dt);
            
            // send mail
          }
          /*
          if (response.statusCode != 200 && process.env.MAIL_ADDRESS != undefined) {
            const sendmail = require('sendmail')();
            sendmail({
              from: process.env.MAIL_ADDRESS,
              to: process.env.MAIL_ADDRESS,
              subject: 'HTTP STATUS CODE : ' + response.statusCode + ' ' + options['hostname'],
              text: 'HTTP STATUS CODE : ' + response.statusCode + ' ' + options['hostname'],
            }, function(err, reply) {
              console.log(log_prefix + err.toString());
              console.dir(reply);
            });
          }
          */
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
