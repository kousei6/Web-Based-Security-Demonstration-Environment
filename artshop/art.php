<?php
// art.php
session_start();
include 'db_config.php';

$art_id = isset($_GET['art_id']) ? intval($_GET['art_id']) : 0; // SQLインジェクション対策としてintval

if ($art_id === 0) {
    echo "商品IDが指定されていません。";
    exit();
}

$art_info = null;
$artist_info = null; // この変数は使われていませんが残しておきます

// 商品情報を取得 (statusとpriceを追加)
$stmt_art = $conn->prepare("SELECT A.art_id, A.art_name, A.art_detail, A.image_path, A.status, A.price, A.artist_id, AR.artist_name, AR.icon_path FROM Art A JOIN Artist AR ON A.artist_id = AR.artist_id WHERE A.art_id = ?");
$stmt_art->bind_param("i", $art_id);
$stmt_art->execute();
$result_art = $stmt_art->get_result();
if ($result_art->num_rows > 0) {
    $art_info = $result_art->fetch_assoc();
} else {
    echo "指定された商品が見つかりません。";
    exit();
}
$stmt_art->close();

// 価格のフォーマット (例: 1,000円)
$display_price = number_format($art_info['price']) . ' 円';

// 在庫ステータスの表示テキスト
$stock_status_text = '';
$can_purchase = false;
if ($art_info['status'] >= 1) {
    $stock_status_text = '在庫あり';
    $can_purchase = true;
} elseif ($art_info['status'] <= 0) {
    $stock_status_text = '売切れ';
} else {
    $stock_status_text = 'ステータス不明'; // その他のステータス
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($art_info['art_name']); ?> - 絵画通販サイト</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        header { background-color: #333; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; }
        header h1 { margin: 0; font-size: 24px; }
        header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 900px; margin: 20px auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .art-detail { display: flex; gap: 30px; margin-bottom: 40px; }
        .art-image { flex: 1; text-align: center; }
        .art-image img { max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .art-info { flex: 2; }
        .art-info h2 { font-size: 32px; margin-top: 0; margin-bottom: 10px; color: #333; }
        .art-info .artist-link { font-size: 18px; color: #007bff; text-decoration: none; margin-bottom: 15px; display: inline-block; }
        .art-info .artist-link:hover { text-decoration: underline; }
        .art-info p { line-height: 1.8; color: #555; margin-bottom: 8px; } /* 行間調整 */
        .art-info .price { font-size: 24px; color: #d9534f; font-weight: bold; margin-bottom: 15px; } /* 価格スタイル */
        .art-info .stock { font-size: 16px; color: #337ab7; font-weight: bold; margin-bottom: 20px; } /* 在庫スタイル */
        .buy-button { background-color: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 5px; font-size: 20px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 20px; }
        .buy-button:hover { background-color: #218838; }
        .buy-button.disabled { background-color: #ccc; cursor: not-allowed; } /* 無効化されたボタン */
        .message { color: green; font-weight: bold; margin-top: 15px; }
        .error-message { color: red; font-weight: bold; margin-top: 15px; }
    </style>
</head>
<body>
    <header>
        <h1><a href="top.php">絵画通販サイト</a></h1>
        <nav>
            <a href="top.php">トップ</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="mypage.php">マイページ</a>
                <a href="logout.php">ログアウト</a>
            <?php else: ?>
                <a href="login.php">ログイン</a>
            <?php endif; ?>
            <a href="history.cgi">購入履歴</a>
        </nav>
    </header>

    <div class="container">
        <div class="art-detail">
            <div class="art-image">
                <img src="<?php echo htmlspecialchars($art_info['image_path'] ?: 'images/default_art.png'); ?>" alt="<?php echo htmlspecialchars($art_info['art_name']); ?>">
            </div>
            <div class="art-info">
                <h2><?php echo htmlspecialchars($art_info['art_name']); ?></h2>
                <a href="artist.cgi?artist_id=<?php echo htmlspecialchars($art_info['artist_id']); ?>" class="artist-link">
                    作者: <?php echo htmlspecialchars($art_info['artist_name']); ?>
                </a>
                
                <h3>価格: <span class="price"><?php echo $display_price; ?></span></h3>
                <p class="stock">在庫状況: <?php echo htmlspecialchars($stock_status_text); ?></p>

                <h3>作品詳細</h3>
                <p><?php echo nl2br(htmlspecialchars($art_info['art_detail'])); ?></p>

                <?php if (isset($_GET['purchase_success'])): ?>
                    <p class="message">ご購入ありがとうございます！</p>
                <?php elseif (isset($_GET['purchase_error'])): ?>
                    <p class="error-message">購入処理中にエラーが発生しました。</p>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($can_purchase): ?>
                        <form action="purchase.php" method="post">
                            <input type="hidden" name="art_id" value="<?php echo htmlspecialchars($art_info['art_id']); ?>">
                            <input type="submit" value="購入する" class="buy-button">
                        </form>
                    <?php else: ?>
                        <p class="error-message">この作品は現在購入できません。</p>
                        <button class="buy-button disabled" disabled>売切れ</button>
                    <?php endif; ?>
                <?php else: ?>
                    <p>購入するには<a href="login.php">ログイン</a>してください。</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
