require('node-cron').schedule('* * * * *', function() {
  let options = {
    hostname: 'dummy.local',
    port: 443,
    path: '/auth/crond.php',
    method: 'GET',
    headers: {
      'Authorization': 'Basic ' + Buffer.from(process.env.BASIC_USER + ':' + process.env.BASIC_PASSWORD).toString('base64'),
      'User-Agent': 'node-cron'
    }
  };
  console.error('test1');
  console.error('test2');
  console.error(os.hostname());
  console.error('test3');
  // require('https').request(options).end();
}, {
  scheduled: true,
  timezone: 'Asia/Tokyo'
}).start();
