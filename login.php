<?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        // Here we allow login by email OR name (username field)
        $stmt = $conn->prepare("SELECT user_id, name, email, password_hash, role FROM users WHERE email = ? OR name = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ss', $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Login success
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                header('Location: main.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Failed to prepare login query.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 space-y-6">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Welcome Back</h1>
            <p class="text-gray-600">Sign in to your account</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 px-4 py-2 rounded-lg text-sm">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="post" class="space-y-4">
            <div>
                <input 
                    type="text" 
                    name="username" 
                    placeholder="Email or Name" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-200"
                    required
                >
            </div>
            <div>
                <input 
                    type="password" 
                    name="password" 
                    placeholder="Password" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-200"
                    required
                >
            </div>
            <button 
                type="submit" 
                class="w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition duration-200 transform hover:scale-[1.02]"
            >
                Login
            </button>
        </form>
    </div>
</body>
</html>