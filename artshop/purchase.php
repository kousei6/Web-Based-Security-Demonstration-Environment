<?php
// purchase.php
session_start();
include 'db_config.php';
date_default_timezone_set('Asia/Tokyo');

// ログインしていない場合はログインページへリダイレクト
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['art_id'])) {
    $user_id = $_SESSION['user_id'];
    $art_id = intval($_POST['art_id']);

    // --- 作品の在庫状況と情報を再確認 ---
    $art_status = '';
    $art_name = '';
    $stmt_check_art = $conn->prepare("SELECT art_name, status FROM Art WHERE art_id = ?");
    $stmt_check_art->bind_param("i", $art_id);
    $stmt_check_art->execute();
    $result_check_art = $stmt_check_art->get_result();
    if ($result_check_art->num_rows > 0) {
        $art_data = $result_check_art->fetch_assoc();
        $art_name = $art_data['art_name'];
        $art_status = $art_data['status'];
    } else {
        // 作品が見つからない場合はエラー
        error_log("purchase.php: 作品ID {$art_id} が見つかりません。");
        header("Location: art.php?art_id=" . $art_id . "&purchase_error=true");
        exit();
    }
    $stmt_check_art->close();

    // 在庫がない場合は購入を拒否
    if ($art_status <= 0) {
        error_log("purchase.php: 作品ID {$art_id} は購入できません（ステータス: {$art_status}）。");
        header("Location: art.php?art_id=" . $art_id . "&purchase_error=true");
        exit();
    }

    // --- ユーザーのメールアドレスとユーザー名を取得 ---
    $to_email = '';
    $user_name = '';
    $stmt_user_info = $conn->prepare("SELECT user_name, email FROM User WHERE user_id = ?");
    $stmt_user_info->bind_param("i", $user_id);
    $stmt_user_info->execute();
    $result_user_info = $stmt_user_info->get_result();
    if ($result_user_info->num_rows > 0) {
        $user_info = $result_user_info->fetch_assoc();
        $user_name = $user_info['user_name'];
        $to_email = $user_info['email'];
    }
    $stmt_user_info->close();

    // メールアドレスが取得できない場合はエラーログに出力し、処理を続行
    if (empty($to_email)) {
        error_log("購入処理: ユーザーID {$user_id} のメールアドレスが取得できませんでした。");
    }

    // --- データベーストランザクションの開始 ---
    // 購入履歴の追加と作品ステータスの更新はアトミックに行うべき
    $conn->begin_transaction();
    $purchase_success = false;

    try {
    	$trade_date = date('Y-m-d H:i:s');
        // 1. 購入履歴テーブルに登録
        $stmt_trade = $conn->prepare("INSERT INTO Trade (user_id, art_id, trade_date) VALUES (?, ?, ?)");
        $stmt_trade->bind_param("iis", $user_id, $art_id, $trade_date);
        $stmt_trade->execute();
        $stmt_trade->close();

        // 2. 作品のステータスを'sold_out'に更新 (簡易的な在庫管理)
        $stmt_update_art = $conn->prepare("UPDATE Art SET status = status - 1 WHERE art_id = ?");
        $stmt_update_art->bind_param("i", $art_id);
        $stmt_update_art->execute();
        $stmt_update_art->close();

        // 全ての処理が成功したらコミット
        $conn->commit();
        $purchase_success = true;

    } catch (mysqli_sql_exception $e) {
        // エラーが発生したらロールバック
        $conn->rollback();
        error_log("購入トランザクション失敗: " . $e->getMessage());
    }

    if ($purchase_success) {
        // user_idをクッキーに保存
        setcookie("user_id", $user_id, time() + (86400 * 30), "/");

        // --- メール送信処理 ---
        if (!empty($to_email)) {
            $subject = '【絵画通販サイト】ご購入ありがとうございます！';
            $message_body = "{$user_name}様\n\n";
            $message_body .= "この度は、絵画通販サイトをご利用いただき誠にありがとうございます。\n\n";
            $message_body .= "以下の作品のご購入を承りました。\n\n";
            $message_body .= "作品名: {$art_name}\n";
            $message_body .= "購入日時: " . date('Y/m/d H:i:s') . "\n\n";
            $message_body .= "引き続き、絵画通販サイトをお楽しみください。\n\n";
            $message_body .= "--------------------------------------\n";
            $message_body .= "絵画通販サイト\n";
            $message_body .= "お問い合わせ: info@example.com\n";
            $message_body .= "--------------------------------------\n";

            $headers = 'From: info@example.com' . "\r\n" .
                       'Reply-To: info@example.com' . "\r\n" .
                       'X-Mailer: PHP/' . phpversion();

            if (mail($to_email, $subject, $message_body, $headers)) {
                error_log("購入確認メールを {$to_email} 宛に送信しました。");
            } else {
                error_log("メール送信失敗: {$to_email} 宛に購入確認メールを送信できませんでした。");
            }
        }

        // 購入成功後、商品ページに戻って成功メッセージを表示
        header("Location: art.php?art_id=" . $art_id . "&purchase_success=true");
        exit();
    } else {
        // 購入失敗時
        header("Location: art.php?art_id=" . $art_id . "&purchase_error=true");
        exit();
    }
} else {
    // POSTリクエストまたはart_idが指定されていない場合はトップページへリダイレクト
    header("Location: top.php");
    exit();
}
$conn->close();
?>
