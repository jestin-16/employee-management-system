<?php
// Settings management page content (loaded inside main.php)

// We assume $conn is already available from main.php (via db.php).

$errors = [];
$success = '';
$activeTab = $_GET['tab'] ?? 'profile';

// ====== PROFILE SETTINGS ======
if ($activeTab === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    if (empty($errors)) {
        // Check if email is already taken by another user
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        if ($stmt) {
            $stmt->bind_param('si', $email, $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = 'Email is already taken by another user.';
            } else {
                $stmt->close();
                // Update user profile
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE user_id = ?");
                if ($stmt) {
                    $stmt->bind_param('ssi', $name, $email, $_SESSION['user_id']);
                    if ($stmt->execute()) {
                        $_SESSION['name'] = $name;
                        $_SESSION['email'] = $email;
                        $success = 'Profile updated successfully.';
                    } else {
                        $errors[] = 'Failed to update profile: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $errors[] = 'Database error while preparing update.';
                }
            }
        }
    }
}

// ====== CHANGE PASSWORD ======
if ($activeTab === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $errors[] = 'All password fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation do not match.';
    } elseif (strlen($newPassword) < 6) {
        $errors[] = 'New password must be at least 6 characters long.';
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($currentPassword, $user['password_hash'])) {
                // Update password
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                if ($stmt) {
                    $stmt->bind_param('si', $newHash, $_SESSION['user_id']);
                    if ($stmt->execute()) {
                        $success = 'Password changed successfully.';
                    } else {
                        $errors[] = 'Failed to change password: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $errors[] = 'Database error while preparing password update.';
                }
            } else {
                $errors[] = 'Current password is incorrect.';
            }
        }
    }
}

// ====== ROLES MANAGEMENT ======
// Handle delete role
if (isset($_GET['delete_role'])) {
    $deleteId = (int) $_GET['delete_role'];
    if ($deleteId > 0) {
        // Check if any employees are using this role
        $checkStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM employees WHERE role_id = ?");
        if ($checkStmt) {
            $checkStmt->bind_param('i', $deleteId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            $checkStmt->close();

            if ($row['cnt'] > 0) {
                $errors[] = 'Cannot delete role. It is assigned to ' . $row['cnt'] . ' employee(s).';
            } else {
                $stmt = $conn->prepare("DELETE FROM roles WHERE role_id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $deleteId);
                    if ($stmt->execute()) {
                        $success = 'Role deleted successfully.';
                    } else {
                        $errors[] = 'Failed to delete role.';
                    }
                    $stmt->close();
                } else {
                    $errors[] = 'Database error while preparing delete.';
                }
            }
        }
    }
}

// Handle create / update role
if ($activeTab === 'roles' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_role') {
    $roleName = trim($_POST['role_name'] ?? '');
    $roleId = isset($_POST['role_id']) && $_POST['role_id'] !== '' ? (int) $_POST['role_id'] : null;

    if ($roleName === '') {
        $errors[] = 'Role name is required.';
    }

    if (empty($errors)) {
        if ($roleId) {
            // Update existing role
            $stmt = $conn->prepare("UPDATE roles SET role_name = ? WHERE role_id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $roleName, $roleId);
                if ($stmt->execute()) {
                    $success = 'Role updated successfully.';
                } else {
                    $errors[] = 'Failed to update role.';
                }
                $stmt->close();
            } else {
                $errors[] = 'Database error while preparing update.';
            }
        } else {
            // Create new role
            $stmt = $conn->prepare("INSERT INTO roles (role_name) VALUES (?)");
            if ($stmt) {
                $stmt->bind_param('s', $roleName);
                if ($stmt->execute()) {
                    $success = 'Role added successfully.';
                } else {
                    $errors[] = 'Failed to add role.';
                }
                $stmt->close();
            } else {
                $errors[] = 'Database error while preparing insert.';
            }
        }
    }
}

// If editing role, fetch it
$editRole = null;
if (isset($_GET['edit_role'])) {
    $editId = (int) $_GET['edit_role'];
    if ($editId > 0) {
        $stmt = $conn->prepare("SELECT role_id, role_name FROM roles WHERE role_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $editId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $editRole = $result->fetch_assoc() ?: null;
            }
            $stmt->close();
        }
    }
}

