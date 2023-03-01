var CronJob = require('cron').CronJob;
try {
  var job = new CronJob(
    '0 * * * * *',
    function() {
      let options = {
        hostname: process.env.RENDER_EXTERNAL_HOSTNAME,
        port: 443,
        path: '/auth/crond.php',
        // path: '/index.html',
        method: 'GET',
        headers: {
          'Authorization': 'Basic ' + Buffer.from(process.env.BASIC_USER + ':' + process.env.BASIC_PASSWORD).toString('base64'),
          'User-Agent': 'node-cron ' + process.pid + ' ' + process.env.BUILD_DATETIME,
          'X-Build-DateTime': process.env.BUILD_DATETIME
        }
      };
      console.log(process.pid + ' START ' + __filename + ' ' + process.env.BUILD_DATETIME);
      try {
        require('https').request(options).end();
      } catch (err) {
        console.log(process.pid + ' ' + err.toString());
      }

      try {
        console.log(process.pid + ' CHECKPOINT 010');
        require('https').request(options, (response) => {
          console.log(process.pid + ' HTTP STATUS CODE : ' + response.statusCode);
        }).end();
        console.log(process.pid + ' CHECKPOINT 020');
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
