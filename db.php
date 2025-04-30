<?php
$host = "localhost";
$db = "chat_app";
$user = "root"; // Change if needed
$pass = "";     // Change if needed

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = [
    "users" => "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL
        )
    ",
    "messages" => "
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            username VARCHAR(50) NOT NULL,
            file_path VARCHAR(255),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ",
    "private_messages" => "
        CREATE TABLE IF NOT EXISTS private_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_user_id INT NOT NULL,
            to_user_id INT NOT NULL,
            message TEXT NOT NULL,
            file_path VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (from_user_id) REFERENCES users(id),
            FOREIGN KEY (to_user_id) REFERENCES users(id)
        )
    "
];

foreach ($sql as $tableName => $query) {
    if ($conn->query($query) === TRUE) {
       // echo "Table $tableName created successfully<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
}
?>
