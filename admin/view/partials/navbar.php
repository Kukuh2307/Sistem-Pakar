 <!-- Navbar -->
  <nav class="bg-white text-teal-600 px-4 py-3 flex justify-between items-center shadow-md">
    <div class="flex items-center space-x-3">
      <button id="menuBtn" class="md:hidden focus:outline-none">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>
      <span class="font-bold text-lg">Admin Dashboard</span>
    </div>
    <div>
      <a href="<?php echo base_url('admin/view/logout.php') ?>" class="bg-[#FF4F0F] text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">Logout</a>
    </div>
  </nav>