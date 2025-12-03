<?php
// Departments management page content (loaded inside main.php)

// We assume $conn is already available from main.php (via db.php).

$errors = [];
$success = '';

// Handle delete action (GET)
if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    if ($deleteId > 0) {
        $stmt = $conn->prepare("DELETE FROM departments WHERE department_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $deleteId);
            if ($stmt->execute()) {
                $success = 'Department deleted successfully.';
            } else {
                $errors[] = 'Failed to delete department.';
            }
            $stmt->close();
        } else {
            $errors[] = 'Database error while preparing delete.';
        }
    }
}

// Handle create / update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptName = trim($_POST['department_name'] ?? '');
    $deptId   = isset($_POST['department_id']) && $_POST['department_id'] !== ''
        ? (int) $_POST['department_id']
        : null;

    if ($deptName === '') {
        $errors[] = 'Department name is required.';
    }

    if (empty($errors)) {
        if ($deptId) {
            // Update existing department
            $stmt = $conn->prepare("UPDATE departments SET department_name = ? WHERE department_id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $deptName, $deptId);
                if ($stmt->execute()) {
                    $success = 'Department updated successfully.';
                } else {
                    $errors[] = 'Failed to update department.';
                }
                $stmt->close();
            } else {
                $errors[] = 'Database error while preparing update.';
            }
        } else {
            // Create new department
            $stmt = $conn->prepare("INSERT INTO departments (department_name) VALUES (?)");
            if ($stmt) {
                $stmt->bind_param('s', $deptName);
                if ($stmt->execute()) {
                    $success = 'Department added successfully.';
                } else {
                    $errors[] = 'Failed to add department.';
                }
                $stmt->close();
            } else {
                $errors[] = 'Database error while preparing insert.';
            }
        }
    }
}

// If editing, fetch the department to prefill the form
$editDepartment = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $stmt = $conn->prepare("SELECT department_id, department_name FROM departments WHERE department_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $editId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $editDepartment = $result->fetch_assoc() ?: null;
            }
            $stmt->close();
        }
    }
}

// Fetch all departments to list
$departments = [];
$result = $conn->query("SELECT department_id, department_name FROM departments ORDER BY department_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
}
?>

<div class="bg-white rounded-xl shadow p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 gap-3">
        <h1 class="text-2xl font-bold">Departments</h1>

        <?php if ($editDepartment): ?>
            <span class="text-sm text-gray-500">
                Editing:
                <strong><?php echo htmlspecialchars($editDepartment['department_name']); ?></strong>
            </span>
        <?php endif; ?>
    </div>

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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Departments list -->
        <div class="lg:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-gray-800">All Departments</h2>
            </div>

            <div class="border rounded-lg overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100 text-gray-700">
                            <th class="p-3">ID</th>
                            <th class="p-3">Department Name</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($departments)): ?>
                            <tr>
                                <td colspan="3" class="p-4 text-center text-gray-500">
                                    No departments found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($departments as $dept): ?>
                                <tr class="border-t">
                                    <td class="p-3">
                                        <?php echo (int) $dept['department_id']; ?>
                                    </td>
                                    <td class="p-3">
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </td>
                                    <td class="p-3 text-right space-x-2">
                                        <a href="?page=departments&edit=<?php echo (int) $dept['department_id']; ?>"
                                           class="text-blue-600 hover:underline text-sm">
                                            Edit
                                        </a>
                                        <a href="?page=departments&delete=<?php echo (int) $dept['department_id']; ?>"
                                           onclick="return confirm('Are you sure you want to delete this department?');"
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

        <!-- Add / Edit form -->
        <div>
            <div class="border rounded-lg p-4 bg-gray-50">
                <h2 class="text-lg font-semibold mb-3 text-gray-800">
                    <?php echo $editDepartment ? 'Edit Department' : 'Add Department'; ?>
                </h2>

                <form method="post" class="space-y-3">
                    <?php if ($editDepartment): ?>
                        <input type="hidden" name="department_id"
                               value="<?php echo (int) $editDepartment['department_id']; ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Department Name
                        </label>
                        <input
                            type="text"
                            name="department_name"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g. Human Resources"
                            value="<?php echo htmlspecialchars($editDepartment['department_name'] ?? ($_POST['department_name'] ?? '')); ?>"
                            required
                        >
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <?php if ($editDepartment): ?>
                            <a href="?page=departments"
                               class="text-sm text-gray-600 hover:underline">
                                Cancel edit
                            </a>
                        <?php else: ?>
                            <span class="text-xs text-gray-500">
                                Create a new department.
                            </span>
                        <?php endif; ?>

                        <button
                            type="submit"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                            <?php echo $editDepartment ? 'Save Changes' : 'Add Department'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
