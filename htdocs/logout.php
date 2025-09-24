<?php
session_start();

// مسح جميع بيانات السيشن
$_SESSION = [];

// مسح الكوكيز (اختياري لو عامل remember me)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// تدمير الجلسة
session_destroy();

// إعادة التوجيه لصفحة تسجيل الدخول
header("Location: index.php");
exit;