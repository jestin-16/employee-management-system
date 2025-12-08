<?php
// Leave Requests management page content (loaded inside main.php)

// We assume $conn is already available from main.php (via db.php).

$errors = [];
$success = '';

// Handle delete action (GET)
if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    if ($deleteId > 0) {
        $stmt = $conn->prepare("DELETE FROM leave_requests WHERE leave_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $deleteId);
            if ($stmt->execute()) {
                $success = 'Leave request deleted successfully.';
            } else {
                $errors[] = 'Failed to delete leave request.';
            }
            $stmt->close();
        } else {
            $errors[] = 'Database error while preparing delete.';
        }
    }
}

// Handle approve/reject action (GET)
if (isset($_GET['approve'])) {
    $approveId = (int) $_GET['approve'];
    $action = $_GET['action'] ?? 'approve'; // approve or reject

    if ($approveId > 0) {
        $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
        $approvedBy = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $stmt = $conn->prepare("UPDATE leave_requests SET status = ?, approved_by = ? WHERE leave_id = ?");
        if ($stmt) {
            $stmt->bind_param('sii', $newStatus, $approvedBy, $approveId);
            if ($stmt->execute()) {
                $success = 'Leave request ' . strtolower($newStatus) . ' successfully.';
            } else {
                $errors[] = 'Failed to update leave request status.';
            }
            $stmt->close();
        } else {
            $errors[] = 'Database error while preparing update.';
        }
    }
}

// Handle create / update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeId = trim($_POST['employee_id'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');
    $leaveType = trim($_POST['leave_type'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $status = trim($_POST['status'] ?? 'Pending');
    $editId = isset($_POST['edit_id']) && $_POST['edit_id'] !== '' ? (int) $_POST['edit_id'] : null;

    // Validation
    if ($employeeId === '') {
        $errors[] = 'Employee is required.';
    }
    if ($startDate === '') {
        $errors[] = 'Start date is required.';
    }
    if ($endDate === '') {
        $errors[] = 'End date is required.';
    }
    if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
        $errors[] = 'End date must be after or equal to start date.';
    }
    if (!in_array($status, ['Pending', 'Approved', 'Rejected'])) {
        $status = 'Pending';
    }

    if (empty($errors)) {
        if ($editId) {
            // Update existing leave request
            $stmt = $conn->prepare("UPDATE leave_requests SET employee_id = ?, start_date = ?, end_date = ?, leave_type = ?, reason = ?, status = ? WHERE leave_id = ?");
            if ($stmt) {
                $leaveTypeNull = $leaveType === '' ? null : $leaveType;
                $reasonNull = $reason === '' ? null : $reason;
                $stmt->bind_param('ssssssi', $employeeId, $startDate, $endDate, $leaveTypeNull, $reasonNull, $status, $editId);
                if ($stmt->execute()) {
                    $success = 'Leave request updated successfully.';
                } else {
                    $errors[] = 'Failed to update leave request: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = 'Database error while preparing update.';
            }
        } else {
            // Create new leave request
            $stmt = $conn->prepare("INSERT INTO leave_requests (employee_id, start_date, end_date, leave_type, reason, status) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $leaveTypeNull = $leaveType === '' ? null : $leaveType;
                $reasonNull = $reason === '' ? null : $reason;
                $stmt->bind_param('ssssss', $employeeId, $startDate, $endDate, $leaveTypeNull, $reasonNull, $status);
                if ($stmt->execute()) {
                    $success = 'Leave request added successfully.';
                } else {
                    $errors[] = 'Failed to add leave request: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = 'Database error while preparing insert.';
            }
        }
    }
}

// If editing, fetch the leave request to prefill the form
$editLeaveRequest = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $stmt = $conn->prepare("SELECT leave_id, employee_id, start_date, end_date, leave_type, reason, status, approved_by FROM leave_requests WHERE leave_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $editId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $editLeaveRequest = $result->fetch_assoc() ?: null;
            }
            $stmt->close();
        }
    }
}

// Filter parameters
$filterStatus = $_GET['filter_status'] ?? '';
$filterEmployee = $_GET['filter_employee'] ?? '';
$filterStartDate = $_GET['filter_start_date'] ?? '';
$filterEndDate = $_GET['filter_end_date'] ?? '';

// Fetch all leave requests with employee details
$leaveRequests = [];
$sqlLeaveRequests = "
    SELECT lr.leave_id,
           lr.employee_id,
           lr.start_date,
           lr.end_date,
           lr.leave_type,
           lr.reason,
           lr.status,
           lr.applied_at,
           lr.approved_by,
           CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
           d.department_name,
           u.name AS approved_by_name
    FROM leave_requests lr
    LEFT JOIN employees e ON lr.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN users u ON lr.approved_by = u.user_id
    WHERE 1=1
";

$params = [];
$types = '';

