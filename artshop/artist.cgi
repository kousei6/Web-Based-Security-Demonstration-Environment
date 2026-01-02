#!/usr/bin/python3
# -*- coding: utf-8 -*-

import cgi
import cgitb
import mysql.connector
import os
import http.cookies
import html

# デバッグモードを有効にする (本番環境では無効にしてください)
cgitb.enable()

# --- データベース接続設定 ---
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': 'passwordA1!',
    'database': 'artshop',
    'charset': 'utf8mb4'
}

# --- ヘッダー出力 ---
print("Content-type: text/html; charset=UTF-8\n")

# --- CGIフォームデータの取得 ---
form = cgi.FieldStorage()
try:
    artist_id = int(form.getfirst('artist_id'))
except ValueError:
    artist_id = 0
    #show_error_page("作者エラー", "作者IDがありません", form.getfirst('artist_id'))
except TypeError:
    print(f"{type(artist_id)}")
"""artist_id = 0
if 'artist_id' in form:
    try:
        artist_id = int(form.getvalue('artist_id'))
    except ValueError:
        artist_id = 0"""
        

# --- データベース接続とデータ取得 ---
artist_name = "不明な作者"
artist_icon = "images/default_artist_icon.png"
arts = []
reviews = []

conn = None
cursor = None
try:
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)

    # 作者情報を取得
    stmt_artist = "SELECT artist_name, icon_path FROM Artist WHERE artist_id = %s"
    cursor.execute(stmt_artist, (artist_id,))
    artist_info = cursor.fetchone()
    if artist_info:
        artist_name = artist_info['artist_name']
        artist_icon = artist_info['icon_path'] if artist_info['icon_path'] else "images/default_artist_icon.png"

    # 作者の絵画情報を取得
    stmt_arts = "SELECT art_id, art_name, image_path FROM Art WHERE artist_id = %s"
    cursor.execute(stmt_arts, (artist_id,))
    arts = cursor.fetchall()

    # レビュー情報を取得（XSS脆弱性のため、HTMLエスケープしない）
    stmt_reviews = "SELECT U.user_name, R.text FROM Review R JOIN User U ON R.user_id = U.user_id WHERE R.artist_id = "+ str(artist_id) +" ORDER BY R.review_id DESC"
    cursor.execute(stmt_reviews)
    
    
    """stmt_reviews = "SELECT U.user_name, R.text FROM Review R JOIN User U ON R.user_id = U.user_id WHERE R.artist_id = %s ORDER BY R.review_id DESC"
    cursor.execute(stmt_reviews, (artist_id))"""
    reviews = cursor.fetchall()

except mysql.connector.Error as err:
    print(f"<p style='color: red;'>データベースエラー: {err}</p>")
finally:
    if cursor:
        cursor.close()
    if conn and conn.is_connected():
        conn.close()

# --- HTML出力 ---
print(f"""
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{html.escape(artist_name)} のページ - 絵画通販サイト</title>
    <style>
        body {{ font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }}
        header {{ background-color: #333; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; }}
        header h1 {{ margin: 0; font-size: 24px; }}
        header a {{ color: white; text-decoration: none; margin-left: 20px; }}
        .container {{ max-width: 1000px; margin: 20px auto; padding: 0 20px; background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }}
        .artist-info {{ text-align: center; padding: 30px 0; border-bottom: 1px solid #eee; }}
        .artist-info img {{ width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; }}
        .artist-info h2 {{ font-size: 28px; margin-bottom: 10px; color: #333; }}
        .section-title {{ font-size: 24px; color: #333; margin-top: 40px; margin-bottom: 20px; border-bottom: 2px solid #007bff; padding-bottom: 5px; }}

        .art-grid {{ display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px; }}
        .art-item {{ background-color: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }}
        .art-item img {{ max-width: 100%; height: 180px; object-fit: cover; border-radius: 4px; margin-bottom: 10px; }}
        .art-item h3 {{ font-size: 18px; margin-bottom: 5px; }}
        .art-item p {{ color: #666; font-size: 14px; }}
        .art-item a {{ text-decoration: none; color: #007bff; font-weight: bold; }}

        .review-section {{ margin-top: 40px; }}
        .review-form {{ margin-bottom: 30px; padding: 20px; background-color: #f9f9f9; border-radius: 8px; }}
        .review-form textarea {{ width: calc(100% - 22px); height: 80px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 10px; }}
        .review-form input[type="submit"] {{ background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }}

        .review-list {{ list-style: none; padding: 0; }}
        .review-item {{ background-color: #fff; border: 1px solid #eee; padding: 15px; margin-bottom: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }}
        .review-item .reviewer {{ font-weight: bold; color: #555; margin-bottom: 5px; }}
        .review-item .review-text {{ color: #333; line-height: 1.6; word-wrap: break-word; }}
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
        <div class="artist-info">
            <img src="{html.escape(artist_icon)}" alt="{html.escape(artist_name)} のアイコン">
            <h2>{html.escape(artist_name)}</h2>
        </div>

        <h3 class="section-title">作品一覧</h3>
        <div class="art-grid">
""")

if arts:
    for art in arts:
        art_id = art['art_id']
        art_name = art['art_name']
        image_path = art['image_path'] if art['image_path'] else 'images/default_art.png'
        print(f"""
            <div class="art-item">
                <a href="art.php?art_id={html.escape(str(art_id))}">
                    <img src="{html.escape(image_path)}" alt="{html.escape(art_name)}">
                    <h3>{html.escape(art_name)}</h3>
                </a>
            </div>
        """)
else:
    print("<p>この作者の作品はまだありません。</p>")

print(f"""
        </div>

        <h3 class="section-title">レビュー</h3>
        <div class="review-section">
            <div class="review-form">
                <h4>レビューを書く</h4>
                <form action="post_review.cgi" method="post">
                    <input type="hidden" name="artist_id" value="{artist_id}">
                    <textarea name="review_text" placeholder="レビューを記入してください" required></textarea><br>
                    <input type="submit" value="レビューを投稿">
                </form>
            </div>

            <ul class="review-list">
""")
if reviews:
    for review in reviews:
        user_name = review['user_name']
        text = review['text'] # XSS脆弱性のため、エスケープしない
        print(f"""
                <li class="review-item">
                    <div class="reviewer">投稿者: {html.escape(user_name)}</div>
                    <div class="review-text">{text}</div>
                </li>
        """)
else:
    print("<p>まだレビューがありません。</p>")

print("""
            </ul>
        </div>
    </div>
</body>
</html>
""")
