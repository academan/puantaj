<?
// Database connection (Replace with your actual credentials)
$host = 'hidden';
$dbname = 'dbpuantaj';
$user = 'puantaj';
$pass = 'hidden';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Recommended for security

    // --- Add this logging block ---
    $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, ['MyLogStatement', array()]);

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

class MyLogStatement extends PDOStatement {
    protected function __construct() {} // Prevent direct instantiation

    public function execute($params=null) {
        ob_start();
        debug_print_backtrace();
        $backtrace = ob_get_contents();
        ob_end_clean();

        $query = $this->queryString;
        $logMessage = "Executing Query:\n" . $query . "\nParameters: " . print_r($params, true) . "\nBacktrace:\n" . $backtrace . "\n--------------------\n";
        error_log($logMessage, 3, 'sql_queries.log'); // Log to 'sql_queries.log' file (relative path)

        return parent::execute($params);
    }
}
?>
