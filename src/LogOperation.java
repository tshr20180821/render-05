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

public final class LogOperation {
    private static Logger _logger;
    private static LogOperation _log_operation = new LogOperation();

    private LogOperation() {}

    public static LogOperation getInstance(Logger logger_) {
        _logger = logger_;
        return _log_operation;
    }

    public boolean execute() {
        ExecutorService executorService = Executors.newFixedThreadPool(1);
        List<Future<Integer>> futures = new ArrayList<>();

        boolean is_record_exists = false;
        Connection conn = null;
        PreparedStatement ps = null;
        try {
            Class.forName("org.sqlite.JDBC");
            conn = DriverManager.getConnection("jdbc:sqlite:/tmp/sqlitelog.db");
            ps = conn.prepareStatement("SELECT seq, process_datetime, pid, level, file, line, function, message FROM t_log WHERE status = 0", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) {
                int seq = rs.getInt("seq");
                String process_datetime = rs.getString("process_datetime");
                String pid = rs.getString("pid");
                String level = rs.getString("level");
                String file = rs.getString("file");
                String line = rs.getString("line");
                String function = rs.getString("function");
                String message = rs.getString("message");

                futures.add(executorService.submit(new LogglySend(_logger, seq, process_datetime, pid, level, file, line, function, message)));
                is_record_exists = true;
            }
        } catch (SQLException e) {
            _logger.warning("SQLException");
            e.printStackTrace();
        } catch (Exception e) {
            _logger.warning("Exception");
            e.printStackTrace();
        } finally {
            try {
                if (ps != null) {
                    ps.close();
                }
                if (conn != null) {
                    conn.close();
                }
            } catch (Exception e) {
                _logger.warning("Exception");
                e.printStackTrace();
            }
        }

        for (Future<Integer> future : futures) {
            try {
                future.get();
            } catch (InterruptedException e) {
                _logger.warning("InterruptedException");
                e.printStackTrace();
            } catch (ExecutionException e) {
                _logger.warning("ExecutionException");
                e.printStackTrace();
            } catch (Exception e) {
                _logger.warning("Exception");
                e.printStackTrace();
            }
        }

        executorService.shutdown();

        return is_record_exists;
    }
}
