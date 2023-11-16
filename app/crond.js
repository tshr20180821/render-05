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
            /*
            console.log('CHECK POINT 010');
            const mc1 = memjs.Client.create();
            console.log('CHECK POINT 015');
            mc1.get('CHECK_APT', function (err, val) {
                try {
                    console.log('CHECK POINT 020');
                    if (val == null) {
                        console.log('CHECK POINT 030');
                        const mc2 = memjs.Client.create();
                        console.log('CHECK POINT 035');
                        mc2.set('CHECK_APT', 'dummy', {
                            expires: 10 * 60
                        }, function (err2, rc) {
                            try {
                                console.log('CHECK POINT 040');
                                // logger.info('memcached set : ' + rc);
                                console.warn(rc);
                                console.log('CHECK POINT 050');
                            } catch (err4) {
                                console.log('CHECK POINT 051');
                                conrole.log(err4.stack);
                                console.log('CHECK POINT 052');
                            }
                        });
                        console.log('CHECK POINT 060');
                        var stdout = execSync('apt-get update');
                        console.log('CHECK POINT 070');
                        // logger.info(stdout.toString());
                        console.log(stdout.toString());
                        console.log('CHECK POINT 080');
                        stdout = execSync('apt-get -s upgrade | grep upgraded');
                        console.log('CHECK POINT 090');
                        // logger.info(stdout.toString());
                        console.log(stdout.toString());
                        console.log('CHECK POINT 100');

                        const dt = new Date();
                        const datetime = dt.getFullYear() + '-' + ('0' + (dt.getMonth() + 1)).slice(-2) + '-' + ('0' + dt.getDate()).slice(-2) + ' ' +
                            ('0' + dt.getHours()).slice(-2) + ':' + ('0' + dt.getMinutes()).slice(-2);
                        console.log('CHECK POINT 110');
                        const mc3 = memjs.Client.create();
                        console.log('CHECK POINT 115');
                        mc3.set('CHECK_APT', datetime + ' ' + stdout.toString(), {
                            expires: 24 * 60 * 60
                        }, function (err2, rc) {
                            try {
                                console.log('CHECK POINT 120');
                                // logger.info('memcached set : ' + rc);
                                console.warn(rc);
                                console.log('CHECK POINT 130');
                            } catch (err5) {
                                console.log('CHECK POINT 131');
                                conrole.log(err5.stack);
                                console.log('CHECK POINT 132');
                            }
                        });
                        console.log('CHECK POINT 140');
                    }
                } catch (err3) {
                    console.log('CHECK POINT 150');
                    conrole.log(err3.stack);
                    console.log('CHECK POINT 160');
                }
            });
            console.log('CHECK POINT 150');
            */

            console.log('CHECK POINT 010');
            const mc = memjs.Client.create();
            console.log('CHECK POINT 020');
            var rc = 0;
            var check_apt = '';
            console.log('CHECK POINT 030');
            var promise = new Promise((resolve) => {
                mc.get('CHECK_APT', function (err1, val) {
                    console.log('CHECK POINT 040');
                    if (val == null) {
                        console.log('CHECK POINT 050');
                        rc = -1;
                    } else {
                        console.log('CHECK POINT 060');
                        check_apt = val;
                        rc = 1;
                    }
                    console.log('CHECK POINT 070');
                });
            });
            console.log('CHECK POINT 080 ' + rc);

            if (rc == -1) {
                console.log('CHECK POINT 090 ' + rc);
                mc.set('CHECK_APT', 'dummy', {
                    expires: 10 * 60
                }, function (err2, rc2) {
                    console.log('CHECK POINT 100');
                });
            } else if (rc == 1) {
                console.log('CHECK POINT 110');
                return;
            }
            console.log('CHECK POINT 120');

            var stdout = execSync('apt-get update');
            console.log('CHECK POINT 130');
            stdout = execSync('apt-get -s upgrade | grep upgraded');
            console.log(stdout.toString());
            console.log('CHECK POINT 140');
            mc.set('CHECK_APT', stdout.toString(), {
                expires: 24 * 60 * 60
            }, function (err3, rc3) {
                console.log('CHECK POINT 150');
            });
            console.log('CHECK POINT 160');

            /*
            const check_apt_file = '/tmp/CHECK_APT';
            if (!fs.existsSync(check_apt_file)) {
                 const fd = fs.openSync(check_apt_file, 'w', 0o666);
                 fs.writeSync(fd, 'uchecked');
                 fs.closeSync(fd);
            }
            logger.info('CHECK APT FILE UPDATE TIME : ' + fs.statSync(check_apt_file).mtime);
            if (((new Date()).getTime() - fs.statSync(check_apt_file).mtimeMs) > 24 * 60 * 60 * 1000) {
                 var stdout = execSync('apt-get update');
                 logger.info(stdout.toString());
                 stdout = execSync('apt-get -s upgrade | grep upgraded');
                 logger.info(stdout.toString());
                 const fd = fs.openSync(check_apt_file, 'w');
                 fs.writeSync(fd, stdout.toString());
                 fs.closeSync(fd);
            }
            */
        } catch (err) {
            console.log('CHECK POINT 160');
            // logger.warn(err.stack);
            console.log(err.stack);
            console.log('CHECK POINT 170');
        }
        resolve();
    });
    console.log('CHECK POINT 180');
}
