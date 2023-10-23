import java.io.IOException;
import java.lang.management.ManagementFactory;
import java.net.BindException;
import java.net.ServerSocket;
import java.time.format.DateTimeFormatter;
import java.time.LocalDateTime;
import java.util.logging.Level;
import java.util.logging.Logger;

public final class LogOperationMain {
    static final int LOCK_PORT = 45678;

    public static void main(String[] args) {
        System.setProperty("java.util.logging.SimpleFormatter.format",
                "%1$tY-%1$tm-%1$td %1$tH:%1$tM:%1$tS.%1$tL %4$s %2$s %5$s%6$s%n");
        Logger logger = Logger.getLogger(Logger.GLOBAL_LOGGER_NAME);
        logger.setLevel(Level.ALL);
        String pid_host = ManagementFactory.getRuntimeMXBean().getName();
        String start_datetime = LocalDateTime.now().format(DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss"));
        logger.info("START " + pid_host + " " + System.getProperty("user.name"));
        ServerSocket ss;
        try {
            ss = new ServerSocket(LOCK_PORT);
            logger.info("availableProcessors : " + (Runtime.getRuntime()).availableProcessors());
            LogOperation logOperation = LogOperation.getInstance(logger);
            int i = 0;
            for (;;) {
                int rc = logOperation.execute();
                if (rc == 0) {
                    Thread.sleep(1000);
                    if (++i % 60 == 0) {
                        i = 0;
                        var sb = new StringBuffer(4);
                        sb.append("START AT " + start_datetime);
                        sb.append(" Free : " + Runtime.getRuntime().freeMemory() / 1024 / 1024 + "MB");
                        sb.append(" Total : " + Runtime.getRuntime().totalMemory() / 1024 / 1024 + "MB");
                        sb.append(" Max : " + Runtime.getRuntime().maxMemory() / 1024 / 1024 + "MB");
                        logger.info(sb.toString());
                        Runtime.getRuntime().gc();
                    }
                } else if (rc == -1) {
                    ss.close();
                    break;
                }
            }
        } catch (BindException e) {
            logger.warning("BindException");
            e.printStackTrace();
        } catch (IOException e) {
            logger.warning("IOException");
            e.printStackTrace();
        } catch (Exception e) {
            logger.warning("Exception");
            e.printStackTrace();
        }
        logger.info("FINISH " + pid_host);
    }
}
