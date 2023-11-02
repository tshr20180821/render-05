import java.io.IOException;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.SQLException;
import java.util.concurrent.Callable;
import java.util.logging.Logger;
import java.util.Properties;

public final class LogglySend implements Callable<Integer> {

    private Logger _logger;
    private int _seq;
    private String _process_datetime;
    private String _pid;
    private String _level;
    private String _file;
    private String _line;
    private String _function;
    private String _message;

    private LogglySend() {
    }

    public LogglySend(Logger logger_, int seq_, String process_datetime_, String pid_, String level_, String file_,
            String line_, String function_, String message_) {
        this._logger = logger_;
        this._seq = seq_;
        this._process_datetime = process_datetime_;
        this._pid = pid_;
        this._level = level_;
        this._file = file_;
        this._line = line_;
        this._function = function_;
        this._message = message_;
    }

    @Override
    public final Integer call() throws Exception {
        this._logger.info("START " + this._seq + " " + this._process_datetime + " " + this._file + " " + this._line + " " + this._function + " " + this._message);
        this.sendLoggly();
        this._logger.info("HALF POINT " + this._seq);
        this.updateLogTable();
        this._logger.info("FINISH " + this._seq);
        return 0;
    }

    private final void sendLoggly() {
        try {
            String render_external_hostname = System.getenv("RENDER_EXTERNAL_HOSTNAME");
            String deploy_datetime = System.getenv("DEPLOY_DATETIME");
            var sb = new StringBuilder(17);
            sb.append(this._process_datetime);
            sb.append(" ");
            sb.append(render_external_hostname);
            sb.append(" ");
            sb.append(deploy_datetime);
            sb.append(" ");
            sb.append(this._pid);
            sb.append(" ");
            sb.append(this._level);
            sb.append(" ");
            sb.append(this._file);
            sb.append(" ");
            sb.append(this._line);
            sb.append(" ");
            sb.append(this._function);
            sb.append(" ");
            sb.append(this._message);
            HttpRequest.BodyPublisher post_data = HttpRequest.BodyPublishers.ofString(sb.toString());
            HttpClient client = HttpClient.newHttpClient();
            String uri = "https://logs-01.loggly.com/inputs/" + System.getenv("LOGGLY_TOKEN") + "/tag/"
                    + render_external_hostname + "," + render_external_hostname + '_' + deploy_datetime + "/";
            HttpRequest request = HttpRequest.newBuilder(URI.create(uri))
                    .header("Content-Type", "text/plain; charset=utf-8")
                    .POST(HttpRequest.BodyPublishers.ofString(sb.toString()))
                    .build();
            HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());
            if (response.statusCode() != 200) {
                _logger.warning(response.statusCode() + " " + response.body() + "\n" + this._seq + " " + sb.toString());
                LogOperationMain.send_slack_message(response.statusCode() + " " + response.body() + "\n" + this._seq + " " + sb.toString());
            }
        } catch (IOException e) {
            this._logger.warning("IOException");
            LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
            e.printStackTrace();
        } catch (Exception e) {
            this._logger.warning("Exception");
            LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
            e.printStackTrace();
        }
    }

    private final void updateLogTable() {
        Connection conn = null;
        PreparedStatement ps = null;
        try {
            Class.forName("org.sqlite.JDBC");
            var props = new Properties();
            props.put("journal_mode", "WAL");
            props.put("busy_timeout", 10000);
            conn = DriverManager.getConnection("jdbc:sqlite:/tmp/sqlitelog.db", props);
            ps = conn.prepareStatement("UPDATE t_log SET status = 1 WHERE seq = ?");
            ps.setInt(1, this._seq);
            ps.executeUpdate();
        } catch (ClassNotFoundException e) {
            _logger.warning("ClassNotFoundException");
            LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
            e.printStackTrace();
        } catch (SQLException e) {
            this._logger.warning("SQLException " + this._seq);
            this._logger.warning("-- e.getMessage() START --");
            this._logger.warning(e.getMessage());
            this._logger.warning("-- e.getMessage() FINISH --");
            this._logger.warning("-- e.getErrorCode() START --");
            this._logger.warning(String.valueOf(e.getErrorCode()));
            this._logger.warning("-- e.getErrorCode() FINISH --");
            LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
            e.printStackTrace();
        } catch (Exception e) {
            this._logger.warning("Exception " + this._seq);
            LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
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
                this._logger.warning("Exception");
                LogOperationMain.send_slack_message(LogOperationMain.get_stack_trace(e));
                e.printStackTrace();
            }
        }
    }
}
