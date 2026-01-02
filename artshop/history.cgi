#!/usr/bin/python3
# -*- coding: utf-8 -*-

import cgi
import cgitb
import mysql.connector
import os
import http.cookies
import html
import sys # Import sys for sys.exit()

# Debug mode (enable only for development, disable in production)
# cgitb.enable() # Commented out as per previous discussions for production environment

# --- Database Connection Configuration ---
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': 'passwordA1!',
    'database': 'artshop',
    'charset': 'utf8mb4'
}

# --- Generic Error Page Function ---
# This function will print a full HTML page, so it should handle its own Content-Type header.
def show_error_page(title, message, details=None, back_link_text="前のページに戻る", back_link_url="javascript:history.back()"):
    print("Content-type: text/html; charset=UTF-8\n") # Always print Content-type for an HTML error page
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
    sys.exit()

# --- Initial DB connection attempt (before any HTML output) ---
conn = None
cursor = None
db_connected = False
try:
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)
    db_connected = True
except mysql.connector.Error as err:
    # If initial DB connection fails, show an error page and exit.
    show_error_page(
        "データベース接続エラー",
        "現在、データベースに接続できません。サイト管理者にお問い合わせください。",
        err
    )

# --- Retrieve User ID (PHP session_id based on DB) ---
user_id = None
try:
    cookies = http.cookies.SimpleCookie(os.environ.get("HTTP_COOKIE", ""))
    php_session_id = None
    if "PHPSESSID" in cookies:
        php_session_id = cookies["PHPSESSID"].value

    if php_session_id:
        stmt_user = "SELECT user_id FROM User WHERE session_id = %s"
        cursor.execute(stmt_user, (php_session_id,))
        user_data = cursor.fetchone()
        if user_data:
            user_id = user_data['user_id']

except mysql.connector.Error as err:
    # If user ID retrieval fails (e.g., query error), show an error page and exit.
    show_error_page(
        "認証エラー",
        "ユーザー情報の取得中に問題が発生しました。再度ログインをお試しください。",
        err,
        "ログインページに戻る", "login.php"
    )
finally:
    # Close connection after user ID retrieval, so we can reconnect later if needed
    if cursor:
        cursor.close()
    if conn and conn.is_connected():
        conn.close()

# --- Login Check (Redirect if not logged in) ---
if user_id is None: # If user_id couldn't be obtained, redirect to login.php
    print("Status: 302 Found") # HTTP status for redirect
    print("Location: login.php\n") # The redirect URL
    sys.exit() # Essential to stop execution after sending redirect headers

# --- No other output before this point if redirect happens ---
# If we reach here, it means the user is logged in, and we can proceed with HTML output.
print("Content-type: text/html; charset=UTF-8\n") # Print Content-type ONLY if NOT redirecting

# --- Get CGI form data ---
form = cgi.FieldStorage()
search_artist_name = form.getvalue('search_artist', '')

# --- Reconnect to DB and Fetch Data ---
purchase_history = []
has_any_history = False 
conn = None
cursor = None
try:
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)
    
    if not search_artist_name:
        sql_check_all_history = f"""
            SELECT COUNT(*) FROM Trade WHERE user_id = {user_id}
        """
        cursor.execute(sql_check_all_history)
        if cursor.fetchone()['COUNT(*)'] > 0:
            has_any_history = True

    sql = f"""
        SELECT A.art_name, A.image_path, AR.artist_name, T.trade_date
        FROM Trade T
        JOIN Art A ON T.art_id = A.art_id
        JOIN Artist AR ON A.artist_id = AR.artist_id
        WHERE T.user_id = {user_id}
    """
    
    # SQL injection vulnerability (search functionality)
    if search_artist_name:
        sql += f" AND AR.artist_name LIKE '%{search_artist_name}%'"

    sql += " ORDER BY T.trade_date DESC"

    cursor.execute(sql)
    purchase_history = cursor.fetchall()

