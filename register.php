<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $sql = "INSERT INTO users (username, password) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password);

    if ($stmt->execute()) {
        echo "<script>alert('Registration successful! Please login.'); window.location.href='index.html';</script>";
    } else {
        echo "<script>alert('Username already taken or error occurred.'); window.history.back();</script>";
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Register - Chat App</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
  <h2>Register</h2>
  <form method="POST" action="register.php" >
   
    <input type="text" name="username" placeholder="Choose a username" required><br>
    <input type="password" name="password" placeholder="Choose a password" required><br>
    <button type="submit">Register</button>
  </form> <!-- Close the form here -->

  <form action="index.html" method="GET">
    <button type="submit">Back to Login</button>
  </form>
</div>
</body>
</html>
