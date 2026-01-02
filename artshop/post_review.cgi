#!/usr/bin/python3
# -*- coding: utf-8 -*-

import cgi
# import cgitb # デバッグ時のみ有効化
import mysql.connector
import os
import http.cookies
import html
import sys

# デバッグモードを有効にする (本番環境では無効にしてください)
# cgitb.enable() # デバッグ時のみ有効化

# --- データベース接続設定 ---
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': 'passwordA1!',
    'database': 'artshop',
    'charset': 'utf8mb4'
}

# --- 汎用エラーページ表示関数 ---
def show_error_page(title, message, details=None, back_link_text="前のページに戻る", back_link_url="javascript:history.back()"):
    print("Content-type: text/html; charset=UTF-8\n") # エラーページ表示時はContent-typeが必要
    print(f"""
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{html.escape(title)} - 絵画通販サイト</title>
    <style>
        body {{ font-family: sans-serif; margin: 20px; text-align: center; background-color: #f4f4f4; }}
        .error-container {{ max-width: 600px; margin: 50px auto; padding: 30px; border: 1px solid #d9534f; border-radius: 8px; background-color: #fdf7f7; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }}
        h2 {{ color: #d9534f; }}
        p {{ color: #333; line-height: 1.6; }}
        a {{ color: #007bff; text-decoration: none; }}
        a:hover {{ text-decoration: underline; }}
    </style>
</head>
<body>
    <div class="error-container">
        <h2>{html.escape(title)}</h2>
        <p>{html.escape(message)}</p>
        {"<p style='font-size: 0.8em; color: #888;'>管理者向け情報: " + html.escape(str(details)) + "</p>" if details else ""}
        <p><a href="{html.escape(back_link_url)}">{html.escape(back_link_text)}</a></p>
    </div>
</body>
</html>
    """)
    sys.exit() # 修正: exit() を sys.exit() に変更 (既にsys import済み)

# --- ヘッダー出力 (リダイレクト用) ---
# print("Content-type: text/html; charset=UTF-8\n") # ★この行を削除★

# --- CGIフォームデータの取得 ---
form = cgi.FieldStorage()
artist_id = None
review_text = None

# artist_idの取得とエラーハンドリングをtry-exceptで囲む
try:
    artist_id = int(form.getfirst('artist_id'))
except (TypeError, ValueError): # NoneTypeの場合も考慮してTypeErrorを追加
    show_error_page("不正なリクエスト", "作者IDが正しく指定されていません。", details=form.getfirst('artist_id'))

if 'review_text' in form:
    review_text = form.getfirst('review_text') # getvalueからgetfirstに変更（単一値取得のため）

# --- ユーザーIDの取得 (PHPのセッションIDを使ってDBから取得) ---
cookies = http.cookies.SimpleCookie(os.environ.get("HTTP_COOKIE", "")) # HTTP_COOKIEが存在しない場合を考慮
php_session_id = None
if "PHPSESSID" in cookies:
    php_session_id = cookies["PHPSESSID"].value

user_id = None
conn = None
cursor = None
try:
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)

    if php_session_id:
        stmt_user = "SELECT user_id FROM User WHERE session_id = %s"
        cursor.execute(stmt_user, (php_session_id,))
        user_data = cursor.fetchone()
        if user_data:
            user_id = user_data['user_id']

except mysql.connector.Error as err:
    show_error_page(
        "データベース接続エラー",
        "現在、データベースに接続できません。サイト管理者にお問い合わせください。",
        details=err
    )
finally:
    if cursor:
        cursor.close()
    if conn and conn.is_connected():
        conn.close()

# ログインチェック & 必須パラメータの確認を細分化
if user_id is None:
    print("Location: login.php\n") # ログインページへリダイレクト
    sys.exit() # 修正: exit() を sys.exit() に変更
elif artist_id is None: # このブロックは上のtry-exceptで既に捕捉されるはずだが、念のため
    show_error_page(
        "情報不足",
        "レビュー対象の作者が指定されていません。",
        details="Missing artist_id."
    )
elif review_text is None or not review_text.strip():
    show_error_page(
        "情報不足",
        "レビューの内容が入力されていません。",
        details="Missing or empty review_text."
    )

# --- データベースにレビューを挿入 ---
try:
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor()

    stmt_insert = "INSERT INTO Review (user_id, artist_id, text) VALUES (%s, %s, %s)"
    cursor.execute(stmt_insert, (user_id, artist_id, review_text))
    conn.commit()

except mysql.connector.Error as err:
    if conn and conn.is_connected():
        conn.rollback()
    show_error_page(
        "レビュー投稿エラー",
        "レビューの投稿中に問題が発生しました。再度お試しください。",
        details=err,
        back_link_text="作者ページに戻る",
        back_link_url=f"./artist.cgi?artist_id={artist_id}"
    )
finally:
    if cursor:
        cursor.close()
    if conn and conn.is_connected():
        conn.close()

# レビュー投稿後、作者ページに戻る
# Locationヘッダーの前にContent-typeなど余分な出力がないことを確認することが重要
print("Status: 302 Found")
print(f"Location: ./artist.cgi?artist_id={artist_id}\n")
sys.exit()