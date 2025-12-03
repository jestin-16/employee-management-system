<?php
session_start();
require_once 'db.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Determine which page to show in the main content (simple router)
$page = $_GET['page'] ?? 'dashboard';

// ====== DASHBOARD QUERIES (used for the main dashboard view) ======

// Total employees
$totalEmployees = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM employees");
if ($res) {
    $row = $res->fetch_assoc();
    $totalEmployees = (int)$row['cnt'];
}

// Present today
$presentToday = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM attendance WHERE date = CURDATE() AND status = 'Present'");
if ($res) {
    $row = $res->fetch_assoc();
    $presentToday = (int)$row['cnt'];
}

// On leave today (approved requests overlapping today)
$onLeaveToday = 0;
$res = $conn->query("SELECT COUNT(DISTINCT employee_id) AS cnt 
                     FROM leave_requests 
                     WHERE status = 'Approved' 
                       AND start_date <= CURDATE() 
                       AND end_date >= CURDATE()");
if ($res) {
    $row = $res->fetch_assoc();
    $onLeaveToday = (int)$row['cnt'];
}

// Pending leave requests
$pendingRequests = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM leave_requests WHERE status = 'Pending'");
if ($res) {
    $row = $res->fetch_assoc();
    $pendingRequests = (int)$row['cnt'];
}

// Employees list (limit 20)
$employees = [];
$sqlEmployees = "
    SELECT e.employee_id,
           CONCAT(e.first_name, ' ', e.last_name) AS full_name,
           r.role_name,
           d.department_name,
           e.status
    FROM employees e
    LEFT JOIN roles r ON e.role_id = r.role_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    ORDER BY e.created_at DESC
    LIMIT 20
";
$res = $conn->query($sqlEmployees);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Recent activities (limit 5)
$activities = [];
$sqlActivities = "
    SELECT a.activity_type, a.description, a.created_at, u.name AS user_name
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.user_id
    ORDER BY a.created_at DESC
    LIMIT 5
";
$res = $conn->query($sqlActivities);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $activities[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>HR Employee Management Dashboard</title>

    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">

    <!-- ====== LAYOUT WRAPPER ====== -->
    <div class="flex h-screen">

        <!-- ====== SIDEBAR ====== -->
        <aside class="w-64 bg-white shadow-lg hidden md:block">
            <div class="p-6 text-2xl font-bold text-blue-600">HR Dashboard</div>

            <nav class="mt-4">
                <a class="block py-3 px-6 text-gray-700 hover:bg-blue-50 hover:text-blue-600" href="?page=dashboard">Dashboard</a>
                <a class="block py-3 px-6 text-gray-700 hover:bg-blue-50 hover:text-blue-600" href="?page=employees">Employees</a>
                <a class="block py-3 px-6 text-gray-700 hover:bg-blue-50 hover:text-blue-600" href="?page=attendance">Attendance</a>
                <a class="block py-3 px-6 text-gray-700 hover:bg-blue-50 hover:text-blue-600" href="?page=leave_requests">Leave Requests</a>
                <a class="block py-3 px-6 text-gray-700 hover:bg-blue-50 hover:text-blue-600" href="?page=departments">Departments</a>
                <a class="block py-3 px-6 text-gray-700 hover:bg-blue-50 hover:text-blue-600" href="?page=settings">Settings</a>
            </nav>
        </aside>

        <!-- ====== MAIN CONTENT ====== -->
        <div class="flex-1 flex flex-col">

            <!-- ====== TOP NAVBAR ====== -->
            <nav class="bg-white shadow-md px-4 py-3 flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <button class="md:hidden text-gray-600 text-2xl" onclick="toggleSidebar()">☰</button>

                    <input type="text" placeholder="Search..."
                        class="border rounded-lg px-4 py-2 w-48 md:w-72 bg-gray-50 focus:outline-blue-500">
                </div>

                <div class="flex items-center space-x-6">
                    <button class="relative">
                        <span class="text-xl">🔔</span>
                        <span
                            class="absolute -top-1 -right-1 bg-red-500 text-white text-xs px-1 rounded-full">3</span>
                    </button>

                    <div class="flex items-center space-x-2 cursor-pointer">
                        <img src="https://via.placeholder.com/35" class="rounded-full" />
                        <span class="font-medium">
                            <?php echo htmlspecialchars($_SESSION['name']); ?>
                        </span>
                    </div>
                </div>
            </nav>

            <!-- ====== CONTENT AREA ====== -->
            <div class="p-6 overflow-auto">

                <?php if ($page === 'departments'): ?>
                    <!-- Departments page content is loaded inside the main layout -->
                    <?php include 'Departments.php'; ?>

                <?php elseif ($page === 'employees'): ?>
                    <!-- Employees page -->
                    <?php include 'employees.php'; ?>

                <?php elseif ($page === 'attendance'): ?>
                    <!-- Attendance page -->
                    <?php include 'Attendance.php'; ?>

                <?php elseif ($page === 'leave_requests'): ?>
                    <!-- Leave Requests page -->
                    <?php include 'leave_requests.php'; ?>

                <?php elseif ($page === 'settings'): ?>
                    <!-- Settings page -->
                    <?php include 'settings.php'; ?>

                <?php else: ?>

                    <!-- ====== DASHBOARD CARDS ====== -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">

                        <div class="bg-white p-6 rounded-xl shadow">
                            <p class="text-gray-500">Total Employees</p>
                            <h2 class="text-3xl font-semibold mt-2">
                                <?php echo $totalEmployees; ?>
                            </h2>
                        </div>

                        <div class="bg-white p-6 rounded-xl shadow">
                            <p class="text-gray-500">Present Today</p>
                            <h2 class="text-3xl font-semibold mt-2">
                                <?php echo $presentToday; ?>
                            </h2>
                        </div>

                        <div class="bg-white p-6 rounded-xl shadow">
                            <p class="text-gray-500">On Leave</p>
                            <h2 class="text-3xl font-semibold mt-2">
                                <?php echo $onLeaveToday; ?>
                            </h2>
                        </div>

                        <div class="bg-white p-6 rounded-xl shadow">
                            <p class="text-gray-500">Pending Requests</p>
                            <h2 class="text-3xl font-semibold mt-2">
                                <?php echo $pendingRequests; ?>
                            </h2>
                        </div>

                    </div>

                    <!-- ====== EMPLOYEE TABLE + ACTIVITIES ====== -->
                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                        <!-- ====== EMPLOYEE TABLE ====== -->
                        <div class="xl:col-span-2 bg-white rounded-xl shadow p-6">

                            <div class="flex justify-between mb-4">
                                <h2 class="text-xl font-semibold">Employees</h2>

                                <button onclick="openModal()"
                                    class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700">
                                    + Add Employee
                                </button>
                            </div>

                            <input type="text" placeholder="Search employees..."
                                class="border px-4 py-2 rounded-lg w-full mb-4 bg-gray-50">

                            <div class="overflow-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-gray-100 text-gray-600">
                                            <th class="p-3">Employee ID</th>
                                            <th class="p-3">Name</th>
                                            <th class="p-3">Role</th>
                                            <th class="p-3">Department</th>
                                            <th class="p-3">Status</th>
                                            <th class="p-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($employees)): ?>
                                            <tr>
                                                <td colspan="6" class="p-4 text-center text-gray-500">
                                                    No employees found.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($employees as $emp): ?>
                                                <tr class="border-b">
                                                    <td class="p-3">
                                                        <?php echo htmlspecialchars($emp['employee_id']); ?>
                                                    </td>
                                                    <td class="p-3">
                                                        <?php echo htmlspecialchars($emp['full_name']); ?>
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
                                                        <span class="px-3 py-1 rounded-full text-sm <?php echo $cls; ?>">
                                                            <?php echo htmlspecialchars($status); ?>
                                                        </span>
                                                    </td>
                                                    <td class="p-3 space-x-2">
                                                        <button class="text-blue-600">View</button>
                                                        <button class="text-yellow-600">Edit</button>
                                                        <button class="text-red-600">Delete</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ====== RECENT ACTIVITIES ====== -->
                        <div class="bg-white rounded-xl shadow p-6">
                            <h2 class="text-xl font-semibold mb-4">Recent Activities</h2>

                            <ul class="space-y-4">
                                <?php if (empty($activities)): ?>
                                    <li class="text-gray-500">No recent activities.</li>
                                <?php else: ?>
                                    <?php foreach ($activities as $act): ?>
                                        <li class="border-b pb-3 last:border-b-0">
                                            <p class="font-medium">
                                                <?php echo htmlspecialchars($act['activity_type']); ?>
                                                <?php if (!empty($act['user_name'])): ?>
                                                    <span class="text-sm text-gray-500">
                                                        by <?php echo htmlspecialchars($act['user_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <?php echo htmlspecialchars($act['description']); ?>
                                            </p>
                                            <span class="text-xs text-gray-500">
                                                <?php echo htmlspecialchars($act['created_at']); ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- ====== ATTENDANCE CHART PLACEHOLDER ====== -->
                    <div class="bg-white mt-6 p-6 rounded-xl shadow">
                        <h2 class="text-xl font-semibold mb-4">Attendance Overview</h2>

                        <div class="h-64 bg-gray-50 border rounded-lg flex items-center justify-center text-gray-500">
                            Chart Placeholder
                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- ====== ADD EMPLOYEE MODAL ====== -->
    <div id="modal"
        class="fixed inset-0 bg-black bg-opacity-40 hidden justify-center items-center p-4 backdrop-blur-sm">

        <div class="bg-white rounded-lg w-full max-w-lg p-6 shadow-lg">
            <h2 class="text-xl font-semibold mb-4">Add New Employee</h2>

            <div class="grid grid-cols-2 gap-4">
                <input type="text" placeholder="Employee ID" class="p-2 border rounded">
                <input type="text" placeholder="Name" class="p-2 border rounded">
                <input type="text" placeholder="Role" class="p-2 border rounded">
                <input type="text" placeholder="Department" class="p-2 border rounded">
                <input type="text" placeholder="Status" class="p-2 border rounded">
            </div>

            <div class="text-right mt-4">
                <button onclick="closeModal()" class="px-4 py-2 mr-2 rounded bg-gray-200">Cancel</button>
                <button class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Save</button>
            </div>
        </div>
    </div>

    <!-- ====== JAVASCRIPT ====== -->
    <script>
        const modal = document.getElementById("modal");

        function openModal() {
            modal.classList.remove("hidden");
            modal.classList.add("flex");
        }
        function closeModal() {
            modal.classList.add("hidden");
        }

        function toggleSidebar() {
            const sidebar = document.querySelector("aside");
            sidebar.classList.toggle("hidden");
        }
    </script>

</body>

</html>
