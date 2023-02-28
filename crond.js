/*
require('node-cron').schedule('* * * * *', function() {
  let options = {
    hostname: process.env.SERVER_NAME,
    port: 443,
    // path: '/auth/crond.php',
    path: '/index.html',
    method: 'GET',
    headers: {
      'Authorization': 'Basic ' + Buffer.from(process.env.BASIC_USER + ':' + process.env.BASIC_PASSWORD).toString('base64'),
      'User-Agent': 'node-cron ' + (new Date()).toString() + ' ' + process.pid
    }
  };
  console.log(process.pid + " START " + __filename);
  // console.error(process.env.SERVER_NAME);
  require('https').request(options).end();
  console.log(process.pid + " FINISH " + __filename);
}, {
  scheduled: true,
  timezone: 'Asia/Tokyo'
}).start();
*/

var CronJob = require('cron').CronJob;
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
    // require('https').request(options).end();
    require('https').request(options, function (error, response, body) {
      console.log(process.pid + ' HTTP STATUS CODE : ' + response.statusCode);
    }).end();
    console.log(process.pid + ' FINISH ' + __filename);
  },
  null,
  true,
  'Asia/Tokyo'
);
job.start();
