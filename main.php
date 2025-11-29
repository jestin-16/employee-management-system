<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard | Employee Management System</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .sidebar-gradient {
            background: linear-gradient(180deg, #1e40af 0%, #1e3a8a 100%);
        }

        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-4px);
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .nav-item {
            transition: all 0.2s ease;
            border-radius: 8px;
            margin: 0 12px;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 4px solid #60a5fa;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        .gradient-text {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>

<body class="bg-gray-50">

    <div class="flex min-h-screen">

        <!-- Sidebar -->
        <aside class="w-72 sidebar-gradient text-white min-h-screen shadow-2xl">
            <div class="p-8 border-b border-blue-700">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                        <span class="text-2xl">👔</span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">HR Panel</h1>
                        <p class="text-sm text-blue-200 font-medium">Employee Management</p>
                    </div>
                </div>
            </div>

            <nav class="mt-8 space-y-1 px-4">

                <a href="#" class="nav-item active flex items-center px-4 py-3">
                    <span class="text-xl mr-4">🏠</span>
                    <span class="font-medium">Dashboard</span>
                </a>

                <a href="employees/list.php" class="nav-item flex items-center px-4 py-3">
                    <span class="text-xl mr-4">👥</span>
                    <span class="font-medium">Employees</span>
                </a>

                <a href="#" class="nav-item flex items-center px-4 py-3">
                    <span class="text-xl mr-4">📆</span>
                    <span class="font-medium">Attendance</span>
                </a>

                <a href="#" class="nav-item flex items-center px-4 py-3">
                    <span class="text-xl mr-4">📝</span>
                    <span class="font-medium">Leave Requests</span>
                </a>

                <a href="#" class="nav-item flex items-center px-4 py-3">
                    <span class="text-xl mr-4">🧾</span>
                    <span class="font-medium">Payroll</span>
                </a>

                <a href="#" class="nav-item flex items-center px-4 py-3">
                    <span class="text-xl mr-4">📈</span>
                    <span class="font-medium">Performance</span>
                </a>

                <a href="#" class="nav-item flex items-center px-4 py-3">
                    <span class="text-xl mr-4">💼</span>
                    <span class="font-medium">Recruitment</span>
                </a>

            </nav>

            <div class="absolute bottom-0 w-72 p-6 border-t border-blue-700">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-sm font-semibold">
                        HM
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-sm">HR Manager</p>
                        <p class="text-xs text-blue-200">admin@company.com</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 ml-0">

            <!-- Top Navbar -->
            <header class="bg-white shadow-sm border-b border-gray-200 px-8 py-5 flex justify-between items-center sticky top-0 z-10">
                <div>
                    <h2 class="text-3xl font-bold text-gray-800">Dashboard</h2>
                    <p class="text-sm text-gray-500 mt-1">Welcome back! Here's what's happening today.</p>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="relative">
                        <button class="relative p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition">
                            <span class="text-xl">🔔</span>
                            <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                        </button>
                    </div>
                    <div class="flex items-center space-x-3 pl-4 border-l border-gray-200">
                        <div class="text-right">
                            <p class="font-semibold text-gray-800 text-sm">HR Manager</p>
                            <p class="text-xs text-gray-500">Administrator</p>
                        </div>
                        <img src="https://i.pravatar.cc/40?img=12" class="w-11 h-11 rounded-full border-2 border-gray-200 shadow-sm">
                    </div>
                </div>
            </header>

            <!-- HR Statistics Cards -->
            <section class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 fade-in">

                    <div class="stat-card p-6 rounded-xl shadow-sm card-hover">
                        <div class="flex items-center justify-between mb-4">
                            <div class="stat-icon bg-blue-50 text-blue-600">
                                👥
                            </div>
                            <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full">+12%</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">Total Employees</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1">42</p>
                        <p class="text-xs text-gray-500">Active workforce</p>
                    </div>

                    <div class="stat-card p-6 rounded-xl shadow-sm card-hover">
                        <div class="flex items-center justify-between mb-4">
                            <div class="stat-icon bg-green-50 text-green-600">
                                📆
                            </div>
                            <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full">85.7%</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">Today's Attendance</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1">36</p>
                        <p class="text-xs text-gray-500">Present today</p>
                    </div>

                    <div class="stat-card p-6 rounded-xl shadow-sm card-hover">
                        <div class="flex items-center justify-between mb-4">
                            <div class="stat-icon bg-yellow-50 text-yellow-600">
                                🏝️
                            </div>
                            <span class="text-xs font-semibold text-yellow-600 bg-yellow-50 px-2 py-1 rounded-full">Pending</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">Leave Requests</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1">4</p>
                        <p class="text-xs text-gray-500">Awaiting approval</p>
                    </div>

                    <div class="stat-card p-6 rounded-xl shadow-sm card-hover">
                        <div class="flex items-center justify-between mb-4">
                            <div class="stat-icon bg-purple-50 text-purple-600">
                                💼
                            </div>
                            <span class="text-xs font-semibold text-purple-600 bg-purple-50 px-2 py-1 rounded-full">Active</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">Open Positions</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1">3</p>
                        <p class="text-xs text-gray-500">Recruiting now</p>
                    </div>

                </div>
            </section>

            <!-- HR Quick Actions -->
            <section class="px-8 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-800">Quick Actions</h3>
                    <a href="#" class="text-sm text-blue-600 font-semibold hover:text-blue-700">View All →</a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 card-hover">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                            <span class="text-2xl">➕</span>
                        </div>
                        <h4 class="text-lg font-bold mb-2 text-gray-800">Add New Employee</h4>
                        <p class="text-gray-600 text-sm mb-4 leading-relaxed">Register a new employee into the system with complete profile information.</p>
                        <a href="employees/add.php" class="inline-flex items-center text-blue-600 font-semibold text-sm hover:text-blue-700 transition">
                            Add Employee
                            <span class="ml-2">→</span>
                        </a>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 card-hover">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                            <span class="text-2xl">🧾</span>
                        </div>
                        <h4 class="text-lg font-bold mb-2 text-gray-800">Process Payroll</h4>
                        <p class="text-gray-600 text-sm mb-4 leading-relaxed">Generate monthly salary statements and process deductions for all employees.</p>
                        <a href="#" class="inline-flex items-center text-purple-600 font-semibold text-sm hover:text-purple-700 transition">
                            Process Now
                            <span class="ml-2">→</span>
                        </a>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 card-hover">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-4">
                            <span class="text-2xl">📑</span>
                        </div>
                        <h4 class="text-lg font-bold mb-2 text-gray-800">Review Leave Requests</h4>
                        <p class="text-gray-600 text-sm mb-4 leading-relaxed">Approve or reject pending leave applications from your team members.</p>
                        <a href="#" class="inline-flex items-center text-yellow-600 font-semibold text-sm hover:text-yellow-700 transition">
                            Review Now
                            <span class="ml-2">→</span>
                        </a>
                    </div>

                </div>
            </section>

            <!-- Recent Activities -->
            <section class="px-8 pb-10">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-800">Recent Activity</h3>
                    <a href="#" class="text-sm text-blue-600 font-semibold hover:text-blue-700">View All →</a>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6">
                        <ul class="space-y-4">

                            <li class="flex items-center justify-between py-4 border-b border-gray-100 last:border-0 group hover:bg-gray-50 -mx-6 px-6 transition rounded-lg">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <span class="text-lg">👤</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">
                                            Employee <strong class="font-semibold">John Mathew</strong> joined the company
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">New employee registration completed</p>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-500 font-medium whitespace-nowrap ml-4">1 hour ago</span>
                            </li>

                            <li class="flex items-center justify-between py-4 border-b border-gray-100 last:border-0 group hover:bg-gray-50 -mx-6 px-6 transition rounded-lg">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                        <span class="text-lg">📑</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">
                                            Leave request from <strong class="font-semibold">Ana George</strong> pending approval
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">Sick leave - 2 days requested</p>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-500 font-medium whitespace-nowrap ml-4">4 hours ago</span>
                            </li>

                            <li class="flex items-center justify-between py-4 border-b border-gray-100 last:border-0 group hover:bg-gray-50 -mx-6 px-6 transition rounded-lg">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                        <span class="text-lg">💼</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">
                                            New job posting for <strong class="font-semibold">UI/UX Designer</strong> created
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">Recruitment campaign started</p>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-500 font-medium whitespace-nowrap ml-4">Yesterday</span>
                            </li>

                            <li class="flex items-center justify-between py-4 border-b border-gray-100 last:border-0 group hover:bg-gray-50 -mx-6 px-6 transition rounded-lg">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                        <span class="text-lg">📊</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">
                                            Attendance updated for <strong class="font-semibold">42 employees</strong>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">Daily attendance log synchronized</p>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-500 font-medium whitespace-nowrap ml-4">Yesterday</span>
                            </li>

                        </ul>
                    </div>
                </div>
            </section>

        </main>
    </div>

</body>

</html>