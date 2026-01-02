<?php
session_start();
include 'db_config.php';

$message = '';
$display_form = true;
$is_confirmation_page = false;

function getPostData($key, $default = null) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $last_name = getPostData('last_name');
    $first_name = getPostData('first_name');
    $last_name_kana = getPostData('last_name_kana');
    $first_name_kana = getPostData('first_name_kana');
    $phone_area = getPostData('phone_area');
    $phone_local = getPostData('phone_local');
    $phone_sub = getPostData('phone_sub');
    $phone = $phone_area . '-' . $phone_local . '-' . $phone_sub;
    $postal1 = getPostData('postal1');
    $postal2 = getPostData('postal2');
    $postal_code = $postal1 . '-' . $postal2;
    $pref = getPostData('pref');
    $city = getPostData('city');
    $street = getPostData('street');
    $building = getPostData('building');
    $email = getPostData('email');
    $user_name = getPostData('user_name');
    $raw_password = getPostData('password');
    $payment = getPostData('payment');

    $card_number = getPostData('card_number');
    $expiry_month = getPostData('expiry_month');
    $expiry_year = getPostData('expiry_year');
    $cvc = getPostData('cvc');

    $bank_name = getPostData('bank_name');
    $branch_name = getPostData('branch_name');
    $account_number = getPostData('account_number');

    if (isset($_POST['confirm'])) {
        $_SESSION['form_data'] = [
            'last_name' => $last_name, 'first_name' => $first_name, 'last_name_kana' => $last_name_kana, 'first_name_kana' => $first_name_kana,
            'phone_area' => $phone_area, 'phone_local' => $phone_local, 'phone_sub' => $phone_sub,
            'postal1' => $postal1, 'postal2' => $postal2, 'pref' => $pref, 'city' => $city, 'street' => $street, 'building' => $building,
            'email' => $email, 'user_name' => $user_name, 'password' => $raw_password, 'payment' => $payment,
            'card_number' => $card_number, 'expiry_month' => $expiry_month, 'expiry_year' => $expiry_year, 'cvc' => $cvc,
            'bank_name' => $bank_name, 'branch_name' => $branch_name, 'account_number' => $account_number,
        ];

        $user_name_check = $_SESSION['form_data']['user_name'];
        $stmt_check = $conn->prepare("SELECT user_id FROM User WHERE user_name = ?");
        $stmt_check->bind_param("s", $user_name_check);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $message = "このユーザー名は既に存在します。別のユーザー名をお試しください。";
            $display_form = true;
        } else {
            $is_confirmation_page = true;
            $display_form = false;
        }
        $stmt_check->close();

    } elseif (isset($_POST['register']) && isset($_SESSION['form_data'])) {
        $data = $_SESSION['form_data'];

        $last_name = $data['last_name'];
        $first_name = $data['first_name'];
        $last_name_kana = $data['last_name_kana'];
        $first_name_kana = $data['first_name_kana'];
        $phone = $data['phone_area'] . '-' . $data['phone_local'] . '-' . $data['phone_sub'];
        $postal_code = $data['postal1'] . '-' . $data['postal2'];
        $pref = $data['pref'];
        $city = $data['city'];
        $street = $data['street'];
        $building = $data['building'];
        $email = $data['email'];
        $user_name = $data['user_name'];
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $payment = $data['payment'];

        $card_number = $data['card_number'] ?? null;
        $expiry_month = $data['expiry_month'] ?? null;
        $expiry_year = $data['expiry_year'] ?? null;
        $cvc = $data['cvc'] ?? null;

        $bank_name = $data['bank_name'] ?? null;
        $branch_name = $data['branch_name'] ?? null;
        $account_number = $data['account_number'] ?? null;

        $stmt_insert = $conn->prepare("
            INSERT INTO User (
                user_name, password, session_id, last_name, first_name, last_name_kana, first_name_kana,
                phone, postal_code, pref, city, street, building, email, payment,
                card_number, expiry_month, expiry_year, cvc,
                bank_name, branch_name, account_number
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $empty_session_id = '';
        $stmt_insert->bind_param(
            "ssssssssssssssssssssss",
            $user_name, $password, $empty_session_id, // user_name, password, session_id
            $last_name, $first_name, $last_name_kana, $first_name_kana,
            $phone, $postal_code, $pref, $city, $street, $building, $email, $payment,
            $card_number, $expiry_month, $expiry_year, $cvc,
            $bank_name, $branch_name, $account_number
        );

        if ($stmt_insert->execute()) {
            unset($_SESSION['form_data']);
            header("Location: login.php?registered=true");
            exit();
        } else {
            error_log("DB Error: " . $conn->error);
            $message = "システムエラーが発生しました。もう一度お試しください。";
            $display_form = true;
        }
        $stmt_insert->close();

    } elseif (isset($_POST['back'])) {
        $display_form = true;
    }
} else {
    unset($_SESSION['form_data']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>新規ユーザー登録</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background-color: #f7f9fc;
            color: #333;
            padding: 40px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        h1, h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        /* 必須項目メッセージのスタイル */
        .required-note {
            text-align: center;
            color: #555;
            margin-bottom: 20px;
            font-size: 0.9em;
            white-space: nowrap; /* これで「*は必須項目です」の改行がなくなります */
        }

        form {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 720px;
            width: 100%;
            margin: 0 auto;
        }

        /* 修正点: p要素のデフォルトをflex-rowにする */
        p {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column; /* デフォルトでラベルと入力欄を縦に並べる */
            align-items: flex-start; /* 左寄せ */
            gap: 5px; /* ラベルと入力欄の間隔 */
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
            content: " *"; /* ラベルテキストとアスタリスクの間にスペース */
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
            width: 100%; /* 親要素の幅いっぱいに広げる */
            box-sizing: border-box; /* パディングとボーダーを幅に含める */
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
            flex-direction: row; /* ラベルと入力欄のグループを横並びにする */
            align-items: center; /* 垂直方向中央揃え */
            gap: 15px; /* ラベルとinput-wrapperの間隔 */
        }

        /* 氏名、フリガナの入力フィールド（姓・名）を横並びにするためのラッパー */
        .input-wrapper {
            display: flex;
            gap: 5px;
            flex-grow: 1;
            width: auto; 
        }
        .input-wrapper input {
            flex: 1; /* 姓と名が均等に幅を占める */
        }

        /* 電話番号、郵便番号、有効期限の入力グループ（ハイフンなどを挟む場合） */
        .input-group {
            display: flex;
            gap: 5px;
            align-items: center;
            flex-grow: 1;
            width: 100%; /* 親要素の幅いっぱいに広げる */
        }
        .input-group input {
            flex-grow: 1;
            width: auto;
        }
        
        /* 支払い方法のグループなど、一部のp要素を列方向にする場合 */
        p.vertical-fields {
            flex-direction: column;
            align-items: flex-start;
        }
        /* 支払い方法の内部のhidden divは別途調整 */
        p.vertical-fields > div {
            width: 100%; /* 親の幅に合わせる */
        }


        .hidden {
            display: none;
        }

        .confirmation-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .confirmation-table th, .confirmation-table td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }

        .confirmation-table th {
            background-color: #f0f0f0;
            width: 35%;
        }

        a {
            color: #3498db;
            text-decoration: none;
            margin-left: 10px;
        }

        a:hover {
            text-decoration: underline;
        }

        /* パスワード表示トグル用のスタイル */
        .password-container {
            position: relative;
            width: 100%;
            display: flex; /* inputとアイコンを横並びにするためにflexを使用 */
            align-items: center;
        }

        .password-container input {
            padding-right: 35px; /* アイコン分のスペースを確保 */
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
    <h1>会員登録フォーム</h1>
    <?php if ($message): ?>
        <p class="error"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if ($display_form): ?>
        <p class="required-note">*は必須項目です</p>
        
        <form method="post" action="" onsubmit="return validateForm();">
            <input type="hidden" name="confirm" value="1">
            <?php $formData = $_SESSION['form_data'] ?? []; ?>
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
                    <button type="button" onclick="searchAddress()">住所検索</button>
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
                <label class="required">パスワード：</label>
                <div class="password-container">
                    <input id="password" name="password" type="password" value="<?php echo htmlspecialchars($formData['password'] ?? ''); ?>" required placeholder="8文字以上">
                    <span class="toggle-password" onclick="togglePasswordVisibility('password')">
                        <i class="fas fa-eye"></i> </span>
                </div>
            </p>
            <p>
                <label class="required">確認用パスワード：</label>
                <div class="password-container">
                    <input id="password_conf" name="password_conf" type="password" value="<?php echo htmlspecialchars($formData['password'] ?? ''); ?>" required placeholder="パスワードを再入力してください">
                    <span class="toggle-password" onclick="togglePasswordVisibility('password_conf')">
                        <i class="fas fa-eye"></i> </span>
                </div>
                <span id="pwError" class="error"></span>
            </p>
            <p class="vertical-fields">
                <label class="required">支払い方法：</label>
                <select id="payment" name="payment" onchange="togglePaymentFields()" required>
                    <option value="">支払い方法を選択してください</option>
                    <option value="credit" <?php echo (($formData['payment'] ?? '') === 'credit') ? 'selected' : ''; ?>>クレジットカード</option>
                    <option value="bank" <?php echo (($formData['payment'] ?? '') === 'bank') ? 'selected' : ''; ?>>銀行振込</option>
                    <option value="cod" <?php echo (($formData['payment'] ?? '') === 'cod') ? 'selected' : ''; ?>>代金引換</option>
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
            <p style="justify-content: center;"><button type="submit">確認</button></p>
            <p style="justify-content: center;"><a href="login.php">ログイン画面へ戻る</a></p>
        </form>

    <?php elseif ($is_confirmation_page && isset($_SESSION['form_data'])): ?>
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
                '支払い方法' => '',
            ];

            switch ($data['payment']) {
                case 'credit':
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
            }

            foreach ($displayData as $label => $value) {
                echo "<tr><th>{$label}</th><td>{$value}</td></tr>";
            }
            ?>
        </table>

        <form method="post" action="">
            <button type="submit" name="register">登録する</button>
            <button type="submit" name="back">入力内容を修正する</button>
        </form>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            togglePaymentFields();
        });

        function validateForm() {
            const pw = document.getElementById('password').value;
            const conf = document.getElementById('password_conf').value;
            const payment = document.getElementById('payment').value;
            const pwError = document.getElementById('pwError');

            pwError.textContent = '';

            if (pw !== conf) {
                pwError.textContent = 'パスワードが一致しません';
                return false;
            }

            // 支払い方法が選択されているか確認
            if (!payment) {
                alert("支払い方法を選択してください");
                return false;
            }

            const creditFields = document.getElementById('creditFields');
            const bankFields = document.getElementById('bankFields');

            if (payment === 'credit') {
                const cardNumberInput = document.querySelector('#creditFields input[name="card_number"]');
                const expiryMonthInput = document.querySelector('#creditFields input[name="expiry_month"]');
                const expiryYearInput = document.querySelector('#creditFields input[name="expiry_year"]');
                const cvcInput = document.querySelector('#creditFields input[name="cvc"]');

                if (cardNumberInput && cardNumberInput.hasAttribute('required') && !cardNumberInput.checkValidity()) {
                    alert('カード番号は13桁から16桁の数字で入力してください。');
                    return false;
                }
                if (expiryMonthInput && expiryYearInput && expiryMonthInput.hasAttribute('required') && (!expiryMonthInput.checkValidity() || !expiryYearInput.checkValidity())) {
                    alert('有効期限はMM/YY形式で入力してください。(例: 01/25)');
                    return false;
                }
                if (cvcInput && cvcInput.hasAttribute('required') && !cvcInput.checkValidity()) {
                    alert('CVCは3桁の数字で入力してください。');
                    return false;
                }

            } else if (payment === 'bank') {
                const bankNameInput = document.querySelector('#bankFields input[name="bank_name"]');
                const branchNameInput = document.querySelector('#bankFields input[name="branch_name"]');
                const accountNumberInput = document.querySelector('#bankFields input[name="account_number"]');

                if (bankNameInput && bankNameInput.hasAttribute('required') && !bankNameInput.value) {
                    alert('銀行名を入力してください。');
                    return false;
                }
                if (branchNameInput && branchNameInput.hasAttribute('required') && !branchNameInput.value) {
                    alert('支店名を入力してください。');
                    return false;
                }
                if (accountNumberInput && accountNumberInput.hasAttribute('required') && !accountNumberInput.checkValidity()) {
                    alert('口座番号は数字で入力してください。');
                    return false;
                }
            }

            return true;
        }

        function togglePaymentFields() {
            const value = document.getElementById('payment').value;
            const creditFields = document.getElementById('creditFields');
            const bankFields = document.getElementById('bankFields');

            creditFields.classList.add('hidden');
            bankFields.classList.add('hidden');
            // 'required'属性を削除する代わりに、HTML5バリデーションに影響を与えないようにします。
            // 支払い方法が選択されていない状態で送信されるのを防ぐため、selectedが空のときはrequiredをtrueに戻す
            [...creditFields.querySelectorAll('input')].forEach(el => el.removeAttribute('required'));
            [...bankFields.querySelectorAll('input')].forEach(el => el.removeAttribute('required'));

            if (value === 'credit') {
                creditFields.classList.remove('hidden');
                [...creditFields.querySelectorAll('input')].forEach(el => el.setAttribute('required', 'true'));
            } else if (value === 'bank') {
                bankFields.classList.remove('hidden');
                [...bankFields.querySelectorAll('input')].forEach(el => el.setAttribute('required', 'true'));
            }
        }

        // パスワード表示トグル機能
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
            // 全角記号、数字、英字、スペースを半角に変換
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
            fetch(`https://zipcloud.ibsnet.co.jp/api/search?zipcode=${fullPostal}`)
                .then(response => response.json())
                .then(data => {
                    if (data.results) {
                        const result = data.results[0];
                        document.getElementById('pref').value = result.address1;
                        document.getElementById('city').value = result.address2 + result.address3;
                    } else {
                        alert('該当する住所が見つかりません');
                    }
                })
                .catch(() => {
                    alert('住所検索に失敗しました');
                });
        }
    </script>
</body>
</html>
