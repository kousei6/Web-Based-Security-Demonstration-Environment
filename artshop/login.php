<?php
// login.php
session_start();
include 'db_config.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $plain_password = $_POST['password']; // ユーザーが入力した平文のパスワード

    // データベースからユーザー名に一致するユーザーの情報を取得
    // SQLインジェクション脆弱性: ここではユーザー名も直接クエリに埋め込んでいるため、依然として脆弱です。
    $sql = "SELECT user_id, user_name, password FROM User WHERE user_name = '$username'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $hashed_password_from_db = $row['password']; // DBに保存されているハッシュ化されたパスワード

        if (password_verify($plain_password, $hashed_password_from_db)) {
            // パスワードが一致した場合

            // ★修正点1: ログイン成功時にセッションIDを再生成する (セッション固定攻撃対策)
            session_regenerate_id(true); // 古いセッションファイルを削除

            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['user_name'] = $row['user_name'];
            
            // ★修正点2: 現在のセッションIDをデータベースのUserテーブルに保存する
            $current_session_id = session_id();
            $stmt_update_session = $conn->prepare("UPDATE User SET session_id = ? WHERE user_id = ?");
            $stmt_update_session->bind_param("si", $current_session_id, $row['user_id']);
            $stmt_update_session->execute();
            $stmt_update_session->close();

            // history.cgi (Python) はPHPのセッションを直接読めないため、user_idクッキーは維持
            // (本来はPHP/Perl/Python間でセッションIDを共有し、DBでセッションデータを管理するのが理想)
            setcookie("user_id", $row['user_id'], time() + (86400 * 30), "/");

            header("Location: mypage.php");
            exit();
        } else {
            $message = "ユーザー名またはパスワードが異なります。";
        }
    } else {
        $message = "ユーザー名またはパスワードが異なります。";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - 絵画通販サイト</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 400px; margin: auto; padding: 20px; border: 1px solid #ccc; border-radius: 5px; }
        input[type="text"], input[type="password"] { width: calc(100% - 22px); padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
        input[type="submit"] { background-color: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .message { color: red; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>ログイン</h2>
        <?php if ($message): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        <form action="login.php" method="post">
            <label for="username">ユーザー名:</label><br>
            <input type="text" id="username" name="username" required><br>
            <label for="password">パスワード:</label><br>
            <input type="password" id="password" name="password" required><br><br>
            <input type="submit" value="ログイン">
        </form>
        <p>アカウントをお持ちでない方は<a href="register.php">こちら</a>から登録してください。</p>
        <p><a href="top.php">トップページへ戻る</a></p>
    </div>
</body>
</html>