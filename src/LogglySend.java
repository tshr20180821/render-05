import java.io.PrintStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.SQLException;
import java.util.concurrent.Callable;
import java.util.logging.Logger;

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

    private LogglySend() {}

    public LogglySend(Logger logger_, int seq_, String process_datetime_, String pid_, String level_, String file_, String line_, String function_, String message_) {
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
    public Integer call() throws Exception {
        this._logger.info("START " + this._seq + " " + this._process_datetime + " " + this._message);
        this.sendLoggly();
        this._logger.info("HALF POINT " + this._seq);
        this.updateLogTable();
        this._logger.info("FINISH " + this._seq);
        return 0;
    }

    private void sendLoggly() {
        try {
            var render_external_hostname = System.getenv("RENDER_EXTERNAL_HOSTNAME");
            var deploy_datetime = System.getenv("DEPLOY_DATETIME");
            var url = new URL("https://logs-01.loggly.com/inputs/" + System.getenv("LOGGLY_TOKEN")
                + "/tag/" + render_external_hostname + "," + render_external_hostname + '_' + deploy_datetime + "/");
            var conn = (HttpURLConnection)url.openConnection();
            conn.setRequestMethod("POST");
            // conn.setDoInput(true);
            conn.setDoOutput(true);
            conn.setRequestProperty("Content-Type", "text/plain; charset=utf-8");
            conn.connect();
            var ps = new PrintStream(conn.getOutputStream());
            /*
            var log_message = this._process_datetime + " " + render_external_hostname + " " + deploy_datetime
                + " " + this._pid + " " + this._level + " " + this._file + " " + this._line + " " + this._function + " " + this._message;
                */
            var sb = new StringBuffer(17);
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
            var log_message = sb.toString();
            ps.print(sb.toString());
            ps.close();
            if (conn.getResponseCode() != 200) {
                this._logger.warning("ERROR" + " " + conn.getResponseCode() + " " + this._seq + " " + sb.toString());
            }
        } catch (Exception e) {
            this._logger.warning("Exception");
            e.printStackTrace();
        }
    }

    private void updateLogTable() {
        Connection conn = null;
        PreparedStatement ps = null;
        try {
            Class.forName("org.sqlite.JDBC");
            conn = DriverManager.getConnection("jdbc:sqlite:/tmp/sqlitelog.db");
            ps = conn.prepareStatement("UPDATE t_log SET status = 1 WHERE seq = ?");
            ps.setInt(1, this._seq);
            ps.executeUpdate();
        } catch (SQLException e) {
            this._logger.warning("SQLException");
            e.printStackTrace();
        } catch (Exception e) {
            this._logger.warning("Exception");
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
                e.printStackTrace();
            }
        }
    }
}
