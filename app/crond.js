// package : cron

const mu = require('./MyUtils.js');
const logger = mu.get_logger();

const https = require("https");
const url = 'https://' + process.env.RENDER_EXTERNAL_HOSTNAME + '/auth/crond.php';
// const fs = require('fs');
const {
    execSync
} = require('child_process');
const memjs = require('memjs');

const CronJob = require('cron').CronJob;

try {
    const job = new CronJob(
        '0 * * * * *',
        function () {
            logger.info('START');

            try {
                var http_options = {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Basic ' + Buffer.from(process.env.BASIC_USER + ':' + process.env.BASIC_PASSWORD).toString('base64'),
                        'User-Agent': 'cron ' + process.env.DEPLOY_DATETIME + ' ' + process.pid,
                        'X-Deploy-DateTime': process.env.DEPLOY_DATETIME
                    }
                };
                http_options.agent = new https.Agent({
                    keepAlive: true
                });

                var data_buffer = [];
                https.request(url, http_options, (res) => {
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
                    res.on('error', (err) => {
                        logger.warn(err.toString());
                    });

                    logger.info('HTTP STATUS CODE : ' + res.statusCode + ' ' + process.env.RENDER_EXTERNAL_HOSTNAME);

                    if (res.statusCode != 200) {
                        // https://process.env.RENDER_EXTERNAL_HOSTNAME/cdn-cgi/trace
                        mu.send_slack_message('HTTP STATUS CODE : ' + res.statusCode + ' ' + process.env.RENDER_EXTERNAL_HOSTNAME);
                    }
                }).end();
                check_package_update();
            } catch (err) {
                logger.warn(err.stack);
            }
            // global.gc();
            const memory_usage = process.memoryUsage();
            var message = 'FINISH Heap Total : ' +
                Math.floor(memory_usage.heapTotal / 1024).toLocaleString() +
                'KB Used : ' +
                Math.floor(memory_usage.heapUsed / 1024).toLocaleString() + 'KB';
            logger.info(message);
        },
        null,
        true,
        'Asia/Tokyo'
    );
    job.start();
} catch (err) {
    logger.warn(err.stack);
}

function check_package_update() {
    console.log('CHECK POINT 005');
    new Promise((resolve) => {
       try {
            console.log('CHECK POINT 010');
            const mc = memjs.Client.create();
            console.log('CHECK POINT 020');
            var rc = 0;
            var check_apt = '';
            console.log('CHECK POINT 030');
            mc.get('CHECK_APT', function (err1, val) {
                console.log('CHECK POINT 040');
                if (val == null) {
                    console.log('CHECK POINT 050');
                    rc = -1;
                } else {
                    console.log('CHECK POINT 060 ' + val);
                    return;
                }
                console.log('CHECK POINT 070');
                var stdout = execSync('apt-get update');
                console.log('CHECK POINT 080');
                stdout = execSync('apt-get -s upgrade | grep upgraded');
                check_apt = stdout.toString();
                console.log(check_apt);
                mc.set('CHECK_APT', check_apt, {
                    expires: 24 * 60 * 60
                }, function (err2, rc2) {
                    console.log('CHECK POINT 090');
                });
            });
            console.log('CHECK POINT 100');
        } catch (err) {
            console.log('CHECK POINT 110');
            // logger.warn(err.stack);
            console.log(err.stack);
            console.log('CHECK POINT 120');
        }
        console.log('CHECK POINT 130');
    });
}
