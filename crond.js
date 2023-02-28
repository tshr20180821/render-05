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
      } catch (ex1) {
        console.log(process.pid + ' ' + ex1.toString());
      }

      //require('https').request(options, (response) => {
      //  console.log(process.pid + ' HTTP STATUS CODE : ' + response.statusCode);
      //});

      console.log(process.pid + ' FINISH ' + __filename);
    },
    null,
    true,
    'Asia/Tokyo'
  );
  job.start();
} catch (ex) {
  console.log(process.pid + ' ' + ex.toString());
}
