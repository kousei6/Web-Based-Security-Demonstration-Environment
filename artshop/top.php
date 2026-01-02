<?php
// top.php
session_start();
include 'db_config.php';

// 作者一覧の取得 (Retrieve list of artists)
$sql_artists = "SELECT artist_id, artist_name, icon_path FROM Artist ORDER BY artist_name ASC";
$result_artists = $conn->query($sql_artists);

// 最新6件の絵画一覧取得（アーティスト名も含む） (Retrieve the latest 6 artworks, including artist names)
$sql_arts = "
SELECT
    Art.art_id AS art_id,
    Art.art_name AS art_name,
    Artist.artist_name AS artist_name,
    Art.image_path AS image_path
FROM Art
JOIN Artist ON Art.artist_id = Artist.artist_id
ORDER BY Art.art_id DESC
LIMIT 6";
$result_arts = $conn->query($sql_arts);

// 全新着絵画取得（フェード用） - MODIFIED FOR RANDOM ORDER (Retrieve all new artworks for the fading display)
$sql_all_new_arts = "
SELECT
    Art.art_id AS art_id,
    Art.art_name AS art_name,
    Artist.artist_name AS artist_name,
    Art.image_path AS image_path
FROM Art
JOIN Artist ON Art.artist_id = Artist.artist_id
ORDER BY RAND() -- Changed to order by random
";
$result_all_new_arts = $conn->query($sql_all_new_arts);

$all_new_arts = [];
if ($result_all_new_arts->num_rows > 0) {
    while ($row = $result_all_new_arts->fetch_assoc()) {
        $all_new_arts[] = $row;
    }
}

