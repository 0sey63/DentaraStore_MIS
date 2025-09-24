<?php
session_start();
include "db.php";

if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cart_item_id'])) {
    $cartItemId = intval($_POST['cart_item_id']);

    // حذف المنتج من cartitems
    $stmt = $conn->prepare("DELETE FROM cartitems WHERE CartItemID = ?");
    if ($stmt) {
        $stmt->bind_param("i", $cartItemId);
        $stmt->execute();
        $stmt->close();
    }
}

// العودة للـ Cart
header("Location: dashboard.php?page=cart");
exit;
