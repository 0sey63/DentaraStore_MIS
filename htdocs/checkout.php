<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

session_start();
include "db.php";

// تحميل مكتبة PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// تحقق من تسجيل دخول المستخدم
if (!isset($_SESSION['UserID']) || $_SESSION['role'] !== 'Customer') {
    header("Location: index.php");
    exit;
}

$userid   = $_SESSION['UserID'];
$fullname = $_SESSION['FullName'];
$email    = $_SESSION['Email']; // ✅ تم تعديلها لتطابق index.php

// جلب محتويات السلة
$cart = [];
$totalPrice = 0;

$stmt = $conn->prepare("SELECT ci.CartItemID, ci.ProductID, ci.PackageID, 
                               p.Name AS ProductName, pk.Name AS PackageName, 
                               ci.Quantity, ci.Price, 
                               (ci.Quantity * ci.Price) AS SubTotal
                        FROM cart c
                        JOIN cartitems ci ON c.CartID = ci.CartID
                        LEFT JOIN products p ON ci.ProductID = p.ProductID
                        LEFT JOIN packages pk ON ci.PackageID = pk.PackageID
                        WHERE c.UserID = ?");
$stmt->bind_param("i", $userid);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $cart[] = $row;
        $totalPrice += $row['SubTotal'];
    }
}
$stmt->close();

$orderID = null;

