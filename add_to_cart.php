<?php
session_start();
include "db.php";

// لازم المستخدم يكون مسجل دخول
if (!isset($_SESSION['UserID']) || $_SESSION['role'] !== 'Customer') {
    header("Location: index.php");
    exit;
}

$userid = $_SESSION['UserID'];

// تأكد أن البيانات واصلة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
    $package_id = isset($_POST['package_id']) ? intval($_POST['package_id']) : null;
    $quantity   = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;

    // لازم يختار يا Product يا Package
    if (!$product_id && !$package_id) {
        die("Invalid request: no product or package selected.");
    }

    // نجيب CartID للمستخدم
    $stmt = $conn->prepare("SELECT CartID FROM Cart WHERE UserID = ?");
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $stmt->bind_result($cartid);
    if (!$stmt->fetch()) {
        $stmt->close();
        // ما عنده سلة → نعمل وحدة جديدة
        $stmt2 = $conn->prepare("INSERT INTO Cart (UserID) VALUES (?)");
        $stmt2->bind_param("i", $userid);
        $stmt2->execute();
        $cartid = $stmt2->insert_id;
        $stmt2->close();
    }
    $stmt->close();

    // نحدد السعر حسب إذا كان Product أو Package
    if ($product_id) {
        $stmt = $conn->prepare("SELECT Price FROM Products WHERE ProductID = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $stmt->bind_result($price);
        if (!$stmt->fetch()) {
            die("Product not found.");
        }
        $stmt->close();

        // هل المنتج موجود مسبقًا في السلة؟
        $stmt = $conn->prepare("SELECT CartItemID, Quantity FROM CartItems WHERE CartID = ? AND ProductID = ?");
        $stmt->bind_param("ii", $cartid, $product_id);
        $stmt->execute();
        $stmt->bind_result($cartItemID, $oldQty);
        if ($stmt->fetch()) {
            $stmt->close();
            // حدث الكمية
            $newQty = $oldQty + $quantity;
            $stmt2 = $conn->prepare("UPDATE CartItems SET Quantity = ? WHERE CartItemID = ?");
            $stmt2->bind_param("ii", $newQty, $cartItemID);
            $stmt2->execute();
            $stmt2->close();
        } else {
            $stmt->close();
            // أضف منتج جديد
            $stmt2 = $conn->prepare("INSERT INTO CartItems (CartID, ProductID, Quantity, Price) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("iiid", $cartid, $product_id, $quantity, $price);
            $stmt2->execute();
            $stmt2->close();
        }

    } elseif ($package_id) {
        $stmt = $conn->prepare("SELECT Price FROM Packages WHERE PackageID = ?");
        $stmt->bind_param("i", $package_id);
        $stmt->execute();
        $stmt->bind_result($price);
        if (!$stmt->fetch()) {
            die("Package not found.");
        }
        $stmt->close();

        // هل البكج موجود مسبقًا في السلة؟
        $stmt = $conn->prepare("SELECT CartItemID, Quantity FROM CartItems WHERE CartID = ? AND PackageID = ?");
        $stmt->bind_param("ii", $cartid, $package_id);
        $stmt->execute();
        $stmt->bind_result($cartItemID, $oldQty);
        if ($stmt->fetch()) {
            $stmt->close();
            // حدث الكمية
            $newQty = $oldQty + $quantity;
            $stmt2 = $conn->prepare("UPDATE CartItems SET Quantity = ? WHERE CartItemID = ?");
            $stmt2->bind_param("ii", $newQty, $cartItemID);
            $stmt2->execute();
            $stmt2->close();
        } else {
            $stmt->close();
            // أضف Package جديد
            $stmt2 = $conn->prepare("INSERT INTO CartItems (CartID, PackageID, Quantity, Price) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("iiid", $cartid, $package_id, $quantity, $price);
            $stmt2->execute();
            $stmt2->close();
        }
    }

    // بعد الإضافة → نرجع للـ cart
    header("Location: dashboard.php?page=cart");
    exit;
} else {
    die("Invalid request method.");
}
