// cron sendmail

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
      console.log(process.env.DEPLOY_DATETIME + ' ' + process.pid + ' START ' + __filename + ' ' + process.env.DEPLOY_DATETIME);
      
      try {
        require('https').request(options, (response) => {
          console.log(process.env.DEPLOY_DATETIME + ' ' + process.pid + ' HTTP STATUS CODE : ' + response.statusCode + ' ' + options['hostname']);
          
          if (response.statusCode != 200 && process.env.MAIL_ADDRESS != undefined) {
            const sendmail = require('sendmail')();
            sendmail({
              from: process.env.MAIL_ADDRESS,
              to: process.env.MAIL_ADDRESS,
              subject: 'HTTP STATUS CODE : ' + response.statusCode + ' ' + options['hostname'],
              text: 'HTTP STATUS CODE : ' + response.statusCode + ' ' + options['hostname'],
            }, function(err, reply) {
              console.log(process.env.DEPLOY_DATETIME + ' ' + process.pid + ' ' + err.toString());
              console.dir(reply);
            });
          }  
        }).end();
      } catch (err) {
        console.log(process.env.DEPLOY_DATETIME + ' ' + process.pid + ' ' + err.toString());
      }

      console.log(process.env.DEPLOY_DATETIME + ' ' + process.pid + ' FINISH ' + __filename);
    },
    null,
    true,
    'Asia/Tokyo'
  );
  job.start();
} catch (err) {
  console.log(process.env.DEPLOY_DATETIME + ' ' + process.pid + ' ' + err.toString());
}
