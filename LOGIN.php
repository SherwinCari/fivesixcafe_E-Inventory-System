<?php
session_start();

$host = "mysql-1d69cd83-umak-e978.i.aivencloud.com";
$port = 19494;
$dbname = "login_credentials";
$username = "avnadmin";
$password = "AVNS_vZ6RVEWU-0a2Jwp-Zzz";

$conn = mysqli_connect($host, $username, $password, $dbname, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
} 
$error = "";
    
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $pass = $_POST['password'];

    // Use prepared statements to prevent SQL injection (delete this if error)
    $stmt = mysqli_prepare($conn, "SELECT * FROM employee_credentials WHERE (email = ? OR username = ?)");
    mysqli_stmt_bind_param($stmt, "ss", $email, $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    /*
    $query = "SELECT * FROM employee_credentials WHERE (email='$email' OR username = '$email') AND password='$pass'";
    $result = mysqli_query($conn, $query); 
    */
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Compare password (use password_verify if hashed, direct compare if plain)
        if ($row['password'] === $pass) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username']  = $row['username'];
            $_SESSION['email']     = $row['email'];
            $_SESSION['role']      = $row['roles'];  // ← store role in session

            if ($row['roles'] === 'admin') {
                header('Location: Merchandise.php');
            } else {
                header('Location: Cashier.php');
            }
            exit();
        }
    }

    $error = "Incorrect email or password!";
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Fivesix Legaspi Cafe</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="LOGIN.css">
</head>

<body>
    <div class="split-container">

        <!-- LEFT SIDE IMAGE -->
        <div class="left-side">
            <img src="IMAGES/P5.jpg" alt="Coffee and pastries">
        </div>

        <!-- RIGHT SIDE LOGIN -->
        <div class="right-side">
            <div class="login-container">
                <div class="login-header">
                    <div class="logo">
                        <div class="logo-placeholder"><img src="IMAGES/Logo.png" alt="Logo" class="logoo"></div>
                        <span class="logo-text">Fivesix Legaspi Cafe</span>
                    </div>
                    <h1>Employee Portal</h1>
                    <p>Sign in to your account to continue</p>
                </div>

                <form class="login-form" action="" method="POST">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input class="shesh" type="text" id="email" name="email" placeholder="Enter your email or username"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input class="shesh" type="password" id="password" name="password"
                            placeholder="Enter your password" required>
                    </div>

                    <?php if ($error): ?>
                        <p class="login-error" color = "red"><?= $error ?></p>
                    <?php endif; ?>
                    <button type="submit" class="login-btn">LOGIN</button>
                </form>


                <a href="Website.html" class="back-link">← Back to Home</a>
            </div>
        </div>

    </div>

</body>

</html>