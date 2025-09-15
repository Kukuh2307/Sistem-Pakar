<nav class="space-y-2">
    <div class="flex pl-4 items-center hover:bg-teal-100 text-gray-700 <?php echo ($current_page == 'dashboard') ? 'bg-teal-100 font-medium' : ''; ?>">
        <i class="fa-solid fa-house"></i>
        <a href="container.php?page=dashboard" class="block px-3 py-2 rounded-lg">Dashboard</a>
    </div>
        <div class="flex pl-4 items-center hover:bg-teal-100 text-gray-700 <?php echo ($current_page == 'users') ? 'bg-teal-100 font-medium' : ''; ?>">
        <i class="fa-solid fa-user"></i>
        <a href="container.php?page=guru" class="block px-3 py-2 rounded-lg">Guru</a>
    </div>
        <div class="flex pl-4 items-center hover:bg-teal-100 text-gray-700 <?php echo ($current_page == 'diagnosa') ? 'bg-teal-100 font-medium' : ''; ?>">
        <i class="fa-solid fa-stethoscope"></i>
        <a href="container.php?page=diagnosa" class="block px-3 py-2 rounded-lg">Diagnosa</a>
    </div>
    <div class="flex pl-4 items-center hover:bg-teal-100 text-gray-700 <?php echo ($current_page == 'laporan') ? 'bg-teal-100 font-medium' : ''; ?>">
        <i class="fa-solid fa-file-alt"></i>
        <a href="container.php?page=laporan" class="block px-3 py-2 rounded-lg">Laporan</a>
    </div>
</nav>