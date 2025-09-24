<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
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

// Initialize arrays
$products = [];
$categories = [];
$packages = [];
$cart = [];

// Home → All products
if ($page == "home") {
    $stmt = $conn->prepare("SELECT ProductID, Name, Description, Price, ImagePath FROM products");
    if (!$stmt) die("Prepare failed: " . $conn->error);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) $products = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Categories → Study years
if ($page == "category") {
    $stmt = $conn->prepare("SELECT s.YearName, p.ProductID, p.Name, p.Price, p.ImagePath 
                            FROM products p 
                            JOIN studyyears s ON p.YearID = s.YearID 
                            ORDER BY s.YearName");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $categories[$row['YearName']][] = $row;
            }
        }
        $stmt->close();
    }
}

// Packages
if ($page == "packages") {
    $stmt = $conn->prepare("SELECT PackageID, Name, Description, Price FROM packages");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) $packages = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Cart
if ($page == "cart") {
    $stmt = $conn->prepare("SELECT ci.CartItemID, p.Name AS ProductName, pk.Name AS PackageName, ci.Quantity, ci.Price, ci.SubTotal
                            FROM cart c
                            JOIN cartitems ci ON c.CartID = ci.CartID
                            LEFT JOIN products p ON ci.ProductID = p.ProductID
                            LEFT JOIN packages pk ON ci.PackageID = pk.PackageID
                            WHERE c.UserID = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) $cart = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
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
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <main class="dashboard">

        <!-- Home Page -->
        <?php if ($page == "home"): ?>
            <h2>All Products</h2>
            <div class="grid">
                <?php foreach ($products as $product): ?>
                    <div class="card">
                        <img src="<?php echo htmlspecialchars($product['ImagePath']); ?>" alt="">
                        <h3><?php echo htmlspecialchars($product['Name']); ?></h3>
                        <p><?php echo htmlspecialchars($product['Description']); ?></p>
                        <p class="price">YER<?php echo number_format($product['Price'], 2); ?></p>
                        <form method="POST" action="add_to_cart.php">
                            <input type="hidden" name="product_id" value="<?php echo $product['ProductID']; ?>">
                            <input type="number" name="quantity" value="1" min="1">
                            <button type="submit">Add to Cart</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

        <!-- Category Page -->
        <?php elseif ($page == "category"): ?>
            <h2>Products by Study Year</h2>
            <?php foreach ($categories as $year => $items): ?>
                <h3><?php echo htmlspecialchars($year); ?></h3>
                <div class="grid">
                    <?php foreach ($items as $item): ?>
                        <div class="card">
                            <img src="<?php echo htmlspecialchars($item['ImagePath']); ?>" alt="">
                            <h3><?php echo htmlspecialchars($item['Name']); ?></h3>
                            <p class="price">YER<?php echo number_format($item['Price'], 2); ?></p>
                            <form method="POST" action="add_to_cart.php">
                                <input type="hidden" name="product_id" value="<?php echo $item['ProductID']; ?>">
                                <input type="number" name="quantity" value="1" min="1">
                                <button type="submit">Add to Cart</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

        <!-- Packages Page -->
        <?php elseif ($page == "packages"): ?>
            <h2>Available Packages</h2>
            <div class="grid">
                <?php foreach ($packages as $pkg): ?>
                    <div class="card">
                        <h3><?php echo htmlspecialchars($pkg['Name']); ?></h3>
                        <p><?php echo htmlspecialchars($pkg['Description']); ?></p>
                        <p class="price">YER<?php echo number_format($pkg['Price'], 2); ?></p>
                        <form method="POST" action="add_to_cart.php">
                            <input type="hidden" name="package_id" value="<?php echo $pkg['PackageID']; ?>">
                            <input type="number" name="quantity" value="1" min="1">
                            <button type="submit">Add Package</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

        <!-- Cart Page -->
        <?php elseif ($page == "cart"): ?>
            <h2>Your Cart</h2>
            <?php if (empty($cart)): ?>
                <p>Your cart is empty.</p>
            <?php else: ?>
                <table class="cart-table">
                    <tr>
                        <th>Item</th><th>Quantity</th><th>Price</th><th>Subtotal</th><th>Action</th>
                    </tr>
                    <?php foreach ($cart as $c): ?>
                        <tr>
                            <td><?php echo $c['ProductName'] ?: $c['PackageName']; ?></td>
                            <td><?php echo $c['Quantity']; ?></td>
                            <td>YER<?php echo number_format($c['Price'], 2); ?></td>
                            <td>YER<?php echo number_format($c['SubTotal'], 2); ?></td>
                            <td>
                                <form method="POST" action="remove_from_cart.php" style="display:inline;">
                                    <input type="hidden" name="cart_item_id" value="<?php echo $c['CartItemID']; ?>">
                                    <button type="submit" class="remove-btn">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <!-- Checkout & Clear Cart -->
                <form method="POST" action="checkout.php" style="display:inline;">
                    <button type="submit" class="checkout-btn">Proceed to Checkout</button>
                </form>

                <form method="POST" action="clear_cart.php" style="display:inline;">
                    <button type="submit" class="clear-btn" onclick="return confirm('Are you sure you want to clear the cart?');">Clear Cart</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

    </main>
</body>
</html>
