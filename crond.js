require('node-cron').schedule('* * * * *', function() {
  let options = {
    hostname: process.env.SERVER_NAME,
    port: 443,
    path: '/auth/crond.php',
    method: 'GET',
    headers: {
      'Authorization': 'Basic ' + Buffer.from(process.env.BASIC_USER + ':' + process.env.BASIC_PASSWORD).toString('base64'),
      'User-Agent': 'node-cron'
    }
  };
  console.error('test1');
  console.error(process.env.SERVER_NAME);
  console.error(require('os').hostname());
  console.error('test2');
  require('https').request(options).end();
}, {
  scheduled: true,
  timezone: 'Asia/Tokyo'
}).start();
