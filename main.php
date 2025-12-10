<?php
session_start();
require_once 'db.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Determine which page to show in the main content (simple router)
$page = $_GET['page'] ?? 'dashboard';

// Search query parameter
$searchQuery = $_GET['search'] ?? '';

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

// Employees list (limit 20) - with search support
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
    WHERE 1=1
";

$params = [];
$types = '';

if ($searchQuery !== '' && $page === 'dashboard') {
    $sqlEmployees .= " AND (e.employee_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ?)";
    $searchPattern = '%' . $searchQuery . '%';
    $params = [$searchPattern, $searchPattern, $searchPattern, $searchPattern];
    $types = 'ssss';
}

$sqlEmployees .= " ORDER BY e.created_at DESC LIMIT 20";

if (!empty($params)) {
    $stmt = $conn->prepare($sqlEmployees);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $employees[] = $row;
        }
        $stmt->close();
    }
} else {
$res = $conn->query($sqlEmployees);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $employees[] = $row;
        }
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

// ====== ATTENDANCE OVERVIEW DATA (for dashboard) ======
$attendanceOverview = [];

// Get attendance statistics for the last 7 days
$attendanceStats = [];
$attendanceByStatus = [
    'Present' => 0,
    'Absent' => 0,
    'Late' => 0,
    'Half Day' => 0,
    'On Leave' => 0
];

// Last 7 days attendance data
$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayName = date('D', strtotime("-$i days"));
    $last7Days[] = [
        'date' => $date,
        'day' => $dayName,
        'present' => 0,
        'absent' => 0,
        'late' => 0,
        'half_day' => 0,
        'on_leave' => 0,
        'total' => 0
    ];
}

// Get attendance data for last 7 days
$sqlLast7Days = "
    SELECT DATE(date) as attendance_date, status, COUNT(*) as count
    FROM attendance
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(date), status
    ORDER BY attendance_date ASC
";
$res = $conn->query($sqlLast7Days);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $date = $row['attendance_date'];
        $status = $row['status'];
        $count = (int)$row['count'];
        
        // Find the day in our array
        foreach ($last7Days as &$day) {
            if ($day['date'] === $date) {
                switch ($status) {
                    case 'Present':
                        $day['present'] = $count;
                        break;
                    case 'Absent':
                        $day['absent'] = $count;
                        break;
                    case 'Late':
                        $day['late'] = $count;
                        break;
                    case 'Half Day':
                        $day['half_day'] = $count;
                        break;
                    case 'On Leave':
                        $day['on_leave'] = $count;
                        break;
                }
                $day['total'] += $count;
                break;
            }
        }
        unset($day);
        
        // Overall statistics
        if (isset($attendanceByStatus[$status])) {
            $attendanceByStatus[$status] += $count;
        }
    }
}

// Calculate attendance rate (last 7 days)
$totalRecords = array_sum($attendanceByStatus);
$attendanceRate = $totalRecords > 0 ? round(($attendanceByStatus['Present'] / $totalRecords) * 100, 1) : 0;

// Get max count for chart scaling
$maxCount = 0;
foreach ($last7Days as $day) {
    if ($day['total'] > $maxCount) {
        $maxCount = $day['total'];
    }
}
if ($maxCount === 0) $maxCount = 1; // Prevent division by zero

// This month's attendance summary
$monthStart = date('Y-m-01');
$monthStats = [
    'total_days' => 0,
    'present' => 0,
    'absent' => 0,
    'late' => 0
];
$sqlMonthStats = "
    SELECT status, COUNT(*) as count
    FROM attendance
    WHERE date >= ?
    GROUP BY status
