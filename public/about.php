<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

render('header', ['title' => 'このシステムについて']);
?>
<div class="container py-4">
  <div class="text-center mb-5">
    <span class="hero-label mb-2">ABOUT</span>
    <h1 class="h3 fw-bold">このシステムについて</h1>
    <p class="text-muted">うさぎ商事オンラインショップは、うさぎ用品をお届けする小さなECサイトです。<br class="d-none d-md-inline">このページでは、システムの全体像と使い方をかんたんにご紹介します。</p>
  </div>

  <section class="panel mb-4">
    <h2 class="section-title mb-4">システム構成</h2>
    <div class="arch py-2">
      <div class="arch-node"><strong>ブラウザ</strong><small>お客さま / 管理者</small></div>
      <div class="arch-arrow">→</div>
      <div class="arch-node main"><strong>Apache + mod_php</strong><small>PHP 8.2 アプリケーション</small></div>
      <div class="arch-arrow">→</div>
      <div class="arch-side">
        <div class="arch-node"><strong>MySQL 8</strong><small>商品・注文・会員データ</small></div>
        <div class="arch-node"><strong>Mailhog</strong><small>注文確認メール（SMTP）</small></div>
        <div class="arch-node"><strong>送料API</strong><small>/api/shipping.php（Guzzle で呼び出し）</small></div>
      </div>
    </div>
    <p class="small text-muted text-center mt-3 mb-0">すべてのコンポーネントは <code>docker compose up</code> でまとめて起動します。</p>
  </section>

  <div class="row g-4 mb-4">
    <div class="col-lg-7">
      <section class="panel h-100">
        <h2 class="section-title mb-4">主な機能</h2>
        <div class="row g-3 small">
          <div class="col-md-6 d-flex gap-3"><span class="feature-icon">1</span><div><strong>商品検索・一覧</strong><br>キーワードで牧草・おやつ・グッズを検索</div></div>
          <div class="col-md-6 d-flex gap-3"><span class="feature-icon">2</span><div><strong>商品詳細・レビュー</strong><br>在庫表示、星評価つきのカスタマーレビュー</div></div>
          <div class="col-md-6 d-flex gap-3"><span class="feature-icon">3</span><div><strong>カート・購入</strong><br>郵便番号から送料を自動計算、確認メール送信</div></div>
          <div class="col-md-6 d-flex gap-3"><span class="feature-icon">4</span><div><strong>マイページ</strong><br>注文履歴・ステータス確認、請求書PDFダウンロード</div></div>
          <div class="col-md-6 d-flex gap-3"><span class="feature-icon">5</span><div><strong>アカウント設定</strong><br>氏名・メール・パスワード変更、プロフィール画像</div></div>
          <div class="col-md-6 d-flex gap-3"><span class="feature-icon">6</span><div><strong>管理画面</strong><br>売上ダッシュボード、注文一覧、商品画像、CSV出力</div></div>
        </div>
      </section>
    </div>
    <div class="col-lg-5">
      <section class="panel h-100">
        <h2 class="section-title mb-4">技術スタック</h2>
        <table class="table table-usagi table-sm small mb-0">
          <tbody>
            <tr><th>言語</th><td>PHP 8.2（フレームワークなし / PDO）</td></tr>
            <tr><th>Web サーバー</th><td>Apache 2.4 + mod_php（Debian bookworm）</td></tr>
            <tr><th>データベース</th><td>MySQL 8</td></tr>
            <tr><th>ライブラリ</th><td>Composer（PHPMailer / Guzzle）</td></tr>
            <tr><th>フロントエンド</th><td>Bootstrap 5 / jQuery</td></tr>
            <tr><th>メール</th><td>Mailhog（開発用 SMTP、<code>:8025</code> で閲覧）</td></tr>
            <tr><th>テスト</th><td>PHPUnit 10 / curl スモークテスト</td></tr>
          </tbody>
        </table>
      </section>
    </div>
  </div>

  <section class="panel">
    <h2 class="section-title mb-3">テスト用アカウント</h2>
    <p class="small text-muted">パスワードはすべて <code>password</code> です。</p>
    <div class="table-responsive">
      <table class="table table-usagi small mb-0">
        <thead><tr><th>メールアドレス</th><th>お名前</th><th>権限</th><th>できること</th></tr></thead>
        <tbody>
          <tr><td><code>admin@usagi.example</code></td><td>宇佐美 管理者</td><td><span class="badge rounded-pill badge-status-processing">管理者</span></td><td>管理画面（ダッシュボード・注文一覧・商品画像・CSV出力）</td></tr>
          <tr><td><code>user1@usagi.example</code></td><td>佐藤 花子</td><td><span class="badge rounded-pill badge-status-completed">一般会員</span></td><td>購入、注文履歴、レビュー投稿（東京都）</td></tr>
          <tr><td><code>user2@usagi.example</code></td><td>鈴木 一郎</td><td><span class="badge rounded-pill badge-status-completed">一般会員</span></td><td>購入、注文履歴、レビュー投稿（大阪府）</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php render('footer'); ?>
