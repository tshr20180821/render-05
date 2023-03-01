var CronJob = require('cron').CronJob;
try {
  var job = new CronJob(
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
      console.log(process.pid + ' START ' + __filename + ' ' + process.env.DEPLOY_DATETIME);
      
      try {
        require('https').request(options, (response) => {
          console.log(process.pid + ' HTTP STATUS CODE : ' + response.statusCode + ' ' + options['hostname']);
        }).end();
      } catch (err) {
        console.log(process.pid + ' ' + err.toString());
      }

      console.log(process.pid + ' FINISH ' + __filename);
    },
    null,
    true,
    'Asia/Tokyo'
  );
  job.start();
} catch (err) {
  console.log(process.pid + ' ' + err.toString());
}
