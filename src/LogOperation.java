import java.io.IOException;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.concurrent.ExecutionException;
import java.util.concurrent.Executors;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Future;
import java.util.List;
import java.util.logging.Logger;
import java.util.Properties;

public final class LogOperation {
    private static Logger _logger;
    private static LogOperation _log_operation = new LogOperation();
    private static Connection _conn;
    private static PreparedStatement _ps;
    private static ExecutorService _executorService;

    private LogOperation() {
    }

    public static LogOperation getInstance(Logger logger_) {
        _logger = logger_;
        _executorService = Executors.newFixedThreadPool(Integer.parseInt(System.getenv("FIXED_THREAD_POOL")));
        try {
            Class.forName("org.sqlite.JDBC");
            var props = new Properties();
            props.put("journal_mode", "WAL");
            props.put("busy_timeout", 10000);
            _conn = DriverManager.getConnection("jdbc:sqlite:/tmp/sqlitelog.db", props);
            _ps = _conn.prepareStatement(
                    "SELECT seq, process_datetime, pid, level, file, line, function, message, tags FROM t_log WHERE status = 0 ORDER BY seq",
                    ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
        } catch (ClassNotFoundException e) {
            _logger.warning("ClassNotFoundException");
            LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
            e.printStackTrace();
        } catch (SQLException e) {
            _logger.warning("SQLException");
            LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
            e.printStackTrace();
        } catch (Exception e) {
            _logger.warning("Exception");
            LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
            e.printStackTrace();
        }
        return _log_operation;
    }

    // 1 : record exists / 0 : record none / < 0 : error
    public final int execute() {
        List<Future<Integer>> futures = new ArrayList<>();

        int rc = 0;
        try {
            ResultSet rs = _ps.executeQuery();
            while (rs.next()) {
                int seq = rs.getInt("seq");
                String process_datetime = rs.getString("process_datetime");
                String pid = rs.getString("pid");
                String level = rs.getString("level");
                String file = rs.getString("file");
                String line = rs.getString("line");
                String function = rs.getString("function");
                String message = rs.getString("message");
                String tags = rs.getString("tags");

                futures.add(_executorService.submit(
                        new LogglySend(_logger, seq, process_datetime, pid, level, file, line, function, message, tags)));
                Thread.sleep(100);
                rc = 1;
            }
        } catch (InterruptedException e) {
            rc = -1;
            _logger.warning("InterruptedException");
            LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
            e.printStackTrace();
        } catch (SQLException e) {
            rc = -2;
            _logger.warning("SQLException");
            LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
            e.printStackTrace();
        } catch (Exception e) {
            rc = -3;
            _logger.warning("Exception");
            LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
            e.printStackTrace();
        }

        for (Future<Integer> future : futures) {
            try {
                future.get();
            } catch (InterruptedException e) {
                rc = -4;
                _logger.warning("InterruptedException");
                LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
                e.printStackTrace();
            } catch (ExecutionException e) {
                rc = -5;
                _logger.warning("ExecutionException");
                LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
                e.printStackTrace();
            } catch (Exception e) {
                rc = -6;
                _logger.warning("Exception");
                LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
                e.printStackTrace();
            }
        }

        return rc;
    }
}