if ($filterStatus !== '') {
    $sqlLeaveRequests .= " AND lr.status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}

if ($filterEmployee !== '') {
    $sqlLeaveRequests .= " AND lr.employee_id = ?";
    $params[] = $filterEmployee;
    $types .= 's';
}

if ($filterStartDate !== '') {
    $sqlLeaveRequests .= " AND lr.start_date >= ?";
    $params[] = $filterStartDate;
    $types .= 's';
}

if ($filterEndDate !== '') {
    $sqlLeaveRequests .= " AND lr.end_date <= ?";
    $params[] = $filterEndDate;
    $types .= 's';
}

$sqlLeaveRequests .= " ORDER BY lr.applied_at DESC, lr.leave_id DESC";

$stmt = $conn->prepare($sqlLeaveRequests);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $leaveRequests[] = $row;
    }
    $stmt->close();
} elseif (empty($params)) {
    $result = $conn->query($sqlLeaveRequests);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $leaveRequests[] = $row;
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

// Calculate days for each leave request
foreach ($leaveRequests as &$lr) {
    if ($lr['start_date'] && $lr['end_date']) {
        $start = new DateTime($lr['start_date']);
        $end = new DateTime($lr['end_date']);
        $end->modify('+1 day'); // Include end date
        $interval = $start->diff($end);
        $lr['days'] = $interval->days;
    } else {
        $lr['days'] = 0;
    }
}
unset($lr);
?>

<div class="bg-white rounded-xl shadow p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 gap-3">
        <h1 class="text-2xl font-bold">Leave Requests</h1>

        <?php if ($editLeaveRequest): ?>
            <span class="text-sm text-gray-500">
                Editing leave request for:
                <strong><?php echo htmlspecialchars($editLeaveRequest['employee_id']); ?></strong>
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
        <form method="get" action="?page=leave_requests" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <input type="hidden" name="page" value="leave_requests">
            
            <div>
                <label class="block text-xs text-gray-600 mb-1">Status</label>
                <select
                    name="filter_status"
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">All Statuses</option>
                    <option value="Pending" <?php echo $filterStatus === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Approved" <?php echo $filterStatus === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="Rejected" <?php echo $filterStatus === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
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

            <div>
                <label class="block text-xs text-gray-600 mb-1">Start Date (From)</label>
                <input
                    type="date"
                    name="filter_start_date"
                    value="<?php echo htmlspecialchars($filterStartDate); ?>"
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>

            <div>
                <label class="block text-xs text-gray-600 mb-1">End Date (To)</label>
                <input
                    type="date"
                    name="filter_end_date"
                    value="<?php echo htmlspecialchars($filterEndDate); ?>"
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>

            <div class="md:col-span-4 flex items-end gap-2">
                <button
                    type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                    Apply Filters
                </button>
                <a href="?page=leave_requests"
                   class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-300">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Leave Requests list -->
        <div class="lg:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-gray-800">All Leave Requests</h2>
                <span class="text-sm text-gray-500">
                    <?php echo count($leaveRequests); ?> request(s)
                </span>
            </div>

            <div class="border rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-gray-700">
                                <th class="p-3">Employee</th>
                                <th class="p-3">Department</th>
                                <th class="p-3">Leave Period</th>
                                <th class="p-3">Days</th>
                                <th class="p-3">Type</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leaveRequests)): ?>
                                <tr>
                                    <td colspan="7" class="p-4 text-center text-gray-500">
                                        No leave requests found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leaveRequests as $request): ?>
                                    <tr class="border-t hover:bg-gray-50">
                                        <td class="p-3">
                                            <div class="font-medium"><?php echo htmlspecialchars($request['employee_name'] ?? '—'); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($request['employee_id']); ?></div>
                                        </td>
                                        <td class="p-3">
                                            <?php echo htmlspecialchars($request['department_name'] ?? '—'); ?>
                                        </td>
                                        <td class="p-3">
                                            <div class="text-xs">
                                                <?php echo htmlspecialchars(date('M d, Y', strtotime($request['start_date']))); ?>
                                                <span class="text-gray-400">→</span>
                                                <?php echo htmlspecialchars(date('M d, Y', strtotime($request['end_date']))); ?>
                                            </div>
                                        </td>
                                        <td class="p-3">
                                            <span class="font-medium"><?php echo (int) $request['days']; ?></span>
                                        </td>
                                        <td class="p-3">
                                            <?php echo htmlspecialchars($request['leave_type'] ?? '—'); ?>
                                        </td>
                                        <td class="p-3">
                                            <?php
                                            $status = $request['status'];
                                            $statusClasses = [
                                                'Pending' => 'bg-yellow-100 text-yellow-700',
                                                'Approved' => 'bg-green-100 text-green-700',
                                                'Rejected' => 'bg-red-100 text-red-700'
                                            ];
                                            $cls = $statusClasses[$status] ?? 'bg-gray-100 text-gray-700';
                                            ?>
                                            <span class="px-2 py-1 rounded-full text-xs <?php echo $cls; ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                        <td class="p-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <?php if ($request['status'] === 'Pending'): ?>
                                                    <a href="?page=leave_requests&approve=<?php echo (int) $request['leave_id']; ?>&action=approve"
                                                       onclick="return confirm('Approve this leave request?');"
                                                       class="text-green-600 hover:underline text-xs"
                                                       title="Approve">
                                                        ✓ Approve
                                                    </a>
                                                    <a href="?page=leave_requests&approve=<?php echo (int) $request['leave_id']; ?>&action=reject"
                                                       onclick="return confirm('Reject this leave request?');"
                                                       class="text-red-600 hover:underline text-xs"
                                                       title="Reject">
                                                        ✗ Reject
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?page=leave_requests&edit=<?php echo (int) $request['leave_id']; ?>"
                                                   class="text-blue-600 hover:underline text-xs">
                                                    Edit
                                                </a>
                                                <a href="?page=leave_requests&delete=<?php echo (int) $request['leave_id']; ?>"
                                                   onclick="return confirm('Are you sure you want to delete this leave request?');"
                                                   class="text-red-600 hover:underline text-xs">
                                                    Delete
                                                </a>
                                            </div>
                                            <?php if ($request['reason']): ?>
                                                <div class="text-xs text-gray-500 mt-1 text-left">
                                                    <strong>Reason:</strong> <?php echo htmlspecialchars($request['reason']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($request['approved_by_name']): ?>
                                                <div class="text-xs text-gray-500 mt-1 text-left">
                                                    <strong>Approved by:</strong> <?php echo htmlspecialchars($request['approved_by_name']); ?>
                                                </div>
                                            <?php endif; ?>
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
                    <?php echo $editLeaveRequest ? 'Edit Leave Request' : 'New Leave Request'; ?>
                </h2>

                <form method="post" class="space-y-3">
                    <?php if ($editLeaveRequest): ?>
                        <input type="hidden" name="edit_id"
                               value="<?php echo (int) $editLeaveRequest['leave_id']; ?>">
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
                                    <?php echo (isset($editLeaveRequest['employee_id']) && $editLeaveRequest['employee_id'] === $emp['employee_id']) || (isset($_POST['employee_id']) && $_POST['employee_id'] === $emp['employee_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Start Date <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="start_date"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($editLeaveRequest['start_date'] ?? ($_POST['start_date'] ?? '')); ?>"
                            required
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            End Date <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="end_date"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($editLeaveRequest['end_date'] ?? ($_POST['end_date'] ?? '')); ?>"
                            required
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Leave Type
                        </label>
                        <select
                            name="leave_type"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">— Select Type —</option>
                            <option value="Sick Leave" <?php echo (($editLeaveRequest['leave_type'] ?? ($_POST['leave_type'] ?? '')) === 'Sick Leave') ? 'selected' : ''; ?>>Sick Leave</option>
                            <option value="Vacation" <?php echo (($editLeaveRequest['leave_type'] ?? ($_POST['leave_type'] ?? '')) === 'Vacation') ? 'selected' : ''; ?>>Vacation</option>
                            <option value="Personal" <?php echo (($editLeaveRequest['leave_type'] ?? ($_POST['leave_type'] ?? '')) === 'Personal') ? 'selected' : ''; ?>>Personal</option>
                            <option value="Emergency" <?php echo (($editLeaveRequest['leave_type'] ?? ($_POST['leave_type'] ?? '')) === 'Emergency') ? 'selected' : ''; ?>>Emergency</option>
                            <option value="Other" <?php echo (($editLeaveRequest['leave_type'] ?? ($_POST['leave_type'] ?? '')) === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Reason
                        </label>
                        <textarea
                            name="reason"
                            rows="3"
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Optional reason for leave..."
                        ><?php echo htmlspecialchars($editLeaveRequest['reason'] ?? ($_POST['reason'] ?? '')); ?></textarea>
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
                            <option value="Pending" <?php echo (($editLeaveRequest['status'] ?? ($_POST['status'] ?? 'Pending')) === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo (($editLeaveRequest['status'] ?? ($_POST['status'] ?? '')) === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo (($editLeaveRequest['status'] ?? ($_POST['status'] ?? '')) === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <?php if ($editLeaveRequest): ?>
                            <a href="?page=leave_requests"
                               class="text-sm text-gray-600 hover:underline">
                                Cancel edit
                            </a>
                        <?php else: ?>
                            <span class="text-xs text-gray-500">
                                Create a new leave request.
                            </span>
                        <?php endif; ?>

                        <button
                            type="submit"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                            <?php echo $editLeaveRequest ? 'Save Changes' : 'Submit Request'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
