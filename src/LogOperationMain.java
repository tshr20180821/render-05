import java.io.IOException;
import java.lang.management.ManagementFactory;
import java.net.BindException;
import java.net.ServerSocket;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.logging.Level;
import java.util.logging.Logger;

public final class LogOperationMain {
    static final int LOCK_PORT = 45678;
    static Logger _logger;

    public static void main(String[] args) {
        System.setProperty("java.util.logging.SimpleFormatter.format",
                "%1$tY-%1$tm-%1$td %1$tH:%1$tM:%1$tS.%1$tL %4$s %2$s %5$s%6$s%n");
        Logger logger = Logger.getLogger(Logger.GLOBAL_LOGGER_NAME);
        logger.setLevel(Level.ALL);
        _logger = logger;
        String pid_host = ManagementFactory.getRuntimeMXBean().getName();
        String start_datetime = LocalDateTime.now().format(DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss"));
        logger.info("START " + pid_host + " " + System.getProperty("user.name"));
        ServerSocket ss;
        try {
            ss = new ServerSocket(LOCK_PORT);
            send_slack_message("Socket Open");
            logger.info("availableProcessors : " + (Runtime.getRuntime()).availableProcessors());
            LogOperation logOperation = LogOperation.getInstance(logger);
            int i = 0;
            for (;;) {
                int rc = logOperation.execute();
                if (rc == 0) {
                    Thread.sleep(1000);
                    if (++i % 60 == 0) {
                        i = 0;
                        var sb = new StringBuilder(4);
                        sb.append("START AT " + start_datetime);
                        sb.append(" Free : " + Runtime.getRuntime().freeMemory() / 1024 / 1024 + "MB");
                        sb.append(" Total : " + Runtime.getRuntime().totalMemory() / 1024 / 1024 + "MB");
                        sb.append(" Max : " + Runtime.getRuntime().maxMemory() / 1024 / 1024 + "MB");
                        logger.info(sb.toString());
                        Runtime.getRuntime().gc();
                    }
                } else if (rc == -1) {
                    ss.close();
                    send_slack_message("Socket Close");
                    break;
                }
            }
        } catch (BindException e) {
            logger.warning("BindException");
            e.printStackTrace();
        } catch (InterruptedException e) {
            logger.warning("InterruptedException");
            send_slack_message(get_stack_trace(e));
            e.printStackTrace();
        } catch (IOException e) {
            logger.warning("IOException");
            send_slack_message(get_stack_trace(e));
            e.printStackTrace();
        } catch (Exception e) {
            logger.warning("Exception");
            send_slack_message(get_stack_trace(e));
            e.printStackTrace();
        }
        logger.info("FINISH " + pid_host);
    }

    public static void send_slack_message(String message_) {
        try {
            for (int i = 0; i < 2; i++) {
                String json_data = "{\"text\":\"" + System.getenv("RENDER_EXTERNAL_HOSTNAME") + " " + message_
                        + "\", \"channel\":\"" + System.getenv("SLACK_CHANNEL_0" + String.valueOf(i + 1)) + "\"}";
                HttpRequest.BodyPublisher post_data = HttpRequest.BodyPublishers.ofString(json_data);
                HttpClient client = HttpClient.newHttpClient();
                HttpRequest request = HttpRequest.newBuilder(URI.create("https://slack.com/api/chat.postMessage"))
                        .header("Authorization", "Bearer " + System.getenv("SLACK_TOKEN"))
                        .header("Content-Type", "application/json;charset=UTF-8").POST(post_data).build();
                client.sendAsync(request, HttpResponse.BodyHandlers.ofString()).thenAccept(response -> {
                    _logger.info(response.body());
                });
                Thread.sleep(1000);
            }
        } catch (InterruptedException e) {
            _logger.warning("InterruptedException");
            e.printStackTrace();
        } catch (Exception e) {
            _logger.warning("Exception");
            e.printStackTrace();
        }
    }

    public static String get_stack_trace(Exception e_) {
        try {
            Thread.sleep(3000);
            StackTraceElement[] list = e_.getStackTrace();
            var sb = new StringBuilder();
            sb.append(e_.getClass()).append(":").append(e_.getMessage()).append("\n");
            for (StackTraceElement ste : list) {
                sb.append(ste.toString()).append("\n");
            }
            return sb.toString();
        } catch (InterruptedException e) {
            _logger.warning("InterruptedException");
            e.printStackTrace();
            return "InterruptedException";
        } catch (Exception e) {
            _logger.warning("Exception");
            e.printStackTrace();
            return "Exception";
        }
    }
}
