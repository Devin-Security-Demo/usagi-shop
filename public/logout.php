<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

if (!is_post()) {
    redirect('/');
}
csrf_verify();
logout_user();
session_start();
flash('info', 'ログアウトしました。');
redirect('/');
