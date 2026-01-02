<?php
// logout.php
session_start();
include 'db_config.php'; // DB接続のため追加

// ログイン中のユーザーIDを取得
$user_id = $_SESSION['user_id'] ?? null;

// セッション変数を全て解除
$_SESSION = array();

// クッキーに保存されているセッションIDを削除
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// ★修正点1: user_idクッキーも削除 (Python CGIとの連携用)
if (isset($_COOKIE['user_id'])) {
    setcookie('user_id', '', time()-42000, '/');
}

// セッションを破棄
session_destroy();

// ★修正点2: データベースのUserテーブルからsession_idをクリアする
if ($user_id !== null) {
    $stmt_clear_session = $conn->prepare("UPDATE User SET session_id = '' WHERE user_id = ?");
    $stmt_clear_session->bind_param("i", $user_id);
    $stmt_clear_session->execute();
    $stmt_clear_session->close();
}

$conn->close(); // DB接続を閉じる

// ログインページへリダイレクト
header("Location: login.php");
exit();
?>