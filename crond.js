// package : cron

const mu = require('./MyUtils.js');
const logger = mu.get_logger();

const CronJob = require('cron').CronJob;
try {
  const job = new CronJob(
    '0 * * * * *',
    function() {
      logger.info('START');
      
      try {
        let http_options = {
          hostname: process.env.RENDER_EXTERNAL_HOSTNAME,
          port: 443,
          path: '/auth/crond.php',
          method: 'GET',
          headers: {
            'Authorization': 'Basic ' + Buffer.from(process.env.BASIC_USER + ':' + process.env.BASIC_PASSWORD).toString('base64'),
            'User-Agent': 'cron ' + process.env.DEPLOY_DATETIME + ' ' + process.pid,
            'X-Deploy-DateTime': process.env.DEPLOY_DATETIME
          }
        };
        
        var data_buffer = [];
        require('https').request(http_options, (res) => {
          res.on('data', (chunk) => {
            data_buffer.push(chunk);
          });
          res.on('end', () => {
            logger.info('RESPONSE BODY : ' + Buffer.concat(data_buffer).toString().substring(0, 100));
            var num = Number(Buffer.concat(data_buffer));
            if (!Number.isNaN(num) && Number(process.env.DEPLOY_DATETIME) < num) {
              logger.warn('CRON STOP');
              this.stop();
            }
          });
          
          logger.info('HTTP STATUS CODE : ' + res.statusCode + ' ' + http_options['hostname']);

          const fs = require('fs');
          const send_mail_file = '/tmp/SEND_MAIL';
          if (!fs.existsSync(send_mail_file)) {
            fs.closeSync(fs.openSync(send_mail_file, 'w'));
          }
          logger.info('SEND MAIL FILE UPDATE TIME : ' + fs.statSync(send_mail_file).mtime);
          const dt = new Date();
          if (res.statusCode != 200
              && process.env.MAIL_ADDRESS != undefined
              && (dt.getTime() - fs.statSync(send_mail_file).mtimeMs) > 5 * 60 * 1000
              ) {
            fs.utimes(send_mail_file, dt, dt);
            
            mu.send_mail(
              'HTTP STATUS CODE : ' + res.statusCode + ' ' + http_options['hostname'],
              'HTTP STATUS CODE : ' + res.statusCode + ' ' + http_options['hostname']);
          }
          
          const check_apt_file = '/tmp/CHECK_APT';
          if (!fs.existsSync(check_apt_file)) {
            fs.closeSync(fs.openSync(check_apt_file, 'w'));
          }
          logger.info('CHECK APT FILE UPDATE TIME : ' + fs.statSync(check_apt_file).mtime);
          if ((dt.getTime() - fs.statSync(check_apt_file).mtimeMs) > 5 * 60 * 1000) {
            fs.utimes(check_apt_file, dt, dt);
            const { execSync } = require('child_process');
            const stdout = execSync('apt-get update && apt-get -s upgrade');
            logger.info(stdout.toString());
          }
        }).end();
      } catch (err) {
        logger.warn(err.toString());
      }
      global.gc();
      const memory_usage = process.memoryUsage();
      logger.info('FINISH Heap Total : '
                  + Math.floor(memory_usage.heapTotal / 1024).toLocaleString()
                  + 'KB Used : '
                  + Math.floor(memory_usage.heapUsed / 1024).toLocaleString() + 'KB');
    },
    null,
    true,
    'Asia/Tokyo'
  );
  job.start();
} catch (err) {
  logger.warn(err.toString());
}
