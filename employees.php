<?php
// Employees management page content (loaded inside main.php)

// We assume $conn is already available from main.php (via db.php).

$errors = [];
$success = '';

// Handle delete action (GET)
if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    if ($deleteId > 0) {
        $stmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $deleteId);
            if ($stmt->execute()) {
                $success = 'Employee deleted successfully.';
            } else {
                $errors[] = 'Failed to delete employee.';
            }
            $stmt->close();
        } else {
            $errors[] = 'Database error while preparing delete.';
        }
    }
}

// Handle create / update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeId = trim($_POST['employee_id'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $roleId = isset($_POST['role_id']) && $_POST['role_id'] !== '' ? (int) $_POST['role_id'] : null;
    $departmentId = isset($_POST['department_id']) && $_POST['department_id'] !== '' ? (int) $_POST['department_id'] : null;
    $status = trim($_POST['status'] ?? 'Active');
    $editId = isset($_POST['edit_id']) && $_POST['edit_id'] !== '' ? (int) $_POST['edit_id'] : null;

    // Validation
    if ($employeeId === '') {
        $errors[] = 'Employee ID is required.';
    }
    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }
    if ($lastName === '') {
        $errors[] = 'Last name is required.';
    }
    if (!in_array($status, ['Active', 'Inactive', 'On Leave'])) {
        $status = 'Active';
    }

    if (empty($errors)) {
        if ($editId) {
            // Update existing employee
            $stmt = $conn->prepare("UPDATE employees SET employee_id = ?, first_name = ?, last_name = ?, email = ?, phone = ?, role_id = ?, department_id = ?, status = ? WHERE employee_id = ?");
            if ($stmt) {
                $stmt->bind_param('sssssiisi', $employeeId, $firstName, $lastName, $email, $phone, $roleId, $departmentId, $status, $editId);
                if ($stmt->execute()) {
                    $success = 'Employee updated successfully.';
                } else {
                    $errors[] = 'Failed to update employee: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = 'Database error while preparing update.';
            }
        } else {
            // Check if employee_id already exists
            $checkStmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
            if ($checkStmt) {
                $checkStmt->bind_param('s', $employeeId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                if ($result->num_rows > 0) {
                    $errors[] = 'Employee ID already exists.';
                }
                $checkStmt->close();
            }

            if (empty($errors)) {
                // Create new employee
                $stmt = $conn->prepare("INSERT INTO employees (employee_id, first_name, last_name, email, phone, role_id, department_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('sssssiis', $employeeId, $firstName, $lastName, $email, $phone, $roleId, $departmentId, $status);
                    if ($stmt->execute()) {
                        $success = 'Employee added successfully.';
                    } else {
                        $errors[] = 'Failed to add employee: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $errors[] = 'Database error while preparing insert.';
                }
            }
        }
    }
}

// If editing, fetch the employee to prefill the form
$editEmployee = null;
if (isset($_GET['edit'])) {
    $editId = trim($_GET['edit']);
    if ($editId !== '') {
        $stmt = $conn->prepare("SELECT employee_id, first_name, last_name, email, phone, role_id, department_id, status FROM employees WHERE employee_id = ?");
        if ($stmt) {
            $stmt->bind_param('s', $editId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $editEmployee = $result->fetch_assoc() ?: null;
            }
            $stmt->close();
        }
    }
}

// Fetch all employees with roles and departments
$employees = [];
$sqlEmployees = "
    SELECT e.employee_id,
           e.first_name,
           e.last_name,
           e.email,
           e.phone,
           e.status,
           r.role_name,
           d.department_name,
           e.role_id,
           e.department_id
    FROM employees e
    LEFT JOIN roles r ON e.role_id = r.role_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    ORDER BY e.created_at DESC
";
$result = $conn->query($sqlEmployees);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Fetch all roles for dropdown
$roles = [];
$result = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
}

// Fetch all departments for dropdown
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
        <h1 class="text-2xl font-bold">Employees</h1>

        <?php if ($editEmployee): ?>
            <span class="text-sm text-gray-500">
                Editing:
                <strong><?php echo htmlspecialchars($editEmployee['first_name'] . ' ' . $editEmployee['last_name']); ?></strong>
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
        <!-- Employees list -->
        <div class="lg:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-gray-800">All Employees</h2>
            </div>

            <div class="border rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-gray-700">
                                <th class="p-3">ID</th>
                                <th class="p-3">Name</th>
                                <th class="p-3">Email</th>
                                <th class="p-3">Role</th>
                                <th class="p-3">Department</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="7" class="p-4 text-center text-gray-500">
                                        No employees found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $emp): ?>
                                    <tr class="border-t hover:bg-gray-50">
                                        <td class="p-3">
                                            <?php echo htmlspecialchars($emp['employee_id']); ?>
                                        </td>
                                        <td class="p-3">
                                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                        </td>
                                        <td class="p-3">
                                            <?php echo htmlspecialchars($emp['email'] ?? '—'); ?>
                                        </td>
                                        <td class="p-3">
                                            <?php echo htmlspecialchars($emp['role_name'] ?? '—'); ?>
                                        </td>
                                        <td class="p-3">
                                            <?php echo htmlspecialchars($emp['department_name'] ?? '—'); ?>
                                        </td>
                                        <td class="p-3">
                                            <?php
                                            $status = $emp['status'];
                                            $statusClasses = [
                                                'Active' => 'bg-green-100 text-green-700',
                                                'Inactive' => 'bg-gray-100 text-gray-700',
                                                'On Leave' => 'bg-yellow-100 text-yellow-700'
                                            ];
                                            $cls = $statusClasses[$status] ?? 'bg-gray-100 text-gray-700';
                                            ?>
                                            <span class="px-2 py-1 rounded-full text-xs <?php echo $cls; ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                        <td class="p-3 text-right space-x-2">
                                            <a href="?page=employees&edit=<?php echo urlencode($emp['employee_id']); ?>"
                                               class="text-blue-600 hover:underline text-sm">
                                                Edit
                                            </a>
                                            <a href="?page=employees&delete=<?php echo urlencode($emp['employee_id']); ?>"
                                               onclick="return confirm('Are you sure you want to delete this employee?');"
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
        </div>

        <!-- Add / Edit form -->
        <div>
            <div class="border rounded-lg p-4 bg-gray-50">
                <h2 class="text-lg font-semibold mb-3 text-gray-800">
                    <?php echo $editEmployee ? 'Edit Employee' : 'Add Employee'; ?>
                </h2>

                <form method="post" class="space-y-3">
                    <?php if ($editEmployee): ?>
                        <input type="hidden" name="edit_id"
                               value="<?php echo htmlspecialchars($editEmployee['employee_id']); ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Employee ID <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="employee_id"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g. EMP001"
                            value="<?php echo htmlspecialchars($editEmployee['employee_id'] ?? ($_POST['employee_id'] ?? '')); ?>"
                            required
                            <?php echo $editEmployee ? 'readonly' : ''; ?>
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="first_name"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="John"
                            value="<?php echo htmlspecialchars($editEmployee['first_name'] ?? ($_POST['first_name'] ?? '')); ?>"
                            required
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Last Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="last_name"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Doe"
                            value="<?php echo htmlspecialchars($editEmployee['last_name'] ?? ($_POST['last_name'] ?? '')); ?>"
                            required
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Email
                        </label>
                        <input
                            type="email"
                            name="email"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="john.doe@example.com"
                            value="<?php echo htmlspecialchars($editEmployee['email'] ?? ($_POST['email'] ?? '')); ?>"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Phone
                        </label>
                        <input
                            type="text"
                            name="phone"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="+1234567890"
                            value="<?php echo htmlspecialchars($editEmployee['phone'] ?? ($_POST['phone'] ?? '')); ?>"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Role
                        </label>
                        <select
                            name="role_id"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">— Select Role —</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo (int) $role['role_id']; ?>"
                                    <?php echo (isset($editEmployee['role_id']) && (int) $editEmployee['role_id'] === (int) $role['role_id']) || (isset($_POST['role_id']) && (int) $_POST['role_id'] === (int) $role['role_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Department
                        </label>
                        <select
                            name="department_id"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">— Select Department —</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo (int) $dept['department_id']; ?>"
                                    <?php echo (isset($editEmployee['department_id']) && (int) $editEmployee['department_id'] === (int) $dept['department_id']) || (isset($_POST['department_id']) && (int) $_POST['department_id'] === (int) $dept['department_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select
                            name="status"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                        >
                            <option value="Active" <?php echo (($editEmployee['status'] ?? ($_POST['status'] ?? 'Active')) === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo (($editEmployee['status'] ?? ($_POST['status'] ?? '')) === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="On Leave" <?php echo (($editEmployee['status'] ?? ($_POST['status'] ?? '')) === 'On Leave') ? 'selected' : ''; ?>>On Leave</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <?php if ($editEmployee): ?>
                            <a href="?page=employees"
                               class="text-sm text-gray-600 hover:underline">
                                Cancel edit
                            </a>
                        <?php else: ?>
                            <span class="text-xs text-gray-500">
                                Add a new employee.
                            </span>
                        <?php endif; ?>

                        <button
                            type="submit"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                            <?php echo $editEmployee ? 'Save Changes' : 'Add Employee'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