// Fetch current user data
$currentUser = null;
$stmt = $conn->prepare("SELECT user_id, name, email FROM users WHERE user_id = ?");
if ($stmt) {
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentUser = $result->fetch_assoc();
    $stmt->close();
}

// Fetch all roles
$roles = [];
$result = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
}
?>

<div class="bg-white rounded-xl shadow p-6">
    <h1 class="text-2xl font-bold mb-6">Settings</h1>

    <?php if (!empty($errors)): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc list-inside space-y-1">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="flex space-x-8">
            <a href="?page=settings&tab=profile"
               class="<?php echo $activeTab === 'profile' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700'; ?> py-4 px-1 text-sm font-medium">
                Profile Settings
            </a>
            <a href="?page=settings&tab=roles"
               class="<?php echo $activeTab === 'roles' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700'; ?> py-4 px-1 text-sm font-medium">
                Roles Management
            </a>
        </nav>
    </div>

    <!-- Profile Settings Tab -->
    <?php if ($activeTab === 'profile'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Update Profile -->
            <div class="border rounded-lg p-6">
                <h2 class="text-lg font-semibold mb-4 text-gray-800">Update Profile</h2>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($currentUser['name'] ?? $_SESSION['name'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="email"
                            name="email"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($currentUser['email'] ?? $_SESSION['email'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <button
                        type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                        Update Profile
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="border rounded-lg p-6">
                <h2 class="text-lg font-semibold mb-4 text-gray-800">Change Password</h2>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Current Password <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="password"
                            name="current_password"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            New Password <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="password"
                            name="new_password"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                            minlength="6"
                        >
                        <p class="text-xs text-gray-500 mt-1">Must be at least 6 characters</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Confirm New Password <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="password"
                            name="confirm_password"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                            minlength="6"
                        >
                    </div>

                    <button
                        type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                        Change Password
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Roles Management Tab -->
    <?php if ($activeTab === 'roles'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Roles List -->
            <div class="lg:col-span-2">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold text-gray-800">All Roles</h2>
                </div>

                <div class="border rounded-lg overflow-hidden">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-gray-700">
                                <th class="p-3">ID</th>
                                <th class="p-3">Role Name</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($roles)): ?>
                                <tr>
                                    <td colspan="3" class="p-4 text-center text-gray-500">
                                        No roles found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($roles as $role): ?>
                                    <tr class="border-t hover:bg-gray-50">
                                        <td class="p-3">
                                            <?php echo (int) $role['role_id']; ?>
                                        </td>
                                        <td class="p-3">
                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                        </td>
                                        <td class="p-3 text-right space-x-2">
                                            <a href="?page=settings&tab=roles&edit_role=<?php echo (int) $role['role_id']; ?>"
                                               class="text-blue-600 hover:underline text-sm">
                                                Edit
                                            </a>
                                            <a href="?page=settings&tab=roles&delete_role=<?php echo (int) $role['role_id']; ?>"
                                               onclick="return confirm('Are you sure you want to delete this role?');"
                                               class="text-red-600 hover:underline text-sm">
                                                Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add / Edit Role Form -->
            <div>
                <div class="border rounded-lg p-4 bg-gray-50">
                    <h2 class="text-lg font-semibold mb-3 text-gray-800">
                        <?php echo $editRole ? 'Edit Role' : 'Add Role'; ?>
                    </h2>

                    <form method="post" class="space-y-3">
                        <input type="hidden" name="action" value="save_role">
                        <?php if ($editRole): ?>
                            <input type="hidden" name="role_id"
                                   value="<?php echo (int) $editRole['role_id']; ?>">
                        <?php endif; ?>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Role Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="role_name"
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="e.g. Manager, Developer"
                                value="<?php echo htmlspecialchars($editRole['role_name'] ?? ($_POST['role_name'] ?? '')); ?>"
                                required
                            >
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <?php if ($editRole): ?>
                                <a href="?page=settings&tab=roles"
                                   class="text-sm text-gray-600 hover:underline">
                                    Cancel edit
                                </a>
                            <?php else: ?>
                                <span class="text-xs text-gray-500">
                                    Create a new role.
                                </span>
                            <?php endif; ?>

                            <button
                                type="submit"
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                                <?php echo $editRole ? 'Save Changes' : 'Add Role'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