except mysql.connector.Error as err:
    # If data fetching fails, show an error page and exit.
    show_error_page(
        "データ取得エラー",
        "購入履歴の取得中に問題が発生しました。再度お試しください。",
        err,
        "マイページに戻る", "mypage.php"
    )
finally:
    if cursor:
        cursor.close()
    if conn and conn.is_connected():
        conn.close()

# --- HTML Output ---
print(f"""
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>購入履歴 - 絵画通販サイト</title>
    <style>
        body {{ font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }}
        header {{ background-color: #333; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; }}
        header h1 {{ margin: 0; font-size: 24px; }}
        header a {{ color: white; text-decoration: none; margin-left: 20px; }}
        .container {{ max-width: 900px; margin: 20px auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }}
        h2 {{ text-align: center; color: #333; margin-bottom: 30px; }}
        .search-form {{ margin-bottom: 20px; padding: 15px; background-color: #e9e9e9; border-radius: 5px; display: flex; justify-content: center; align-items: center; gap: 10px; }}
        .search-form input[type="text"] {{ padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 250px; }}
        .search-form input[type="submit"] {{ background-color: #007bff; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; }}
        .search-form input[type="submit"]:hover {{ background-color: #0056b3; }}
        .history-list {{ list-style: none; padding: 0; }}
        .history-item {{ display: flex; align-items: center; background-color: #f9f9f9; border: 1px solid #eee; padding: 15px; margin-bottom: 15px; border-radius: 8px; }}
        .history-item img {{ width: 100px; height: 100px; object-fit: cover; border-radius: 4px; margin-right: 15px; }}
        .item-details {{ flex-grow: 1; }}
        .item-details h3 {{ margin: 0 0 5px 0; color: #333; }}
        .item-details p {{ margin: 0; color: #666; font-size: 14px; }}
        .info-message {{ text-align: center; color: #555; margin-top: 20px; font-size: 1.1em; }}
    </style>
</head>
<body>
    <header>
        <h1><a href="top.php">絵画通販サイト</a></h1>
        <nav>
            <a href="top.php">トップ</a>
            <a href="mypage.php">マイページ</a>
            <a href="history.cgi">購入履歴</a>
            <a href="logout.php">ログアウト</a>
        </nav>
    </header>

    <div class="container">
        <h2>購入履歴</h2>

        <div class="search-form">
            <form action="history.cgi" method="get">
                <label for="search_artist">作者名で検索:</label>
                <input type="text" id="search_artist" name="search_artist" value="{html.escape(search_artist_name)}">
                <input type="submit" value="検索">
            </form>
        </div>

        <ul class="history-list">
""")

# --- Conditional logic for displaying messages ---
if purchase_history:
    for item in purchase_history:
        art_name = item['art_name']
        # Default image path. No changes to default value as requested.
        image_path = item['image_path'] if item['image_path'] else 'image/default_art.png'
        artist_name = item['artist_name']
        trade_date = item['trade_date']
        
        # HTML escaping (important for security)
        art_name_escaped = html.escape(str(art_name))
        image_path_escaped = html.escape(str(image_path))
        artist_name_escaped = html.escape(str(artist_name))
        trade_date_escaped = html.escape(str(trade_date)) if trade_date is not None else "不明" # Display "不明" if trade_date is NULL

        print(f"""
            <li class="history-item">
                <img src="{image_path_escaped}" alt="{art_name_escaped}">
                <div class="item-details">
                    <h3>{art_name_escaped}</h3>
                    <p>作者: {artist_name_escaped}</p>
                    <p>購入日時: {trade_date_escaped}</p>
                </div>
            </li>
        """)
else:
    if search_artist_name:
        print(f"""
            <p class="info-message">'{html.escape(search_artist_name)}' に一致する購入履歴は見つかりませんでした。</p>
        """)
    elif has_any_history:
        print("""
            <p class="info-message">指定された条件に合う購入履歴が見つかりませんでした。</p>
        """)
    else:
        print("""
            <p class="info-message">まだ購入履歴がありません。</p>
        """)

print("""
        </ul>
    </div>
</body>
</html>
""")
