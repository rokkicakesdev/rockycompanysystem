<?php
/**
 * Session Cleaner & Debugger
 * Use this script to clear your session and debug redirect issues
 * 
 * Access: http://localhost/project/clear_session.php
 */

session_start();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Session Cleaner</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-top: 0; }
        .info { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; }
        .success { background: #e8f5e9; border-left: 4px solid #4CAF50; padding: 15px; margin: 20px 0; }
        .warning { background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 20px 0; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .btn { display: inline-block; padding: 12px 24px; background: #2196F3; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px; }
        .btn:hover { background: #1976D2; }
        .btn-danger { background: #f44336; }
        .btn-danger:hover { background: #d32f2f; }
    </style>
</head>
<body>
    <div class='card'>
        <h1>🔧 Session Cleaner & Debugger</h1>";

// Show current session data
echo "<div class='info'>
        <h3>Current Session Data:</h3>
        <pre>" . print_r($_SESSION, true) . "</pre>
      </div>";

// Check if action is to clear session
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    session_unset();
    session_destroy();
    session_start(); // Start fresh session
    
    echo "<div class='success'>
            <h3>✅ Session Cleared Successfully!</h3>
            <p>All session data has been removed. You can now login again.</p>
          </div>";
    
    echo "<a href='index.php' class='btn'>Go to Login Page</a>";
} else {
    // Show current issues
    if (isset($_SESSION['user_id'])) {
        $role = $_SESSION['role'] ?? 'unknown';
        echo "<div class='warning'>
                <h3>⚠️ Active Session Detected</h3>
                <p><strong>User ID:</strong> {$_SESSION['user_id']}</p>
                <p><strong>Role:</strong> {$role}</p>
                <p><strong>Name:</strong> " . ($_SESSION['name'] ?? 'N/A') . "</p>
              </div>";
        
        echo "<p>If you're experiencing redirect loops, click the button below to clear your session:</p>";
        echo "<a href='clear_session.php?action=clear' class='btn btn-danger'>Clear Session & Fix Redirects</a>";
    } else {
        echo "<div class='info'>
                <h3>ℹ️ No Active Session</h3>
                <p>No session data found. You are not logged in.</p>
              </div>";
        
        echo "<a href='index.php' class='btn'>Go to Login Page</a>";
    }
}

echo "
        <hr style='margin: 30px 0;'>
        <h3>🔍 Troubleshooting Tips:</h3>
        <ul>
            <li><strong>ERR_TOO_MANY_REDIRECTS:</strong> Clear session using the button above</li>
            <li><strong>Stuck on wrong page:</strong> Clear browser cache and cookies for localhost</li>
            <li><strong>Login issues:</strong> Make sure you're using correct credentials:
                <ul>
                    <li>Admin: <code>admin1</code> / <code>password123</code></li>
                    <li>Management: <code>management1</code> / <code>password123</code></li>
                </ul>
            </li>
        </ul>
        
        <h3>📋 Quick Actions:</h3>
        <a href='index.php' class='btn'>Login Page</a>
        <a href='logout.php' class='btn btn-danger'>Logout</a>
        <a href='clear_session.php?action=clear' class='btn btn-danger'>Clear Session</a>
    </div>
</body>
</html>";
?>
