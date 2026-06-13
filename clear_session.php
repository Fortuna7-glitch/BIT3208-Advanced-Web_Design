<?php
// clear_session.php - Run this ONCE to clear all sessions
session_start();
session_destroy();
echo "Session cleared! <a href='index.php'>Go to Homepage</a>";
?>