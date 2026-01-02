<?php
// db_config.php
$servername = "localhost";
$username = "root"; // 環境に合わせて変更してください
$password = "passwordA1!";     // 環境に合わせて変更してください
$dbname = "artshop";

// データベース接続
$conn = new mysqli($servername, $username, $password, $dbname);

// 接続チェック
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 文字コード設定
$conn->set_charset("utf8mb4");
?>