// معالجة تأكيد الطلب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    if (!empty($cart)) {
        $conn->begin_transaction(); // نبدأ معاملة

        try {
            // تحقق من توفر الكميات
            foreach ($cart as $item) {
                if ($item['ProductID']) {
                    $stmtCheck = $conn->prepare("SELECT QuantityInStock FROM productinventory WHERE ProductID = ?");
                    $stmtCheck->bind_param("i", $item['ProductID']);
                    $stmtCheck->execute();
                    $stmtCheck->bind_result($stockQty);
                    if ($stmtCheck->fetch()) {
                        if ($stockQty < $item['Quantity']) {
                            throw new Exception("Sorry, product '{$item['ProductName']}' is not available in the required quantity.");
                        }
                    } else {
                        throw new Exception("Product '{$item['ProductName']}' not found in inventory.");
                    }
                    $stmtCheck->close();

                } elseif ($item['PackageID']) {
                    // تحقق من توفر مكونات البكج
                    $stmtPkg = $conn->prepare("SELECT pi.ProductID, pi.Quantity AS PkgQty, inv.QuantityInStock 
                                               FROM packageitems pi
                                               JOIN productinventory inv ON pi.ProductID = inv.ProductID
                                               WHERE pi.PackageID = ?");
                    $stmtPkg->bind_param("i", $item['PackageID']);
                    $stmtPkg->execute();
                    $resPkg = $stmtPkg->get_result();
                    while ($pkg = $resPkg->fetch_assoc()) {
                        $totalNeeded = $pkg['PkgQty'] * $item['Quantity'];
                        if ($pkg['QuantityInStock'] < $totalNeeded) {
                            throw new Exception("Sorry, not enough stock for a product inside package '{$item['PackageName']}'.");
                        }
                    }
                    $stmtPkg->close();
                }
            }

            // إدخال الطلب الجديد
            $stmtOrder = $conn->prepare("INSERT INTO orders (UserID, TotalAmount, OrderDate) VALUES (?, ?, NOW())");
            $stmtOrder->bind_param("id", $userid, $totalPrice);
            $stmtOrder->execute();
            $orderID = $stmtOrder->insert_id;
            $stmtOrder->close();

            // إضافة عناصر الطلب + تحديث المخزون
            foreach ($cart as $item) {
                $stmtItem = $conn->prepare("INSERT INTO orderitems (OrderID, ProductID, PackageID, Quantity, Price) 
                                            VALUES (?, ?, ?, ?, ?)");
                $stmtItem->bind_param("iiiid", $orderID, $item['ProductID'], $item['PackageID'], $item['Quantity'], $item['Price']);
                $stmtItem->execute();
                $stmtItem->close();

                if ($item['ProductID']) {
                    // خصم الكمية من المنتج العادي
                    $stmtUpdate = $conn->prepare("UPDATE productinventory 
                                                  SET QuantityInStock = QuantityInStock - ? 
                                                  WHERE ProductID = ?");
                    $stmtUpdate->bind_param("ii", $item['Quantity'], $item['ProductID']);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();

                } elseif ($item['PackageID']) {
                    // خصم الكميات من المنتجات المكونة للبكج
                    $stmtPkg = $conn->prepare("SELECT ProductID, Quantity AS PkgQty 
                                               FROM packageitems 
                                               WHERE PackageID = ?");
                    $stmtPkg->bind_param("i", $item['PackageID']);
                    $stmtPkg->execute();
                    $resPkg = $stmtPkg->get_result();
                    while ($pkg = $resPkg->fetch_assoc()) {
                        $totalDeduct = $pkg['PkgQty'] * $item['Quantity'];
                        $stmtUpdate = $conn->prepare("UPDATE productinventory 
                                                      SET QuantityInStock = QuantityInStock - ? 
                                                      WHERE ProductID = ?");
                        $stmtUpdate->bind_param("ii", $totalDeduct, $pkg['ProductID']);
                        $stmtUpdate->execute();
                        $stmtUpdate->close();
                    }
                    $stmtPkg->close();
                }
            }

            // تفريغ السلة
            $stmtClear = $conn->prepare("DELETE ci FROM cartitems ci 
                                         JOIN cart c ON ci.CartID = c.CartID 
                                         WHERE c.UserID = ?");
            $stmtClear->bind_param("i", $userid);
            $stmtClear->execute();
            $stmtClear->close();

            $conn->commit(); // اعتماد المعاملة

            // ✅ إرسال ايميل عبر PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'aldubai16osama@gmail.com'; // ضع ايميلك هنا
                $mail->Password   = 'tcna czam ktuk yvoc';    // ضع App Password هنا
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('aldubai16osama@gmail.com', 'Dentara Store');
                $mail->addAddress($email, $fullname);

                $mail->isHTML(true);
                $mail->Subject = "Order Confirmation - Dentara Store";
                $mail->Body    = "
                    Dear $fullname,<br><br>
                    Thank you for your order!<br>
                    <b>Order ID:</b> #$orderID<br>
                    <b>Total Amount:</b> YER " . number_format($totalPrice, 2) . "<br><br>
                    Please transfer the amount to our wallet number: <b>775663444</b><br><br>
                    Best regards,<br>
                    Dentara Store
                ";

                $mail->send();
            } catch (Exception $e) {
                $errorMsg = "Order confirmed, but email could not be sent. Error: {$mail->ErrorInfo}";
            }

            $successMsg = "Thank you for ordering from our store!<br>
                           Your Order ID: #$orderID<br>
                           Total Amount: YER " . number_format($totalPrice, 2) . "<br>
                           Please transfer to wallet: <b>775663444</b>";
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = $e->getMessage();
        }
    } else {
        $errorMsg = "Your cart is empty.";
    }
}

// تحديث الكمية مباشرة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qty'])) {
    foreach ($_POST['quantities'] as $cartItemID => $qty) {
        $qty = max(1, intval($qty));
        $stmtUpdate = $conn->prepare("UPDATE cartitems SET Quantity = ? WHERE CartItemID = ?");
        $stmtUpdate->bind_param("ii", $qty, $cartItemID);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }
    header("Refresh:0");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - Dentara</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .success-box {
            background: #e8f9e9;
            border: 2px solid #4CAF50;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            color: #2d662d;
            font-size: 16px;
            text-align: center;
        }
        .error-box {
            background: #fce8e6;
            border: 2px solid #f44336;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            color: #c62828;
            font-size: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="logo">Dentara</div>
    <ul>
        <li><a href="dashboard.php?page=home">Home</a></li>
        <li><a href="dashboard.php?page=category">Category</a></li>
        <li><a href="dashboard.php?page=packages">Packages</a></li>
        <li><a href="dashboard.php?page=cart" class="active">Cart</a></li>
    </ul>
    <div class="user-info">
        <span><?php echo htmlspecialchars($fullname); ?></span>
        <a href="index.php" class="logout-btn">Logout</a>
    </div>
</nav>

<main class="dashboard">
    <h2>Checkout</h2>

    <?php if (!empty($cart)): ?>
        <?php if(isset($successMsg)): ?>
            <div class="success-box"><?php echo $successMsg; ?></div>
            <a href="dashboard.php?page=home"><button type="button">Back to Home</button></a>
        <?php else: ?>
            <form method="POST">
                <table class="cart-table">
                    <tr>
                        <th>Item</th><th>Quantity</th><th>Price</th><th>Subtotal</th>
                    </tr>
                    <?php $totalPrice = 0; ?>
                    <?php foreach ($cart as $c): ?>
                        <?php $totalPrice += $c['Quantity'] * $c['Price']; ?>
                        <tr>
                            <td><?php echo $c['ProductName'] ?: $c['PackageName']; ?></td>
                            <td>
                                <input type="number" name="quantities[<?php echo $c['CartItemID']; ?>]" 
                                       value="<?php echo $c['Quantity']; ?>" min="1" style="width:60px;">
                            </td>
                            <td>YER<?php echo number_format($c['Price'], 2); ?></td>
                            <td>YER<?php echo number_format($c['Quantity'] * $c['Price'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="3" style="text-align:right; font-weight:bold;">Total:</td>
                        <td>YER<?php echo number_format($totalPrice, 2); ?></td>
                    </tr>
                </table>

                <button type="submit" name="update_qty" style="margin-top:10px;">Update Quantities</button>
                <button type="submit" name="confirm_order" class="checkout-btn">Confirm Order</button>
                <a href="dashboard.php?page=cart"><button type="button" style="margin-top:10px;">Back to Cart</button></a>
            </form>
        <?php endif; ?>

        <?php if(isset($errorMsg)): ?>
            <div class="error-box"><?php echo $errorMsg; ?></div>
        <?php endif; ?>

    <?php else: ?>
        <p>Your cart is empty.</p>
        <a href="dashboard.php?page=home"><button type="button">Back to Home</button></a>
    <?php endif; ?>
</main>
</body>
</html>
