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
                if ((new Date()).getMinutes() % 2 == 0) {
                    check_apt_update();
                } else {
                    check_npm_update();
                }
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

function check_apt_update() {
    new Promise(() => {
       try {
            logger.info('START check_apt_update');
            const mc = memjs.Client.create();
            var check_apt = '';
            mc.get('CHECK_APT', function (err, val) {
                if (err) {
                    logger.warn(err.stack);
                }
                if (val != null) {
                    logger.info('memcached hit CHECK_APT : ' + val);
                    return;
                }
                const dt = new Date();
                const datetime = dt.getFullYear() + '-' + ('0' + (dt.getMonth() + 1)).slice(-2) + '-' + ('0' + dt.getDate()).slice(-2) + ' ' +
                   ('0' + dt.getHours()).slice(-2) + ':' + ('0' + dt.getMinutes()).slice(-2);
                var stdout = execSync('apt-get update');
                stdout = execSync('apt-get -s upgrade | grep upgraded');
                check_apt = datetime + ' ' + stdout.toString();
                mc.set('CHECK_APT', check_apt, {
                    expires: 24 * 60 * 60
                }, function (err, _) {
                    if (err) {
                        logger.warn(err.stack);
                    } else {
                        logger.info('memcached set CHECK_APT : ' + check_apt);
                    }
                });
            });
        } catch (err) {
            logger.warn(err.stack);
        }
    });
}

function check_npm_update() {
    new Promise(() => {
       try {
            logger.info('START check_npm_update');
            const mc = memjs.Client.create();
            var check_npm = '';
            mc.get('CHECK_NPM', function (err, val) {
                if (err) {
                    logger.warn(err.stack);
                }
                if (val != null) {
                    logger.info('memcached hit CHECK_NPM : ' + val);
                    return;
                }
                const dt = new Date();
                const datetime = dt.getFullYear() + '-' + ('0' + (dt.getMonth() + 1)).slice(-2) + '-' + ('0' + dt.getDate()).slice(-2) + ' ' +
                   ('0' + dt.getHours()).slice(-2) + ':' + ('0' + dt.getMinutes()).slice(-2);
                var stdout = execSync('npm outdated');
                logger.info('check_npm_update length : ' + stdout.toString().length);
                check_npm = datetime + ' ' + stdout.toString();
                mc.set('CHECK_NPM', check_npm, {
                    expires: 24 * 60 * 60
                }, function (err, _) {
                    if (err) {
                        logger.warn(err.stack);
                    } else {
                        logger.info('memcached set CHECK_NPM : ' + check_npm);
                    }
                });
            });
        } catch (err) {
            logger.warn(err.stack);
        }
    });
}
