<?php
// Attendance management page content (loaded inside main.php)

// We assume $conn is already available from main.php (via db.php).

$errors = [];
$success = '';

// Handle delete action (GET)
if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    if ($deleteId > 0) {
        $stmt = $conn->prepare("DELETE FROM attendance WHERE attendance_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $deleteId);
            if ($stmt->execute()) {
                $success = 'Attendance record deleted successfully.';
            } else {
                $errors[] = 'Failed to delete attendance record.';
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
    $date = trim($_POST['date'] ?? '');
    $status = trim($_POST['status'] ?? 'Present');
    $checkIn = trim($_POST['check_in'] ?? '');
    $checkOut = trim($_POST['check_out'] ?? '');
    $editId = isset($_POST['edit_id']) && $_POST['edit_id'] !== '' ? (int) $_POST['edit_id'] : null;

    // Validation
    if ($employeeId === '') {
        $errors[] = 'Employee is required.';
    }
    if ($date === '') {
        $errors[] = 'Date is required.';
    }
    if (!in_array($status, ['Present', 'Absent', 'Late', 'Half Day', 'On Leave'])) {
        $status = 'Present';
    }

    if (empty($errors)) {
        if ($editId) {
            // Update existing attendance record
            // Check if check_in and check_out columns exist, if not, use simpler query
            $stmt = $conn->prepare("UPDATE attendance SET employee_id = ?, date = ?, status = ? WHERE attendance_id = ?");
            if ($stmt) {
                $stmt->bind_param('sssi', $employeeId, $date, $status, $editId);
                if ($stmt->execute()) {
                    // If check_in and check_out columns exist, update them separately
                    if ($checkIn !== '' || $checkOut !== '') {
                        $updateTimeStmt = $conn->prepare("UPDATE attendance SET check_in = ?, check_out = ? WHERE attendance_id = ?");
                        if ($updateTimeStmt) {
                            $checkInNull = $checkIn === '' ? null : $checkIn;
                            $checkOutNull = $checkOut === '' ? null : $checkOut;
                            $updateTimeStmt->bind_param('ssi', $checkInNull, $checkOutNull, $editId);
                            $updateTimeStmt->execute();
                            $updateTimeStmt->close();
                        }
                    }
                    $success = 'Attendance record updated successfully.';
                } else {
                    $errors[] = 'Failed to update attendance record: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = 'Database error while preparing update.';
            }
        } else {
            // Check if attendance record already exists for this employee and date
            $checkStmt = $conn->prepare("SELECT attendance_id FROM attendance WHERE employee_id = ? AND date = ?");
            if ($checkStmt) {
                $checkStmt->bind_param('ss', $employeeId, $date);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                if ($result->num_rows > 0) {
                    $errors[] = 'Attendance record already exists for this employee on this date.';
                }
                $checkStmt->close();
            }

            if (empty($errors)) {
                // Create new attendance record
                $stmt = $conn->prepare("INSERT INTO attendance (employee_id, date, status) VALUES (?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('sss', $employeeId, $date, $status);
                    if ($stmt->execute()) {
                        $attendanceId = $conn->insert_id;
                        // If check_in and check_out columns exist, update them
                        if ($checkIn !== '' || $checkOut !== '') {
                            $updateTimeStmt = $conn->prepare("UPDATE attendance SET check_in = ?, check_out = ? WHERE attendance_id = ?");
                            if ($updateTimeStmt) {
                                $checkInNull = $checkIn === '' ? null : $checkIn;
                                $checkOutNull = $checkOut === '' ? null : $checkOut;
                                $updateTimeStmt->bind_param('ssi', $checkInNull, $checkOutNull, $attendanceId);
                                $updateTimeStmt->execute();
                                $updateTimeStmt->close();
                            }
                        }
                        $success = 'Attendance record added successfully.';
                    } else {
                        $errors[] = 'Failed to add attendance record: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $errors[] = 'Database error while preparing insert.';
                }
            }
        }
    }
}

// If editing, fetch the attendance record to prefill the form
$editAttendance = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $stmt = $conn->prepare("SELECT attendance_id, employee_id, date, status, check_in, check_out FROM attendance WHERE attendance_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $editId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $editAttendance = $result->fetch_assoc() ?: null;
            }
            $stmt->close();
        }
    }
}

// Filter parameters
$filterDate = $_GET['filter_date'] ?? date('Y-m-d');
$filterEmployee = $_GET['filter_employee'] ?? '';

// Fetch all attendance records with employee details
$attendanceRecords = [];
$sqlAttendance = "
    SELECT a.attendance_id,
           a.employee_id,
           a.date,
           a.status,
           a.check_in,
           a.check_out,
           CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
           d.department_name
    FROM attendance a
    LEFT JOIN employees e ON a.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE 1=1
";

$params = [];
$types = '';

if ($filterDate !== '') {
    $sqlAttendance .= " AND a.date = ?";
    $params[] = $filterDate;
    $types .= 's';
}

if ($filterEmployee !== '') {
    $sqlAttendance .= " AND a.employee_id = ?";
    $params[] = $filterEmployee;
    $types .= 's';
}

$sqlAttendance .= " ORDER BY a.date DESC, a.attendance_id DESC";

$stmt = $conn->prepare($sqlAttendance);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendanceRecords[] = $row;
    }
    $stmt->close();
} elseif (empty($params)) {
    $result = $conn->query($sqlAttendance);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $attendanceRecords[] = $row;
        }
    }
}