// --- DEBUGGING STEP REMOVED ---
// The var_dump output has been removed to clean the page.
// If you need to debug again, you can re-add the lines here.
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>絵画通販サイト</title>
    <style>
        /* --- CSS for layout and styling --- */
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            display: flex; /* フッターを最下部に固定するためにflexboxを使用 */
            flex-direction: column; /* 垂直方向に要素を配置 */
            min-height: 100vh; /* 画面全体の高さを確保 */
        }
        header {
            background-color: #333;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header h1 {
            margin: 0;
            font-size: 24px;
        }
        header a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
            flex-grow: 1; /* コンテンツエリアが残りのスペースを占めるようにする */
        }
        .section-title {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .artist-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-bottom: 40px;
        }
        .artist-item {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            width: 180px;
            /* ホバーアニメーションの基底トランジション */
            transition: box-shadow 0.3s ease, transform 0.3s ease;
        }
        .artist-item img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }
        .artist-item a {
            display: block; /* ホバーエリアをリンク全体に広げるため */
            text-decoration: none;
            color: #333; /* 親要素のテキスト色を継承 */
            font-weight: bold;
            transition: transform 0.3s ease, box-shadow 0.3s ease; /* ホバーアニメーション */
        }

        /* --- Fading Art Display Styles (Modified for responsiveness) --- */
        .fading-art-container {
            position: relative;
            width: 100%; /* Take full available width of its parent (.container) */
            max-width: 800px; /* Increased max-width for larger display on big screens */
            /* Use padding-bottom for aspect ratio based on width */
            padding-bottom: 60%; /* This sets height to 60% of its width (e.g., 800px width * 0.6 = 480px height) */
            height: 0; /* Important for padding-bottom trick */
            margin: 0 auto 40px auto;
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .fading-art-item {
            position: absolute; /* Essential for filling the container based on padding-bottom */
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background-color: #fff;
            pointer-events: none; /* Prevents clicks on the item itself */
            cursor: default; /* Changes cursor to default, indicating not clickable */
        }
        .fading-art-item.active { opacity: 1; }
        .fading-art-item img {
            max-width: 95%; /* Increased max-width to make images larger within the container */
            max-height: calc(100% - 80px); /* Adjust height to make space for caption, slightly more space */
            object-fit: contain;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .fading-art-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 15px 0; /* Increased padding for caption */
            text-align: center;
            font-size: 1.3em; /* Slightly larger font for caption */
            pointer-events: none;
        }
        .fading-art-caption h3 { margin: 0; font-size: 1.2em; }
        .fading-art-caption p { margin: 5px 0 0 0; font-size: 0.9em; }

        /* --- New Arrivals List (Grid) Styles --- */
        .art-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .art-item {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            /* ホバーアニメーションの基底トランジション */
            transition: box-shadow 0.3s ease, transform 0.3s ease;
        }
        .art-item img {
            max-width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .art-item a {
            display: block; /* ホバーエリアをリンク全体に広げるため */
            text-decoration: none;
            color: #007bff; /* リンクの色を維持 */
            font-weight: bold;
            transition: transform 0.3s ease, box-shadow 0.3s ease; /* ホバーアニメーション */
        }

        /* --- ホバーエフェクトのCSS --- */
        .artist-item a:hover,
        .art-item a:hover {
            transform: translateY(-5px); /* わずかに浮き上がる効果 */
            box-shadow: 0 8px 16px rgba(0,0,0,0.2); /* 強調された影 */
        }

        /* --- Footer Styles --- */
        footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 40px; /* コンテンツとの間にスペースを設ける */
            font-size: 0.9em;
        }
        footer p {
            margin: 0;
        }
        footer a {
            color: white;
            text-decoration: none;
            margin: 0 10px;
        }
        footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<header>
    <h1><a href="top.php">絵画通販サイト</a></h1>
    <nav>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="mypage.php">マイページ</a>
            <a href="logout.php">ログアウト</a>
        <?php else: ?>
            <a href="login.php">ログイン</a>
            <a href="register.php">新規登録</a>
        <?php endif; ?>
        <a href="history.cgi">購入履歴</a>
    </nav>
</header>

<div class="container">
    <h2 class="section-title">作者一覧</h2>
    <div class="artist-list">
        <?php if ($result_artists->num_rows > 0): ?>
            <?php while ($row = $result_artists->fetch_assoc()): ?>
                <div class="artist-item">
                    <a href="artist.cgi?artist_id=<?php echo htmlspecialchars($row['artist_id']); ?>">
                        <img src="<?php echo htmlspecialchars($row['icon_path'] ?: 'images/default_artist_icon.png'); ?>" alt="<?php echo htmlspecialchars($row['artist_name']); ?>のアイコン" />
                        <h3><?php echo htmlspecialchars($row['artist_name']); ?></h3>
                    </a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>作者情報がありません。</p>
        <?php endif; ?>
    </div>

    <h2 class="section-title">おすすめ絵画</h2>
    <div class="fading-art-container">
        <?php if (!empty($all_new_arts)): ?>
            <?php foreach ($all_new_arts as $index => $art): ?>
                <?php
                    // Data extraction for display
                    $art_name = htmlspecialchars($art['art_name']);
                    $artist_name = htmlspecialchars($art['artist_name']);
                    $image_path = htmlspecialchars($art['image_path'] ?: 'images/default_art.png');
                ?>
                <div class="fading-art-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <img src="<?php echo $image_path; ?>" alt="<?php echo $art_name; ?>" />
                    <div class="fading-art-caption">
                        <h3><?php echo $art_name; ?></h3>
                        <p>作者: <?php echo $artist_name; ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; padding-top: 150px;">現在、おすすめ絵画はありません。</p>
        <?php endif; ?>
    </div>

    <h2 class="section-title">新着絵画一覧</h2>
    <div class="art-grid">
        <?php
        // Reset the pointer for $result_arts to display the 6 latest arts in the grid again
        $result_arts->data_seek(0);
        if ($result_arts->num_rows > 0):
            while ($row = $result_arts->fetch_assoc()):
        ?>
            <div class="art-item">
                <a href="art.php?art_id=<?php echo htmlspecialchars($row['art_id']); ?>">
                    <img src="<?php echo htmlspecialchars($row['image_path'] ?: 'images/default_art.png'); ?>" alt="<?php echo htmlspecialchars($row['art_name']); ?>" />
                    <h3><?php echo htmlspecialchars($row['art_name']); ?></h3>
                    <p>作者: <?php echo htmlspecialchars($row['artist_name']); ?></p>
                </a>
            </div>
        <?php
            endwhile;
        else:
        ?>
            <p>現在、新着絵画はありません。</p>
        <?php endif; ?>
    </div>
</div>

<footer>
    <p>&copy; <?php echo date('Y'); ?> 絵画通販サイト. All rights reserved.</p>
    <p>
        <a href="#">プライバシーポリシー</a> |
        <a href="#">利用規約</a> |
        <a href="#">お問い合わせ</a>
    </p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fadingArtItems = document.querySelectorAll('.fading-art-item');
    if (fadingArtItems.length === 0) return;

    let currentIndex = 0;

    function showNextArt() {
        fadingArtItems[currentIndex].classList.remove('active');
        currentIndex = (currentIndex + 1) % fadingArtItems.length;
        fadingArtItems[currentIndex].classList.add('active');
    }

    setInterval(showNextArt, 5000);
});
</script>
</body>
</html>
<?php
// Close the database connection
$conn->close();
?>
