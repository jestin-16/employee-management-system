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
                <a class="block py-3 px-6 text-gray-700 hover:bg-blue-50 hover:text-blue-600" href="#">Dashboard</a>
                <a class="block py-3 px-6 text-gray-700 hover:bg-blue-50 hover:text-blue-600" href="#">Employees</a>
                <a class="block py-3 px-6 text-gray-700 hover:bg-blue-50 hover:text-blue-600" href="#">Attendance</a>
                <a class="block py-3 px-6 text-gray-700 hover:bg-blue-50 hover:text-blue-600" href="#">Leave Requests</a>
                <a class="block py-3 px-6 text-gray-700 hover:bg-blue-50 hover:text-blue-600" href="#">Departments</a>
                <a class="block py-3 px-6 text-gray-700 hover:bg-blue-50 hover:text-blue-600" href="#">Settings</a>
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
                        <span class="font-medium">HR Manager</span>
                    </div>
                </div>
            </nav>

            <!-- ====== CONTENT AREA ====== -->
            <div class="p-6 overflow-auto">

                <!-- ====== DASHBOARD CARDS ====== -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">

                    <div class="bg-white p-6 rounded-xl shadow">
                        <p class="text-gray-500">Total Employees</p>
                        <h2 class="text-3xl font-semibold mt-2">120</h2>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow">
                        <p class="text-gray-500">Present Today</p>
                        <h2 class="text-3xl font-semibold mt-2">98</h2>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow">
                        <p class="text-gray-500">On Leave</p>
                        <h2 class="text-3xl font-semibold mt-2">12</h2>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow">
                        <p class="text-gray-500">Pending Requests</p>
                        <h2 class="text-3xl font-semibold mt-2">5</h2>
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

                                    <tr class="border-b">
                                        <td class="p-3">EMP101</td>
                                        <td class="p-3">Jestin Shaji</td>
                                        <td class="p-3">Developer</td>
                                        <td class="p-3">IT</td>
                                        <td class="p-3"><span
                                                class="px-3 py-1 rounded-full text-sm bg-green-100 text-green-700">Active</span>
                                        </td>
                                        <td class="p-3 space-x-2">
                                            <button class="text-blue-600">View</button>
                                            <button class="text-yellow-600">Edit</button>
                                            <button class="text-red-600">Delete</button>
                                        </td>
                                    </tr>

                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ====== RECENT ACTIVITIES ====== -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <h2 class="text-xl font-semibold mb-4">Recent Activities</h2>

                        <ul class="space-y-4">
                            <li class="border-b pb-3">
                                <p class="font-medium">New employee added</p>
                                <span class="text-sm text-gray-500">2 hours ago</span>
                            </li>
                            <li class="border-b pb-3">
                                <p class="font-medium">Leave request approved</p>
                                <span class="text-sm text-gray-500">5 hours ago</span>
                            </li>
                            <li>
                                <p class="font-medium">Attendance updated</p>
                                <span class="text-sm text-gray-500">1 day ago</span>
                            </li>
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
