<?php
session_start();
require_once 'db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } else {
        // Check if email already exists
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = 'A user with this email already exists.';
            } else {
                $stmt->close();

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'admin';

                $stmt = $conn->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('ssss', $name, $email, $hash, $role);
                    if ($stmt->execute()) {
                        $message = 'Admin user created successfully. You can now log in.';
                    } else {
                        $error = 'Failed to create admin: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Failed to prepare insert statement.';
                }
            }
            if ($stmt && $stmt->num_rows > 0) {
                $stmt->close();
            }
        } else {
            $error = 'Failed to prepare email check.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin User</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-200 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Create Admin User</h1>
            <p class="text-gray-600 text-sm">Use this once to add your first admin, then optionally delete this file.</p>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-100 text-green-700 px-4 py-2 rounded-lg text-sm">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 px-4 py-2 rounded-lg text-sm">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input
                    type="text"
                    name="name"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    required
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input
                    type="email"
                    name="email"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    required
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input
                    type="password"
                    name="password"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    required
                >
            </div>

            <button
                type="submit"
                class="w-full bg-indigo-600 text-white py-2.5 rounded-lg font-semibold hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition duration-200"
            >
                Create Admin
            </button>
        </form>
    </div>
</body>
</html>