";
$stmt = $conn->prepare($sqlMonthStats);
if ($stmt) {
    $stmt->bind_param('s', $monthStart);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $status = $row['status'];
        $count = (int)$row['count'];
        if (isset($monthStats[$status])) {
            $monthStats[$status] = $count;
        }
        $monthStats['total_days'] += $count;
    }
    $stmt->close();
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
                <a class="block py-3 px-6 <?php echo ($page === 'dashboard' || $page === '') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'; ?>" href="?page=dashboard">Dashboard</a>
                <a class="block py-3 px-6 <?php echo $page === 'employees' ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'; ?>" href="?page=employees">Employees</a>
                <a class="block py-3 px-6 <?php echo $page === 'attendance' ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'; ?>" href="?page=attendance">Attendance</a>
                <a class="block py-3 px-6 <?php echo $page === 'leave_requests' ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'; ?>" href="?page=leave_requests">Leave Requests</a>
                <a class="block py-3 px-6 <?php echo $page === 'departments' ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'; ?>" href="?page=departments">Departments</a>
                <a class="block py-3 px-6 <?php echo $page === 'settings' ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'; ?>" href="?page=settings">Settings</a>
            </nav>
        </aside>

        <!-- ====== MAIN CONTENT ====== -->
        <div class="flex-1 flex flex-col">

            <!-- ====== TOP NAVBAR ====== -->
            <nav class="bg-white shadow-md px-4 py-3 flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <button class="md:hidden text-gray-600 text-2xl" onclick="toggleSidebar()">☰</button>

                    <form method="get" action="main.php" class="flex items-center">
                        <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">
                        <input type="text" 
                               name="search" 
                               placeholder="Search employees..."
                               value="<?php echo htmlspecialchars($searchQuery); ?>"
                        class="border rounded-lg px-4 py-2 w-48 md:w-72 bg-gray-50 focus:outline-blue-500">
                        <?php if ($searchQuery): ?>
                            <a href="?page=<?php echo htmlspecialchars($page); ?>" class="ml-2 text-gray-500 hover:text-gray-700">
                                ✕
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="flex items-center space-x-6">
                    <?php if ($pendingRequests > 0): ?>
                        <a href="?page=leave_requests&filter_status=Pending" class="relative">
                            <span class="text-xl">🔔</span>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full">
                                <?php echo $pendingRequests > 9 ? '9+' : $pendingRequests; ?>
                            </span>
                        </a>
                    <?php else: ?>
                    <button class="relative">
                        <span class="text-xl">🔔</span>
                    </button>
                    <?php endif; ?>

                    <div class="relative group" id="userMenu">
                        <div class="flex items-center space-x-2 cursor-pointer" onclick="toggleUserMenu()">
                        <img src="https://via.placeholder.com/35" class="rounded-full" />
                        <span class="font-medium">
                            <?php echo htmlspecialchars($_SESSION['name']); ?>
                        </span>
                            <span class="text-gray-400">▼</span>
                        </div>
                        <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border hidden z-50">
                            <a href="?page=settings&tab=profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-t-lg">Profile Settings</a>
                            <a href="?logout=1" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-b-lg">Logout</a>
                        </div>
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

                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-semibold">Recent Employees</h2>

                                <div class="flex gap-2">
                                    <a href="?page=employees"
                                        class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg shadow hover:bg-gray-200 text-sm">
                                        View All
                                    </a>
                                    <a href="?page=employees"
                                        class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 text-sm">
                                    + Add Employee
                                    </a>
                                </div>
                            </div>

                            <?php if ($searchQuery): ?>
                                <div class="mb-4 p-3 bg-blue-50 rounded-lg text-sm text-blue-700">
                                    Showing results for: <strong><?php echo htmlspecialchars($searchQuery); ?></strong>
                                    <a href="?page=dashboard" class="ml-2 underline">Clear search</a>
                                </div>
                            <?php endif; ?>

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
                                                        <a href="?page=employees&edit=<?php echo urlencode($emp['employee_id']); ?>" class="text-blue-600 hover:underline">View</a>
                                                        <a href="?page=employees&edit=<?php echo urlencode($emp['employee_id']); ?>" class="text-yellow-600 hover:underline">Edit</a>
                                                        <a href="?page=employees&delete=<?php echo urlencode($emp['employee_id']); ?>" 
                                                           onclick="return confirm('Are you sure you want to delete this employee?');"
                                                           class="text-red-600 hover:underline">Delete</a>
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

                    <!-- ====== ATTENDANCE OVERVIEW ====== -->
                    <div class="bg-white mt-6 p-6 rounded-xl shadow">
                        <h2 class="text-xl font-semibold mb-6">Attendance Overview</h2>

                        <!-- Statistics Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                <p class="text-sm text-blue-600 font-medium">Attendance Rate</p>
                                <h3 class="text-2xl font-bold text-blue-700 mt-1"><?php echo $attendanceRate; ?>%</h3>
                                <p class="text-xs text-gray-500 mt-1">Last 7 days</p>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                                <p class="text-sm text-green-600 font-medium">Present</p>
                                <h3 class="text-2xl font-bold text-green-700 mt-1"><?php echo $attendanceByStatus['Present']; ?></h3>
                                <p class="text-xs text-gray-500 mt-1">Last 7 days</p>
                            </div>
                            <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                                <p class="text-sm text-red-600 font-medium">Absent</p>
                                <h3 class="text-2xl font-bold text-red-700 mt-1"><?php echo $attendanceByStatus['Absent']; ?></h3>
                                <p class="text-xs text-gray-500 mt-1">Last 7 days</p>
                            </div>
                            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                                <p class="text-sm text-yellow-600 font-medium">Late</p>
                                <h3 class="text-2xl font-bold text-yellow-700 mt-1"><?php echo $attendanceByStatus['Late']; ?></h3>
                                <p class="text-xs text-gray-500 mt-1">Last 7 days</p>
                            </div>
                        </div>

                        <!-- 7-Day Attendance Chart -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">Last 7 Days Attendance</h3>
                            <div class="bg-gray-50 p-4 rounded-lg border">
                                <div class="flex items-end justify-between gap-2 h-64">
                                    <?php foreach ($last7Days as $day): ?>
                                        <div class="flex-1 flex flex-col items-center">
                                            <div class="w-full flex flex-col justify-end items-center gap-1" style="height: 200px;">
                                                <!-- Stacked bars for different statuses -->
                                                <div class="w-full flex flex-col-reverse gap-0.5">
                                                    <?php if ($day['present'] > 0): ?>
                                                        <div class="bg-green-500 rounded-t" 
                                                             style="height: <?php echo ($day['present'] / $maxCount) * 180; ?>px;"
                                                             title="Present: <?php echo $day['present']; ?>">
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($day['absent'] > 0): ?>
                                                        <div class="bg-red-500" 
                                                             style="height: <?php echo ($day['absent'] / $maxCount) * 180; ?>px;"
                                                             title="Absent: <?php echo $day['absent']; ?>">
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($day['late'] > 0): ?>
                                                        <div class="bg-yellow-500" 
                                                             style="height: <?php echo ($day['late'] / $maxCount) * 180; ?>px;"
                                                             title="Late: <?php echo $day['late']; ?>">
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($day['half_day'] > 0): ?>
                                                        <div class="bg-orange-500" 
                                                             style="height: <?php echo ($day['half_day'] / $maxCount) * 180; ?>px;"
                                                             title="Half Day: <?php echo $day['half_day']; ?>">
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($day['on_leave'] > 0): ?>
                                                        <div class="bg-blue-500 rounded-b" 
                                                             style="height: <?php echo ($day['on_leave'] / $maxCount) * 180; ?>px;"
                                                             title="On Leave: <?php echo $day['on_leave']; ?>">
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($day['total'] === 0): ?>
                                                        <div class="bg-gray-300 rounded" 
                                                             style="height: 10px;"
                                                             title="No data">
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <!-- Count label -->
                                                <?php if ($day['total'] > 0): ?>
                                                    <span class="text-xs font-medium text-gray-700 mt-1"><?php echo $day['total']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <!-- Day label -->
                                            <div class="mt-2 text-center">
                                                <p class="text-xs font-medium text-gray-700"><?php echo $day['day']; ?></p>
                                                <p class="text-xs text-gray-500"><?php echo date('M j', strtotime($day['date'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <!-- Legend -->
                                <div class="flex flex-wrap justify-center gap-4 mt-4 pt-4 border-t">
                                    <div class="flex items-center gap-2">
                                        <div class="w-4 h-4 bg-green-500 rounded"></div>
                                        <span class="text-xs text-gray-600">Present</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="w-4 h-4 bg-red-500 rounded"></div>
                                        <span class="text-xs text-gray-600">Absent</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="w-4 h-4 bg-yellow-500 rounded"></div>
                                        <span class="text-xs text-gray-600">Late</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="w-4 h-4 bg-orange-500 rounded"></div>
                                        <span class="text-xs text-gray-600">Half Day</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="w-4 h-4 bg-blue-500 rounded"></div>
                                        <span class="text-xs text-gray-600">On Leave</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- This Month Summary -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">This Month Summary</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="p-4 bg-gray-50 rounded-lg border">
                                    <p class="text-sm text-gray-600">Total Records</p>
                                    <h4 class="text-xl font-bold text-gray-800 mt-1"><?php echo $monthStats['total_days']; ?></h4>
                                </div>
                                <div class="p-4 bg-gray-50 rounded-lg border">
                                    <p class="text-sm text-gray-600">Present This Month</p>
                                    <h4 class="text-xl font-bold text-green-700 mt-1"><?php echo $monthStats['present']; ?></h4>
                                </div>
                                <div class="p-4 bg-gray-50 rounded-lg border">
                                    <p class="text-sm text-gray-600">Absent This Month</p>
                                    <h4 class="text-xl font-bold text-red-700 mt-1"><?php echo $monthStats['absent']; ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- ====== JAVASCRIPT ====== -->
    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector("aside");
            sidebar.classList.toggle("hidden");
        }

        function toggleUserMenu() {
            const dropdown = document.getElementById("userDropdown");
            dropdown.classList.toggle("hidden");
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById("userMenu");
            const dropdown = document.getElementById("userDropdown");
            if (userMenu && dropdown && !userMenu.contains(event.target)) {
                dropdown.classList.add("hidden");
            }
        });

        // Auto-submit search on Enter key
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        this.form.submit();
                    }
                });
            }
        });
    </script>

</body>

</html>