// Fetch all employees for dropdown
$employees = [];
$result = $conn->query("SELECT employee_id, first_name, last_name FROM employees ORDER BY first_name, last_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Get today's date for default value
$todayDate = date('Y-m-d');
?>

<div class="bg-white rounded-xl shadow p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 gap-3">
        <h1 class="text-2xl font-bold">Attendance</h1>

        <?php if ($editAttendance): ?>
            <span class="text-sm text-gray-500">
                Editing attendance for:
                <strong><?php echo htmlspecialchars($editAttendance['employee_id']); ?> - <?php echo htmlspecialchars($editAttendance['date']); ?></strong>
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

    <!-- Filters -->
    <div class="mb-4 p-4 bg-gray-50 rounded-lg border">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Filters</h2>
        <form method="get" action="?page=attendance" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <input type="hidden" name="page" value="attendance">
            
            <div>
                <label class="block text-xs text-gray-600 mb-1">Date</label>
                <input
                    type="date"
                    name="filter_date"
                    value="<?php echo htmlspecialchars($filterDate); ?>"
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>

            <div>
                <label class="block text-xs text-gray-600 mb-1">Employee</label>
                <select
                    name="filter_employee"
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">All Employees</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>"
                            <?php echo $filterEmployee === $emp['employee_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_id'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button
                    type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                    Apply Filters
                </button>
                <a href="?page=attendance"
                   class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-300">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Attendance records list -->
        <div class="lg:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-gray-800">Attendance Records</h2>
                <span class="text-sm text-gray-500">
                    <?php echo count($attendanceRecords); ?> record(s)
                </span>
            </div>

            <div class="border rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-gray-700">
                                <th class="p-3">Date</th>
                                <th class="p-3">Employee</th>
                                <th class="p-3">Department</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Check In</th>
                                <th class="p-3">Check Out</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendanceRecords)): ?>
                                <tr>
                                    <td colspan="7" class="p-4 text-center text-gray-500">
                                        No attendance records found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendanceRecords as $record): ?>
                                    <tr class="border-t hover:bg-gray-50">
                                        <td class="p-3">
                                            <?php echo htmlspecialchars(date('M d, Y', strtotime($record['date']))); ?>
                                        </td>
                                        <td class="p-3">
                                            <div class="font-medium"><?php echo htmlspecialchars($record['employee_name'] ?? '—'); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($record['employee_id']); ?></div>
                                        </td>
                                        <td class="p-3">
                                            <?php echo htmlspecialchars($record['department_name'] ?? '—'); ?>
                                        </td>
                                        <td class="p-3">
                                            <?php
                                            $status = $record['status'];
                                            $statusClasses = [
                                                'Present' => 'bg-green-100 text-green-700',
                                                'Absent' => 'bg-red-100 text-red-700',
                                                'Late' => 'bg-yellow-100 text-yellow-700',
                                                'Half Day' => 'bg-orange-100 text-orange-700',
                                                'On Leave' => 'bg-blue-100 text-blue-700'
                                            ];
                                            $cls = $statusClasses[$status] ?? 'bg-gray-100 text-gray-700';
                                            ?>
                                            <span class="px-2 py-1 rounded-full text-xs <?php echo $cls; ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <?php echo $record['check_in'] ? htmlspecialchars(date('H:i', strtotime($record['check_in']))) : '—'; ?>
                                        </td>
                                        <td class="p-3">
                                            <?php echo $record['check_out'] ? htmlspecialchars(date('H:i', strtotime($record['check_out']))) : '—'; ?>
                                        </td>
                                        <td class="p-3 text-right space-x-2">
                                            <a href="?page=attendance&edit=<?php echo (int) $record['attendance_id']; ?>"
                                               class="text-blue-600 hover:underline text-sm">
                                                Edit
                                            </a>
                                            <a href="?page=attendance&delete=<?php echo (int) $record['attendance_id']; ?>"
                                               onclick="return confirm('Are you sure you want to delete this attendance record?');"
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
                    <?php echo $editAttendance ? 'Edit Attendance' : 'Mark Attendance'; ?>
                </h2>

                <form method="post" class="space-y-3">
                    <?php if ($editAttendance): ?>
                        <input type="hidden" name="edit_id"
                               value="<?php echo (int) $editAttendance['attendance_id']; ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Employee <span class="text-red-500">*</span>
                        </label>
                        <select
                            name="employee_id"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                        >
                            <option value="">— Select Employee —</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>"
                                    <?php echo (isset($editAttendance['employee_id']) && $editAttendance['employee_id'] === $emp['employee_id']) || (isset($_POST['employee_id']) && $_POST['employee_id'] === $emp['employee_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Date <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="date"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($editAttendance['date'] ?? ($_POST['date'] ?? $todayDate)); ?>"
                            required
                        >
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
                            <option value="Present" <?php echo (($editAttendance['status'] ?? ($_POST['status'] ?? 'Present')) === 'Present') ? 'selected' : ''; ?>>Present</option>
                            <option value="Absent" <?php echo (($editAttendance['status'] ?? ($_POST['status'] ?? '')) === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                            <option value="Late" <?php echo (($editAttendance['status'] ?? ($_POST['status'] ?? '')) === 'Late') ? 'selected' : ''; ?>>Late</option>
                            <option value="Half Day" <?php echo (($editAttendance['status'] ?? ($_POST['status'] ?? '')) === 'Half Day') ? 'selected' : ''; ?>>Half Day</option>
                            <option value="On Leave" <?php echo (($editAttendance['status'] ?? ($_POST['status'] ?? '')) === 'On Leave') ? 'selected' : ''; ?>>On Leave</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Check In Time
                        </label>
                        <input
                            type="time"
                            name="check_in"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="<?php echo $editAttendance['check_in'] ? htmlspecialchars(date('H:i', strtotime($editAttendance['check_in']))) : ($_POST['check_in'] ?? ''); ?>"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Check Out Time
                        </label>
                        <input
                            type="time"
                            name="check_out"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="<?php echo $editAttendance['check_out'] ? htmlspecialchars(date('H:i', strtotime($editAttendance['check_out']))) : ($_POST['check_out'] ?? ''); ?>"
                        >
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <?php if ($editAttendance): ?>
                            <a href="?page=attendance"
                               class="text-sm text-gray-600 hover:underline">
                                Cancel edit
                            </a>
                        <?php else: ?>
                            <span class="text-xs text-gray-500">
                                Mark employee attendance.
                            </span>
                        <?php endif; ?>

                        <button
                            type="submit"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                            <?php echo $editAttendance ? 'Save Changes' : 'Mark Attendance'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

