<?php
session_start();
include 'db_config.php'; // このパスが正しいことを確認してください

// 未ログインの場合、ログインページへリダイレクト
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
// ヘルパー関数: POSTデータを取得
function getPostData($key, $default = null) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// ユーザーの現在のデータをデータベースから取得
$db_user_data = [];
$stmt_fetch_user = $conn->prepare("
    SELECT user_name, password, last_name, first_name, last_name_kana, first_name_kana,
           email, phone, postal_code, pref, city, street, building, payment,
           card_number, expiry_month, expiry_year, cvc,
           bank_name, branch_name, account_number
    FROM User
    WHERE user_id = ?
");
$stmt_fetch_user->bind_param("i", $user_id);
$stmt_fetch_user->execute();
$result_fetch_user = $stmt_fetch_user->get_result();
$db_user_data = $result_fetch_user->fetch_assoc();
$stmt_fetch_user->close();

if (!$db_user_data) {
    $message = "ユーザー情報の取得に失敗しました。システム管理者にお問い合わせください。";
// ここで致命的なエラーとして処理を中断するか、マイページへリダイレクト
    // header("Location: mypage.php"); exit();
}

// ----------------------------------------------------
// ページの状態を決定する変数
// 'mypage': マイページ表示
// 'password_input': パスワード入力フォーム表示
// 'edit_form': 登録情報編集フォーム表示
// 'confirmation_page': 確認ページ表示
$current_page_state = 'mypage';
$user_data_for_form = []; // フォームに事前入力するデータ

// 初期表示時または編集成功時
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // 編集成功後のリダイレクトメッセージ
    if (isset($_GET['updated']) && $_GET['updated'] === 'true') {
        $message = "登録情報が正常に更新されました。";
    }
    $current_page_state = 'mypage';
    unset($_SESSION['form_data']); // 古いセッションデータをクリア
}
// ----------------------------------------------------
// POSTリクエスト時の処理
else if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        switch ($action) {
            case 'edit_profile_start':
                // マイページから「登録内容を修正する」ボタンを押した場合
                $current_page_state = 'password_input';
                unset($_SESSION['form_data']); // 念のためセッションデータをクリア
                break;
            case 'verify_password':
                // パスワード入力フォームからの送信
                $input_password = getPostData('current_password');
                if (password_verify($input_password, $db_user_data['password'])) {
                    // パスワードが正しい場合、編集フォームを表示するための準備
                    $current_page_state = 'edit_form';
                    // DBから取得した最新の情報をセッションにセット（フォーム事前入力用）
                    $phone_parts = explode('-', $db_user_data['phone']);
                    $postal_parts = explode('-', $db_user_data['postal_code']);

                    $_SESSION['form_data'] = [
                        'last_name' => $db_user_data['last_name'],
                        'first_name' => $db_user_data['first_name'],
                        'last_name_kana' => $db_user_data['last_name_kana'],
                        'first_name_kana' => $db_user_data['first_name_kana'],
                        'phone_area' => $phone_parts[0] ?? '',
                        'phone_local' => $phone_parts[1] ?? '',
                        'phone_sub' => $phone_parts[2] ?? '',
                        'postal1' => $postal_parts[0] ?? '',
                        'postal2' => $postal_parts[1] ?? '',
                        'pref' => $db_user_data['pref'],
                        'city' => $db_user_data['city'],
                        'street' => $db_user_data['street'],
                        'building' => $db_user_data['building'],
                        'email' => $db_user_data['email'],
                        'user_name' => $db_user_data['user_name'],
                        'new_password' => '', // 新しいパスワードは初期値として空
                        'payment' => $db_user_data['payment'],
                        'card_number' => $db_user_data['card_number'],
                        'expiry_month' => $db_user_data['expiry_month'],
                        'expiry_year' => $db_user_data['expiry_year'],
                        'cvc' => $db_user_data['cvc'],
                        'bank_name' => $db_user_data['bank_name'],
                        'branch_name' => $db_user_data['branch_name'],
                        'account_number' => $db_user_data['account_number'],
                    ];
                    $user_data_for_form = $_SESSION['form_data'];
                } else {
                    $message = "現在のパスワードが間違っています。";
                    $current_page_state = 'password_input'; // パスワード再入力を求める
                }
                break;
            case 'confirm_edit':
                // 編集フォームからの確認ボタン押下
                // フォームからの入力を取得し、セッションに保存
                $_SESSION['form_data'] = [
                    'last_name' => getPostData('last_name'),
                    'first_name' => getPostData('first_name'),
                    'last_name_kana' => getPostData('last_name_kana'),
                    'first_name_kana' => getPostData('first_name_kana'),
                    'phone_area' => getPostData('phone_area'),
                    'phone_local' => getPostData('phone_local'),
                    'phone_sub' => getPostData('phone_sub'),
                    'postal1' => getPostData('postal1'),
                    'postal2' => getPostData('postal2'),
                    'pref' => getPostData('pref'),
                    'city' => getPostData('city'),
                    'street' => getPostData('street'),
                    'building' => getPostData('building'),
                    'email' => getPostData('email'),
                    'user_name' => getPostData('user_name'),
                    'new_password' => getPostData('password'), // 新しいパスワード
                    'payment' => getPostData('payment'),
                    'card_number' => getPostData('card_number'),
                    'expiry_month' => getPostData('expiry_month'),
                    'expiry_year' => getPostData('expiry_year'),
                    'cvc' => getPostData('cvc'),
                    'bank_name' => getPostData('bank_name'),
                    'branch_name' => getPostData('branch_name'),
                    'account_number' => getPostData('account_number'),
                ];
                $user_name_check = $_SESSION['form_data']['user_name'];
                // ユーザー名が変更された場合のみ重複チェック
                if ($user_name_check !== $db_user_data['user_name']) {
                    $stmt_check_username = $conn->prepare("SELECT user_id FROM User WHERE user_name = ? AND user_id != ?");
                    $stmt_check_username->bind_param("si", $user_name_check, $user_id);
                    $stmt_check_username->execute();
                    $stmt_check_username->store_result();
                    if ($stmt_check_username->num_rows > 0) {
                        $message = "このユーザー名は既に存在します。別のユーザー名をお試しください。";
                        $current_page_state = 'edit_form'; // フォームに戻す
                        $user_data_for_form = $_SESSION['form_data']; // 入力内容を保持
                    }
                    $stmt_check_username->close();
                }

                if (!$message) { // ユーザー名のエラーがなければ確認ページへ
                    $current_page_state = 'confirmation_page';
                    $user_data_for_form = $_SESSION['form_data']; // 確認ページ表示用データ
                }
                break;
            case 'update_execute':
                // 確認ページからの確定ボタン押下 (データベース更新)
                if (isset($_SESSION['form_data'])) {
                    $data = $_SESSION['form_data'];
                    // 新しいパスワードが入力されていればハッシュ化、そうでなければ既存のハッシュ化パスワードを再利用
                    $hashed_password_to_use = !empty($data['new_password']) ? password_hash($data['new_password'], PASSWORD_DEFAULT) : $db_user_data['password'];

                    $phone = $data['phone_area'] . '-' . $data['phone_local'] . '-' . $data['phone_sub'];
                    $postal_code = $data['postal1'] . '-' . $data['postal2'];

                    $stmt_update = $conn->prepare("
                        UPDATE User SET
                            user_name = ?, password = ?, last_name = ?, first_name = ?, last_name_kana = ?, first_name_kana = ?,
                            phone = ?, postal_code = ?, pref = ?, city = ?, street = ?, building = ?, email = ?, payment = ?,
                            card_number = ?, expiry_month = ?, expiry_year = ?, cvc = ?,
                            bank_name = ?, branch_name = ?, account_number = ?
                        WHERE user_id = ?
                    ");
                    $stmt_update->bind_param(
                        "sssssssssssssssssssssi",
                        $data['user_name'], $hashed_password_to_use,
                        $data['last_name'], $data['first_name'], $data['last_name_kana'], $data['first_name_kana'],
                        $phone, $postal_code, $data['pref'], $data['city'], $data['street'], $data['building'], $data['email'], $data['payment'],
                        $data['card_number'], $data['expiry_month'], $data['expiry_year'], $data['cvc'],
                        $data['bank_name'], $data['branch_name'], $data['account_number'],
                        $user_id
                    );

                    if ($stmt_update->execute()) {
                        unset($_SESSION['form_data']); // 更新成功後、セッションデータをクリア
                        // マイページに戻る（更新成功メッセージ付き）
                        header("Location: " . $_SERVER['PHP_SELF'] . "?updated=true");
                        exit();
                    } else {
                        error_log("DB Error: " . $conn->error);
                        $message = "システムエラーが発生しました。もう一度お試しください。";
                        $current_page_state = 'edit_form'; // 更新失敗時は編集フォームに戻す
                        $user_data_for_form = $_SESSION['form_data']; // 入力内容を保持
                    }
                    $stmt_update->close();
                } else {
                    $message = "不正なアクセスです。最初からやり直してください。";
                    $current_page_state = 'mypage';
                }
                break;
            case 'back_to_edit':
                // 確認ページから「入力内容を修正する」ボタン押下
                $current_page_state = 'edit_form';
                // $_SESSION['form_data'] には直前の入力内容が保存されているのでそれをそのまま利用
                $user_data_for_form = $_SESSION['form_data'];
                break;

            // case 'back_to_password_input': // このケースは新しい「back_to_mypage_from_edit_form」に置き換えられます
            //     // 編集フォームから「パスワード入力に戻る」ボタン押下
            //     unset($_SESSION['form_data']); // フォームデータをクリア
            //     $current_page_state = 'password_input';
            //     break;

            case 'back_to_mypage_from_password':
                // パスワード入力から「マイページに戻る」ボタン押下
                unset($_SESSION['form_data']); // フォームデータをクリア
                $current_page_state = 'mypage';
                break;

            case 'back_to_mypage_from_edit_form':
                // 編集フォームから「マイページに戻る」ボタン押下 (新規追加)
                unset($_SESSION['form_data']); // フォームデータをクリア
                $current_page_state = 'mypage';
                break;

            default:
                // 不明なアクションの場合、マイページに戻る
                $current_page_state = 'mypage';
                unset($_SESSION['form_data']);
                break;
        }
    } else {
        // actionが設定されていないPOSTリクエストは、通常はマイページにリダイレクトするか、
        // エラーメッセージを表示して現在の状態を維持する
        $current_page_state = 'mypage';
        // デフォルトでマイページに戻す
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ユーザー情報</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background-color: #f7f9fc;
            color: #333;
            padding: 0; /* ヘッダー分を調整 */
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh; /* 画面全体を使う */
        }

        /* 新しいヘッダーとCSS */
        header {
            background-color: #333;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            /* 幅を100%に */
            box-sizing: border-box;
            /* paddingを幅に含める */
            margin-bottom: 20px;
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
        /* ヘッダー内のナビゲーションリンクのスタイル */
        header nav a:hover {
            text-decoration: none;
            /* アンダーラインを削除 */
        }

        /* ----- 以下、既存のスタイル ----- */
        h1, h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .required-note {
            text-align: center;
            color: #555;
            margin-bottom: 20px;
            font-size: 0.9em;
            white-space: nowrap;
        }

        form, .container {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 720px;
            width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
        }

        p {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }

        label {
            font-weight: bold;
            display: flex;
            align-items: center;
            width: auto;
            flex-shrink: 0;
            flex-grow: 0;
            white-space: nowrap;
        }

        label.required::after {
            content: " *";
            color: red;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            flex-grow: 1;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus,
        select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 3px rgba(52, 152, 219, 0.5);
        }

        button {
            padding: 10px 20px;
            background-color: #27ae60;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-right: 10px;
            /* ボタン間のスペース */
        }

        button:hover {
            background-color: #2980b9;
        }

        .error {
            color: red;
            margin-left: 0;
            flex-grow: 1;
            white-space: normal;
        }

        p.name-group {
            flex-direction: row;
            align-items: center;
            gap: 15px;
        }

        .input-wrapper {
            display: flex;
            gap: 5px;
            flex-grow: 1;
            width: auto;
        }
        .input-wrapper input {
            flex: 1;
        }

        .input-group {
            display: flex;
            gap: 5px;
            align-items: center;
            flex-grow: 1;
            width: 100%;
        }
        .input-group input {
            flex-grow: 1;
            width: auto;
        }

        p.vertical-fields {
            flex-direction: column;
            align-items: flex-start;
        }
        p.vertical-fields > div {
            width: 100%;
        }

        .hidden {
            display: none;
        }

        .confirmation-table, .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .confirmation-table th, .confirmation-table td,
        .info-table th, .info-table td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }

        .confirmation-table th, .info-table th {
            background-color: #f0f0f0;
            width: 35%;
        }

        .button-group {
            text-align: center;
            margin-top: 30px;
        }

        .button-group button, .button-group a {
            padding: 12px 25px;
            margin: 0 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .button-group button.edit-button,
        .button-group a.edit-button {
            background-color: #3498db;
            color: white;
        }

        .button-group button.edit-button:hover,
        .button-group a.edit-button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .button-group button.logout-button,
        .button-group a.logout-button {
            background-color: #e74c3c;
            color: white;
        }

        .button-group button.logout-button:hover,
        .button-group a.logout-button {
            background-color: #c0392b;
            transform: translateY(-2px);
        }

        .message {
            margin-bottom: 20px;
            padding: 8px 15px; /* パディングを少し小さく調整 */
            border-radius: 8px;
            font-weight: bold;
            display: inline-block; /* 内容の幅に合わせる */
            box-sizing: border-box;
            /* パディングを幅に含める */
            max-width: 100%;
            /* 親要素からはみ出さないように */
            white-space: normal;
            /* テキストが折り返せるように */
            text-align: left;
            /* テキストアラインメントを左に */
        }
        /* メッセージ表示を中央に寄せたい場合は、メッセージを囲む親要素でtext-align: center;
         を中央に設定する */
        /* 例えば、以下の様なdivで囲む */
        /* <div style="text-align: center;"> */
        /* <p class="message ...">...</p> */
        /* </div> */


        .message.success {
            background-color: #e6ffe6;
            color: #28a745;
            border: 1px solid #28a745;
        }

        .message.error {
            background-color: #ffe6e6;
            color: #dc3545;
            border: 1px solid #dc3545;
        }

        .password-container {
            position: relative;
            width: 100%;
            display: flex;
            align-items: center;
        }

        .password-container input {
            padding-right: 35px;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            cursor: pointer;
            color: #555;
        }
    </style>
</head>

<body>
    <header>
        <h1><a href="top.php">絵画通販サイト</a></h1>
        <nav>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>">マイページ</a> <a href="logout.php">ログアウト</a>
            <?php else: ?>
                <a href="login.php">ログイン</a>
                <a href="register.php">新規登録</a>
            <?php endif; ?>
            <a href="history.cgi">購入履歴</a>
        </nav>
    </header>

    <h1>ユーザー情報</h1>
    <?php if ($message): ?>
        <div style="text-align: center; width: 100%;">
            <p class="message <?php echo (isset($_GET['updated']) && $_GET['updated'] === 'true') ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($current_page_state === 'mypage'): // マイページ表示 ?>
        <div class="container">
            <table class="info-table">
                <tr>
                    <th>ユーザー名</th>
                    <td><?php echo htmlspecialchars($db_user_data['user_name']); ?></td>
                </tr>
                <tr>
                    <th>氏名</th>
                    <td><?php echo htmlspecialchars($db_user_data['last_name'] . ' ' . $db_user_data['first_name']); ?></td>
                </tr>
                <tr>
                    <th>フリガナ</th>
                    <td><?php echo htmlspecialchars($db_user_data['last_name_kana'] . ' ' . $db_user_data['first_name_kana']); ?></td>
                </tr>
                <tr>
                    <th>メールアドレス</th>
                    <td><?php echo htmlspecialchars($db_user_data['email']); ?></td>
                </tr>
                <tr>
                    <th>電話番号</th>
                    <td><?php echo htmlspecialchars($db_user_data['phone']); ?></td>
                </tr>
                <tr>
                    <th>郵便番号</th>
                    <td><?php echo htmlspecialchars($db_user_data['postal_code']); ?></td>
                </tr>
                <tr>
                    <th>住所</th>
                    <td><?php echo htmlspecialchars($db_user_data['pref'] . $db_user_data['city'] . $db_user_data['street'] . ($db_user_data['building'] ? ' ' . $db_user_data['building'] : '')); ?></td>
                </tr>
                <tr>
                    <th>支払い方法</th>
                    <td>
                        <?php
                            $payment_method = '';
                            switch ($db_user_data['payment']) {
                                case 'credit':
                                    $payment_method = 'クレジットカード';
                                    if ($db_user_data['card_number']) {
                                        // カード番号の安全な表示 (下4桁のみ)
                                        $payment_method .= ' (下4桁: ' . substr($db_user_data['card_number'], -4) . ')';
                                    }
                                    break;
                                case 'bank':
                                    $payment_method = '銀行振込';
                                    if ($db_user_data['bank_name']) {
                                        $payment_method .= ' (' .  htmlspecialchars($db_user_data['bank_name']) . ')';
                                    }
                                    break;
                                case 'cod':
                                    $payment_method = '代金引換';
                                    break;
                                case 'konbini':
                                    $payment_method = 'コンビニ払い';
                                    break;
                                default:
                                    $payment_method = '未設定';
                            }
                            echo htmlspecialchars($payment_method);
                        ?>
                    </td>
                </tr>
            </table>

            <div class="button-group">
                <form method="post" action="">
                    <button type="submit" name="action" value="edit_profile_start" class="edit-button">登録内容を修正する</button>
                    <a href="logout.php" class="logout-button">ログアウト</a>
                </form>
            </div>
        </div>

    <?php elseif ($current_page_state === 'password_input'): // パスワード入力フォーム表示 ?>
        <h2>現在のパスワードを入力してください</h2>
        <form method="post" action="">
            <input type="hidden" name="action" value="verify_password">
            <p>
                <label class="required">現在のパスワード：</label>
                <div class="password-container">
                    <input id="current_password_input" name="current_password" type="password" required placeholder="現在のパスワード">
                    <span class="toggle-password" onclick="togglePasswordVisibility('current_password_input')">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </p>
            <p style="justify-content: center;">
                <button type="submit">次へ</button>
            </p>
        </form>
        <form method="post" action="" style="text-align: center; margin-top: 10px;">
            <button type="submit" name="action" value="back_to_mypage_from_password" style="background-color: #6c757d;">マイページに戻る</button>
        </form>

    <?php elseif ($current_page_state === 'edit_form'): // 登録情報編集フォーム表示 ?>
        <h2>登録情報編集</h2>
        <p class="required-note">*は必須項目です</p>

        <form method="post" action="" onsubmit="return validateEditForm();">
            <input type="hidden" name="action" value="confirm_edit">
            <?php $formData = $_SESSION['form_data'] ?? $user_data_for_form; ?>

            <p class="name-group"> <label class="required">氏名：</label>
                <div class="input-wrapper">
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($formData['last_name'] ?? ''); ?>" required placeholder="例：山田">
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>" required placeholder="例：太郎">
                </div>
            </p>
            <p class="name-group"> <label class="required">フリガナ：</label>
                <div class="input-wrapper">
                    <input type="text" name="last_name_kana" value="<?php echo htmlspecialchars($formData['last_name_kana'] ?? ''); ?>" required pattern="[\u30A0-\u30FFー]+" placeholder="例：ヤマダ">
                    <input type="text" name="first_name_kana" value="<?php echo htmlspecialchars($formData['first_name_kana'] ?? ''); ?>" required pattern="[\u30A0-\u30FFー]+" placeholder="例：タロウ">
                </div>
            </p>
            <p>
                <label class="required">電話番号：</label>
                <div class="input-group">
                    <input type="text" name="phone_area" value="<?php echo htmlspecialchars($formData['phone_area'] ?? ''); ?>" required pattern="\d+" placeholder="例：090"> -
                    <input type="text" name="phone_local" value="<?php echo htmlspecialchars($formData['phone_local'] ?? ''); ?>" required pattern="\d+" placeholder="例：1234"> -
                    <input type="text" name="phone_sub" value="<?php echo htmlspecialchars($formData['phone_sub'] ?? ''); ?>" required pattern="\d+" placeholder="例：5678">
                </div>
            </p>
            <p>
                <label class="required">郵便番号：</label>
                <div class="input-group">
                    <input type="text" name="postal1" id="postal1" value="<?php echo htmlspecialchars($formData['postal1'] ?? ''); ?>" required pattern="\d{3}" maxlength="3" placeholder="例：123"> -
                    <input type="text" name="postal2" id="postal2" value="<?php echo htmlspecialchars($formData['postal2'] ?? ''); ?>" required pattern="\d{4}" maxlength="4" placeholder="例：4567">
                    <button type="button" onclick="searchAddress()" style="background-color: #6c757d;">住所検索</button>
                </div>
            </p>
            <p>
                <label class="required">都道府県：</label>
                <select id="pref" name="pref" required>
                    <option value="">都道府県を選択してください</option>
                    <?php
                    $prefs = [
                        "北海道", "青森県", "岩手県", "宮城県", "秋田県", "山形県", "福島県", "茨城県", "栃木県",
                        "群馬県", "埼玉県", "千葉県", "東京都", "神奈川県", "新潟県", "富山県", "石川県", "福井県",
                        "山梨県", "長野県", "岐阜県", "静岡県", "愛知県", "三重県", "滋賀県", "京都府", "大阪府",
                        "兵庫県", "奈良県", "和歌山県", "鳥取県", "島根県", "岡山県", "広島県", "山口県", "徳島県",
                        "香川県", "愛媛県", "高知県", "福岡県", "佐賀県", "長崎県", "熊本県", "大分県", "宮崎県",
                        "鹿児島県", "沖縄県"
                    ];
                    foreach ($prefs as $p) {
                        $selected = (($formData['pref'] ?? '') === $p) ? 'selected' : '';
                        echo "<option value=\"{$p}\" {$selected}>{$p}</option>";
                    }
                    ?>
                </select>
            </p>
            <p><label class="required">市区町村：</label><input type="text" id="city" name="city" value="<?php echo htmlspecialchars($formData['city'] ?? ''); ?>" required placeholder="例：〇〇市〇〇町"></p>
            <p><label class="required">丁目・番地：</label><input type="text" id="street" name="street" value="<?php echo htmlspecialchars($formData['street'] ?? ''); ?>" required placeholder="例：1-2-3"></p>
            <p><label>建物名（任意）：</label><input type="text" name="building" value="<?php echo htmlspecialchars($formData['building'] ?? ''); ?>" placeholder="例：〇〇マンション 101号室"></p>
            <p><label class="required">メールアドレス：</label><input type="email" name="email" value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>" required placeholder="例：your.email@example.com"></p>
            <p><label class="required">ユーザー名：</label><input type="text" name="user_name" value="<?php echo htmlspecialchars($formData['user_name'] ?? ''); ?>" required placeholder="半角英数字で入力してください"></p>
            <p>
                <label>新しいパスワード（変更する場合のみ入力）：</label>
                <div class="password-container">
                    <input id="password" name="password" type="password" placeholder="8文字以上">
                    <span class="toggle-password" onclick="togglePasswordVisibility('password')">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </p>
            <p>
                <label>新しいパスワード確認用：</label>
                <div class="password-container">
                    <input id="password_conf" name="password_conf" type="password" placeholder="パスワードを再入力してください">
                    <span class="toggle-password" onclick="togglePasswordVisibility('password_conf')">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                <span id="pwError" class="error"></span>
            </p>
            <p class="vertical-fields">
                <label class="required">支払い方法：</label>
                <select id="payment" name="payment" onchange="togglePaymentFields()" required>
                    <option value="">支払い方法を選択してください</option>
                    <option value="card" <?php echo (($formData['payment'] ?? '') === 'card') ? 'selected' : ''; ?>>クレジットカード</option>
                    <option value="bank" <?php echo (($formData['payment'] ?? '') === 'bank') ? 'selected' : ''; ?>>銀行振込</option>
                    <option value="cod" <?php echo (($formData['payment'] ?? '') === 'cod') ? 'selected' : ''; ?>>代金引換</option>
                    <option value="konbini" <?php echo (($formData['payment'] ?? '') === 'konbini') ? 'selected' : ''; ?>>コンビニ払い</option>
                </select>
            </p>
            <div id="creditFields" class="hidden">
                <p><label class="required">カード番号：</label><input type="text" name="card_number" value="<?php echo htmlspecialchars($formData['card_number'] ?? ''); ?>" pattern="[0-9]{13,16}" placeholder="例：1234567890123456"></p>
                <p>
                    <label class="required">有効期限：</label>
                    <div class="input-group">
                        <input type="text" name="expiry_month" placeholder="MM" value="<?php echo htmlspecialchars($formData['expiry_month'] ?? ''); ?>" pattern="^(0[1-9]|1[0-2])$" maxlength="2" size="2" placeholder="例：01"> /
                        <input type="text" name="expiry_year" placeholder="YY" value="<?php echo htmlspecialchars($formData['expiry_year'] ?? ''); ?>" pattern="^\d{2}$" maxlength="2" size="2" placeholder="例：25">
                    </div>
                </p>
                <p><label class="required">CVC：</label><input type="text" name="cvc" value="<?php echo htmlspecialchars($formData['cvc'] ?? ''); ?>" pattern="\d{3}" placeholder="例：123"></p>
            </div>
            <div id="bankFields" class="hidden">
                <p><label class="required">銀行名：</label><input type="text" name="bank_name" value="<?php echo htmlspecialchars($formData['bank_name'] ?? ''); ?>" placeholder="例：〇〇銀行"></p>
                <p><label class="required">支店名：</label><input type="text" name="branch_name" value="<?php echo htmlspecialchars($formData['branch_name'] ?? ''); ?>" placeholder="例：〇〇支店"></p>
                <p><label class="required">口座番号：</label><input type="text" name="account_number" value="<?php echo htmlspecialchars($formData['account_number'] ?? ''); ?>" pattern="\d+" placeholder="例：1234567"></p>
            </div>
            <p style="justify-content: center;">
                <button type="submit">確認</button>
                <button type="submit" name="action" value="back_to_mypage_from_edit_form" style="background-color: #6c757d;">マイページに戻る</button>
            </p>
        </form>

    <?php elseif ($current_page_state === 'confirmation_page' && isset($_SESSION['form_data'])): // 確認ページ表示 ?>
        <h2>入力内容の確認</h2>
        <table class="confirmation-table">
            <?php
            $data = $_SESSION['form_data'];
            $displayData = [
                '氏名' => htmlspecialchars($data['last_name'] . ' ' . $data['first_name']),
                'フリガナ' => htmlspecialchars($data['last_name_kana'] . ' ' . $data['first_name_kana']),
                '電話番号' => htmlspecialchars($data['phone_area'] . '-' . $data['phone_local'] . '-' . $data['phone_sub']),
                '郵便番号' => htmlspecialchars($data['postal1'] . '-' . $data['postal2']),
                '都道府県' => htmlspecialchars($data['pref']),
                '市区町村' => htmlspecialchars($data['city']),
                '丁目・番地' => htmlspecialchars($data['street']),
                '建物名' => htmlspecialchars($data['building'] ?: 'なし'),
                'メールアドレス' => htmlspecialchars($data['email']),
                'ユーザー名' => htmlspecialchars($data['user_name']),
            ];
            // 新しいパスワードが入力されていれば表示
            if (!empty($data['new_password'])) {
                $displayData['新しいパスワード'] = '******** (変更されます)';
            } else {
                $displayData['パスワード'] = '変更なし';
            }

            switch ($data['payment']) {
                case 'card':
                    $displayData['支払い方法'] = 'クレジットカード';
                    $displayData['カード番号'] = htmlspecialchars($data['card_number']);
                    $displayData['有効期限'] = htmlspecialchars($data['expiry_month'] . '/' . $data['expiry_year']);
                    $displayData['CVC'] = '***';
                    break;
                case 'bank':
                    $displayData['支払い方法'] = '銀行振込';
                    $displayData['銀行名'] = htmlspecialchars($data['bank_name']);
                    $displayData['支店名'] = htmlspecialchars($data['branch_name']);
                    $displayData['口座番号'] = htmlspecialchars($data['account_number']);
                    break;
                case 'cod':
                    $displayData['支払い方法'] = '代金引換';
                    break;
                case 'konbini':
                    $displayData['支払い方法'] = 'コンビニ払い';
                    break;
            }

            foreach ($displayData as $label => $value) {
                echo "<tr><th>{$label}</th><td>{$value}</td></tr>";
            }
            ?>
        </table>

        <form method="post" action="">
            <button type="submit" name="action" value="update_execute">確定</button>
            <button type="submit" name="action" value="back_to_edit" style="background-color: #6c757d;">入力内容を修正する</button>
        </form>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 現在の状態が編集フォームの場合のみ支払い方法フィールドの表示を切り替える
            // フォームの 'action' input要素が存在するかで判別
            const actionInput = document.querySelector('input[name="action"]');
            if (actionInput && actionInput.value === 'confirm_edit') { // 編集フォームからの送信時
                 togglePaymentFields();
            } else if (document.getElementById('payment')) { // 編集フォームがレンダリングされている場合
                togglePaymentFields();
            }
        });
        function validateEditForm() {
            const newPw = document.getElementById('password');
            const newPwConf = document.getElementById('password_conf');
            const payment = document.getElementById('payment').value;
            const pwError = document.getElementById('pwError');

            pwError.textContent = '';
            // 新しいパスワードが入力されている場合のみ検証
            if (newPw.value !== '' || newPwConf.value !== '') {
                if (newPw.value.length < 8) {
                    pwError.textContent = '新しいパスワードは8文字以上で入力してください。';
                    return false;
                }
                if (newPw.value !== newPwConf.value) {
                    pwError.textContent = '新しいパスワードが一致しません。';
                    return false;
                }
            }

            // 支払い方法が選択されているか確認
            if (!payment) {
                alert("支払い方法を選択してください");
                return false;
            }

            const creditFields = document.getElementById('creditFields');
            const bankFields = document.getElementById('bankFields');

            // 必須フィールドのバリデーションはHTMLのrequired属性とpattern属性に任せる
            // ただし、JavaScript側でより詳細なチェックが必要な場合
            if (payment === 'card') {
                const cardNumberInput = document.querySelector('#creditFields input[name="card_number"]');
                const expiryMonthInput = document.querySelector('#creditFields input[name="expiry_month"]');
                const expiryYearInput = document.querySelector('#creditFields input[name="expiry_year"]');
                const cvcInput = document.querySelector('#creditFields input[name="cvc"]');
                if (cardNumberInput && !cardNumberInput.checkValidity()) {
                    alert('カード番号は13桁から16桁の数字で入力してください。');
                    return false;
                }
                if (expiryMonthInput && expiryYearInput && (!expiryMonthInput.checkValidity() || !expiryYearInput.checkValidity())) {
                    alert('有効期限はMM/YY形式で入力してください。(例: 01/25)');
                    return false;
                }
                if (cvcInput && !cvcInput.checkValidity()) {
                    alert('CVCは3桁または4桁の数字で入力してください。');
                    return false;
                }

            } else if (payment === 'bank') {
                const bankNameInput = document.querySelector('#bankFields input[name="bank_name"]');
                const branchNameInput = document.querySelector('#bankFields input[name="branch_name"]');
                const accountNumberInput = document.querySelector('#bankFields input[name="account_number"]');
                if (bankNameInput && !bankNameInput.value) {
                    alert('銀行名を入力してください。');
                    return false;
                }
                if (branchNameInput && !branchNameInput.value) {
                    alert('支店名を入力してください。');
                    return false;
                }
                if (accountNumberInput && !accountNumberInput.checkValidity()) {
                    alert('口座番号は数字で入力してください。');
                    return false;
                }
            }
            return true;
            // すべてのバリデーションをパスした場合
        }


        function togglePaymentFields() {
            const value = document.getElementById('payment').value;
            const creditFields = document.getElementById('creditFields');
            const bankFields = document.getElementById('bankFields');

            // 支払い方法に特化したフィールドをすべて非表示にし、'required'属性を削除
            creditFields.classList.add('hidden');
            bankFields.classList.add('hidden');
            [...creditFields.querySelectorAll('input')].forEach(el => el.removeAttribute('required'));
            [...bankFields.querySelectorAll('input')].forEach(el => el.removeAttribute('required'));

            // 選択された支払い方法に応じて関連フィールドを表示し、'required'属性を追加
            if (value === 'card') {
                creditFields.classList.remove('hidden');
                [...creditFields.querySelectorAll('input')].forEach(el => el.setAttribute('required', 'true'));
            } else if (value === 'bank') {
                bankFields.classList.remove('hidden');
                [...bankFields.querySelectorAll('input')].forEach(el => el.setAttribute('required', 'true'));
            }
        }

        function togglePasswordVisibility(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling ? input.nextElementSibling.querySelector('i') : null;

            if (!icon) return;
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function toHalfWidth(str) {
            if (str === null || str === undefined) return '';
            return str.replace(/[！-～]/g, s => String.fromCharCode(s.charCodeAt(0) - 0xFEE0)).replace(/　/g, ' ');
        }

        function searchAddress() {
            let part1 = toHalfWidth(document.getElementById('postal1').value.trim());
            let part2 = toHalfWidth(document.getElementById('postal2').value.trim());
            if (!part1.match(/^\d{3}$/) || !part2.match(/^\d{4}$/)) {
                alert('郵便番号は「3桁 - 4桁」の数字で入力してください');
                return;
            }
            const fullPostal = part1 + part2;
            const url = `https://zipcloud.ibsnet.co.jp/api/search?zipcode=${fullPostal}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 200 && data.results) {
                        const result = data.results[0];
                        document.getElementById('pref').value = result.address1;
                        document.getElementById('city').value = result.address2 + result.address3;
                        document.getElementById('street').value = ''; // 丁目・番地はクリアして手動入力させる
                    } else {
                        alert('住所が見つかりませんでした。郵便番号を確認してください。');
                        document.getElementById('pref').value = '';
                        document.getElementById('city').value = '';
                        document.getElementById('street').value = '';
                    }
                })
                .catch(error => {
                    console.error('Error fetching address:', error);
                    alert('住所検索中にエラーが発生しました。');
                });
        }
    </script>
</body>
</html>
