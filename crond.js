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
      
      try {
        require('https').request(options, (response) => {
          console.log(log_prefix + 'HTTP STATUS CODE : ' + response.statusCode + ' ' + options['hostname']);
          
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
        }).end();
      } catch (err) {
        console.log(log_prefix + err.toString());
      }
    },
    null,
    true,
    'Asia/Tokyo'
  );
  console.log(log_prefix + 'START ' + __filename );
  job.start();
  console.log(log_prefix + 'FINISH ' + __filename);
} catch (err) {
  console.log(log_prefix + err.toString());
}
