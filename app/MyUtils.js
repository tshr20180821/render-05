// package : nodemailer log4js

const https = require("https");

const log4js = require('log4js');
log4js.configure('log4js.json');

const logger = log4js.getLogger();
logger.level = 'debug';

if (process.env.DEPLOY_DATETIME != undefined) {
    logger.addContext("DEPLOY_DATETIME", process.env.DEPLOY_DATETIME);
} else {
    logger.addContext("DEPLOY_DATETIME", '');
}

class MyLog {
    _regex1;
    _regex2;

    constructor() {
        this._regex1 = /(.+) .+\/(.+?):(\d+)/;
        this._regex2 = /(\/)(.+?):(\d+)/;
    }

    info(message_) {
        this.#output('INFO', message_);
    }

    warn(message_) {
        this.#output('WARN', message_);
    }

    #output(level_, message_) {
        new Promise(() => {
            try {
                // console.log((new Error).stack);
                const target_line = (new Error()).stack.split("\n")[5].substring(7);
                var match = target_line.match(this._regex1);
                if (match == null) {
                    match = target_line.match(this._regex2);
                    match[1] = "-";
                }
                
                const dt = new Date();
                const datetime = dt.getFullYear() + '-' + ('0' + (dt.getMonth() + 1)).slice(-2) + '-' + ('0' + dt.getDate()).slice(-2) + ' ' +
                    ('0' + dt.getHours()).slice(-2) + ':' + ('0' + dt.getMinutes()).slice(-2) + ':' + ('0' + dt.getSeconds()).slice(-2) + '.' +
                    ('00' + dt.getMilliseconds()).slice(-3);
                const log_header = process.env.RENDER_EXTERNAL_HOSTNAME + ' ' + process.env.DEPLOY_DATETIME + ' ' +
                    process.pid + ' ' + level_ + ' ' + match[2] + ' ' + match[3] + ' [' + match[1] + ']';
                console.log(datetime + ' \x1b[35m' + log_header + '\x1b[0m ' + message_);
                const loggly_options = {
                    protocol: 'https:',
                    port: 443,
                    hostname: 'logs-01.loggly.com',
                    path: '/inputs/' + process.env.LOGGLY_TOKEN +
                        '/tag/' + process.env.RENDER_EXTERNAL_HOSTNAME + ',' + process.env.RENDER_EXTERNAL_HOSTNAME + '_' + process.env.DEPLOY_DATETIME + ',' + level_ + ',nodejs/',
                    method: 'POST',
                    headers: {
                        'content-type': 'text/plain; charset=utf-8',
                    }
                };
                loggly_options.agent = new https.Agent({
                    keepAlive: true
                });

                const request = https.request(loggly_options);
                request.write(datetime + ' ' + log_header + ' ' + message_);
                request.end();
            } catch (err) {
                console.warn(err.stack);
            }
        });
    }
}

module.exports.get_logger = function () {
    // return logger;
    return new MyLog();
}

module.exports.send_slack_message = function (message_) {
    const sleep_ms = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    const http_options = {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + process.env.SLACK_TOKEN,
            'Content-type': 'application/json'
        }
    };
    new Promise(() => {
        try {
            [process.env.SLACK_CHANNEL_01, process.env.SLACK_CHANNEL_02].forEach(channel => {
                const post_data = JSON.stringify({
                    text: message_,
                    channel: channel
                });
                const request = require('https').request('https://slack.com/api/chat.postMessage', http_options, response => {
                    logger.info('Slack Post Message Result : ' + response.statusCode);
                });
                request.write(post_data);
                request.end();
                sleep_ms(1000);
            });
        } catch (err) {
            console.warn(err.stack);
        }
    });
}

module.exports.send_mail = function (subject_, body_) {
    if (process.env.MAIL_ADDRESS == undefined) {
        logger.warn('UNSET ENV MAIL_ADDRESS');
        return;
    }

    if (process.env.SMTP_SERVER == undefined ||
        process.env.SMTP_USER == undefined ||
        process.env.SMTP_PASSWORD == undefined) {
        logger.warn('UNSET ENV SMTP PARAM');
        return;
    }

    const smtp_options = {
        host: process.env.SMTP_SERVER,
        port: 465,
        secure: true,
        auth: {
            user: process.env.SMTP_USER,
            pass: process.env.SMTP_PASSWORD,
        },
    };

    const mail = {
        from: process.env.SMTP_USER,
        to: process.env.MAIL_ADDRESS,
        subject: subject_,
        text: body_
    };

    (async () => {
        const smtp = require('nodemailer').createTransport(smtp_options);
        await smtp.sendMail(mail, function (err, info) {
            if (err) {
                logger.warn(err.stack);
            } else {
                logger.info(info.messageId);
            }
        });
    })();
}
