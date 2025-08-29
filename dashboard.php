<?php
session_start();
include "db.php";

// Check login
if (!isset($_SESSION['UserID']) || $_SESSION['role'] !== 'Customer') {
    header("Location: index.php");
    exit;
}

$userid = $_SESSION['UserID'];
$fullname = $_SESSION['FullName'];

// Default page = Home
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Home → All products
if ($page == "home") {
    $stmt = $conn->prepare("SELECT ProductID, Name, Description, Price, ImagePath FROM Products");
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Categories → Study years
if ($page == "category") {
    $stmt = $conn->prepare("SELECT s.YearName, p.ProductID, p.Name, p.Price, p.ImagePath 
                            FROM Products p 
                            JOIN StudyYears s ON p.YearID = s.YearID 
                            ORDER BY s.YearName");
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[$row['YearName']][] = $row;
    }
    $stmt->close();
}

// Packages
if ($page == "packages") {
    $stmt = $conn->prepare("SELECT PackageID, Name, Description, Price FROM Packages");
    $stmt->execute();
    $result = $stmt->get_result();
    $packages = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Cart
if ($page == "cart") {
    $stmt = $conn->prepare("SELECT ci.CartItemID, p.Name AS ProductName, pk.Name AS PackageName, ci.Quantity, ci.Price, ci.SubTotal
                            FROM Cart c
                            JOIN CartItems ci ON c.CartID = ci.CartID
                            LEFT JOIN Products p ON ci.ProductID = p.ProductID
                            LEFT JOIN Packages pk ON ci.PackageID = pk.PackageID
                            WHERE c.UserID = ?");
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $cart = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Dashboard - Dentara</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo">Dentara</div>
        <ul>
            <li><a href="dashboard.php?page=home" class="<?php echo ($page=='home'?'active':''); ?>">Home</a></li>
            <li><a href="dashboard.php?page=category" class="<?php echo ($page=='category'?'active':''); ?>">Category</a></li>
            <li><a href="dashboard.php?page=packages" class="<?php echo ($page=='packages'?'active':''); ?>">Packages</a></li>
            <li><a href="dashboard.php?page=cart" class="<?php echo ($page=='cart'?'active':''); ?>">Cart</a></li>
        </ul>
        <div class="user-info">
            <span><?php echo htmlspecialchars($fullname); ?></span>
            <a href="index.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <main class="dashboard">
        <?php if ($page == "home"): ?>
            <h2>All Products</h2>
            <div class="grid">
                <?php foreach ($products as $product): ?>
                    <div class="card">
                        <img src="<?php echo htmlspecialchars($product['ImagePath']); ?>" alt="">
                        <h3><?php echo htmlspecialchars($product['Name']); ?></h3>
                        <p><?php echo htmlspecialchars($product['Description']); ?></p>
                        <p class="price">$<?php echo number_format($product['Price'], 2); ?></p>
                        <form method="POST" action="add_to_cart.php">
                            <input type="hidden" name="product_id" value="<?php echo $product['ProductID']; ?>">
                            <input type="number" name="quantity" value="1" min="1">
                            <button type="submit">Add to Cart</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($page == "category"): ?>
            <h2>Products by Study Year</h2>
            <?php foreach ($categories as $year => $items): ?>
                <h3><?php echo htmlspecialchars($year); ?></h3>
                <div class="grid">
                    <?php foreach ($items as $item): ?>
                        <div class="card">
                            <img src="<?php echo htmlspecialchars($item['ImagePath']); ?>" alt="">
                            <h4><?php echo htmlspecialchars($item['Name']); ?></h4>
                            <p class="price">$<?php echo number_format($item['Price'], 2); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php elseif ($page == "packages"): ?>
            <h2>Available Packages</h2>
            <div class="grid">
                <?php foreach ($packages as $pkg): ?>
                    <div class="card">
                        <h3><?php echo htmlspecialchars($pkg['Name']); ?></h3>
                        <p><?php echo htmlspecialchars($pkg['Description']); ?></p>
                        <p class="price">$<?php echo number_format($pkg['Price'], 2); ?></p>
                        <form method="POST" action="add_to_cart.php">
                            <input type="hidden" name="package_id" value="<?php echo $pkg['PackageID']; ?>">
                            <button type="submit">Add Package</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($page == "cart"): ?>
            <h2>Your Cart</h2>
            <?php if (empty($cart)): ?>
                <p>Your cart is empty.</p>
            <?php else: ?>
                <table class="cart-table">
                    <tr>
                        <th>Item</th><th>Quantity</th><th>Price</th><th>Subtotal</th>
                    </tr>
                    <?php foreach ($cart as $c): ?>
                        <tr>
                            <td><?php echo $c['ProductName'] ?: $c['PackageName']; ?></td>
                            <td><?php echo $c['Quantity']; ?></td>
                            <td>$<?php echo number_format($c['Price'], 2); ?></td>
                            <td>$<?php echo number_format($c['SubTotal'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <button class="checkout-btn">Proceed to Checkout</button>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
