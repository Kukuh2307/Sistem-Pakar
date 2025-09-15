  <?php
  session_start();
  require_once __DIR__ . '/../../../config.php';
  requireAdmin();

  try {
    // Ambil total siswa
    $user = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_siswa = $user - 1; // Kurangi 1 untuk admin
        
    // Ambil total laporan
    $total_laporan = $pdo->query("SELECT COUNT(*) FROM history_diagnosa")->fetchColumn();

      // Konfigurasi pagination
      $limit = 5;
      $hal = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
      $hal = max($hal, 1);
      $offset = ($hal - 1) * $limit;

      // Hitung total data
      $countQuery = "SELECT COUNT(*) as total FROM history_diagnosa";
      $countStmt = $pdo->prepare($countQuery);
      $countStmt->execute();
      $totalData = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
      $totalPages = max(1, ceil($totalData / $limit));

      // Query data
      $query = "
        SELECT 
          hd.id,
          u.username,
          hd.nama_lengkap,
          hd.jenis_kelamin,
          hd.tanggal_lahir,
          hd.alamat,
          hd.tanggal,
          hd.gejala_terpilih,
          hd.hasil_diagnosa,
          hd.pdf_filename,
          hd.user_id
        FROM history_diagnosa hd
        JOIN users u ON hd.user_id = u.id
        ORDER BY hd.tanggal DESC
        LIMIT $limit OFFSET $offset
      ";
      $stmt = $pdo->prepare($query);
      $stmt->execute();
      $laporan_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

  } catch (PDOException $e) {
      error_log("Database error: " . $e->getMessage());
      $laporan_data = [];
      $totalData = 0;
      $totalPages = 1;
  }

  function hitungJumlahGejala($gejala_json) {
      $arr = json_decode($gejala_json, true);
      return is_array($arr) ? count($arr) : 0;
  }

  function getHasilDiagnosaUtama($hasil_json) {
      $arr = json_decode($hasil_json, true);
      return (is_array($arr) && !empty($arr)) ? ($arr[0]['penyakit'] ?? 'Tidak diketahui') : 'Tidak diketahui';
  }

  function formatTanggal($tanggal) { 
      return date('d/m/Y H:i', strtotime($tanggal)); 
  }

  // Base URL untuk pagination (tetap di menu laporan)
  $baseUrl = "container.php?page=dashboard";
  ?>
  <!DOCTYPE html>
  <html lang="id">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Diagnosa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
      .fade-in {
          animation: fadeIn 0.5s ease-in;
      }
      @keyframes fadeIn {
          from { opacity: 0; transform: translateY(20px); }
          to { opacity: 1; transform: translateY(0); }
      }
      
      /* Custom styles for better mobile experience */
      @media (max-width: 768px) {
          .mobile-card {
              border: 1px solid #e2e8f0;
              border-radius: 0.5rem;
              margin-bottom: 1rem;
              padding: 1rem;
              background: white;
              box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
          }
          
          .mobile-card .card-row {
              display: flex;
              justify-content: space-between;
              margin-bottom: 0.5rem;
              padding-bottom: 0.5rem;
              border-bottom: 1px solid #f1f5f9;
          }
          
          .mobile-card .card-label {
              font-weight: 600;
              color: #4a5568;
              min-width: 120px;
          }
          
          .mobile-card .card-value {
              text-align: right;
              flex-grow: 1;
          }
          
          .action-buttons {
              display: flex;
              gap: 0.5rem;
              margin-top: 1rem;
              flex-wrap: wrap;
          }
      }
    </style>
  </head>
  <body class="bg-gray-100">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 m-6">
            <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
                <div class="flex items-center">
                    <i class="fas fa-users text-3xl text-teal-600 mr-4"></i>
                    <div>
                        <h3 class="text-gray-500 text-sm">Total Siswa Terdaftar</h3>
                        <p class="text-2xl font-bold text-teal-600" id="total-siswa"><?php echo $total_siswa; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
                <div class="flex items-center">
                    <i class="fas fa-file-medical text-3xl text-teal-600 mr-4"></i>
                    <div>
                        <h3 class="text-gray-500 text-sm">Total Laporan Diagnosa</h3>
                        <p class="text-2xl font-bold text-teal-600" id="total-laporan"><?php echo $total_laporan; ?></p>
                    </div>
                </div>
            </div>
        </div>

  <div class="bg-white rounded-xl shadow mx-3 mt-2 mb-20">
    <div class="p-4 border-b bg-gradient-to-r from-teal-50 to-teal-100 flex flex-col md:flex-row md:items-center md:justify-between">
      <h2 class="text-lg font-semibold text-teal-700"><i class="fas fa-clipboard-list mr-2"></i>Daftar Laporan Diagnosa Siswa</h2>
      <div class="flex items-center space-x-4 mt-3 md:mt-0">
        <div class="relative">
          <input id="searchInput" placeholder="Cari nama siswa atau diagnosa..."
            class="px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 w-full md:w-64">
          <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
        </div>
        <button id="refreshBtn" onclick="location.reload()"
          class="px-4 py-2 bg-[#065084] text-white rounded-lg hover:bg-teal-700 flex items-center mt-2 md:mt-0 w-full md:w-auto justify-center">
          <i class="fas fa-sync-alt mr-2"></i>Refresh
        </button>
      </div>
    </div>

    <!-- Desktop Table (hidden on mobile) -->
    <div class="overflow-x-auto hidden md:block">
      <table class="min-w-full text-left">
        <thead class="bg-teal-600 text-white">
          <tr>
            <th class="py-3 px-4">No</th>
            <th class="py-3 px-4">Nama Siswa</th>
            <th class="py-3 px-4">Tanggal</th>
            <th class="py-3 px-4">Jumlah Gejala</th>
            <th class="py-3 px-4">Hasil Diagnosa</th>
            <th class="py-3 px-4">Aksi</th>
          </tr>
        </thead>
        <tbody id="tableBody" class="divide-y divide-gray-200">
        <?php if (empty($laporan_data)): ?>
          <tr><td colspan="6" class="py-8 text-center text-gray-500">Belum ada data laporan</td></tr>
        <?php else: foreach ($laporan_data as $i => $row): ?>
          <tr class="data-row">
            <td class="py-3 px-4"><?php echo $offset + $i + 1; ?></td>
            <td class="py-3 px-4 flex items-center">
              <div class="w-8 h-8 bg-teal-100 rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-user text-teal-600 text-xs"></i>
              </div>  
            <span class="nama-siswa"><?php echo htmlspecialchars($row['nama_lengkap']); ?></span></td>
            <td class="py-3 px-4"><?php echo formatTanggal($row['tanggal']); ?></td>
            <td class="py-3 px-4"><?php echo hitungJumlahGejala($row['gejala_terpilih']); ?> Gejala</td>
            <td class="py-3 px-4">
              <?php
              $result = '';
              if (getHasilDiagnosaUtama($row['hasil_diagnosa']) === 'Pemakaian HP Berlebihan Berat') {
                  $result = '<span class="bg-red-100 text-red-800 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">Pemakaian HP Berlebihan Berat</span>';
              } else if(getHasilDiagnosaUtama($row['hasil_diagnosa']) === 'Pemakaian HP Berlebihan Sedang') {
                  $result = '<span class="bg-yellow-100 text-yellow-800 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">Pemakaian HP Berlebihan Sedang</span>';
              } else if(getHasilDiagnosaUtama($row['hasil_diagnosa']) === 'Pemakaian HP Berlebihan Ringan') {
                  $result = '<span class="bg-green-100 text-green-800 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">Pemakaian HP Berlebihan Ringan</span>';
              } else {
                  $result = '<span class="bg-gray-100 text-gray-800 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">' . htmlspecialchars(getHasilDiagnosaUtama($row['hasil_diagnosa'])) . '</span>';
              }
              ?>
            <span class="diagnosa-text"><?php echo $result; ?></span></td>
            <td class="py-3 px-4">
              <!-- Tombol Detail dengan modal seperti di dashboard.php -->
              <button onclick="showDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                      class="px-2 py-1 bg-[#065084] text-white rounded text-sm mb-1 md:mb-0">
                Detail
              </button>
              <?php if ($row['pdf_filename']): ?>
                <!-- Form untuk generate PDF real-time -->
                <form action="<?php echo base_url('report.php') ?>" method="POST" target="_blank" class="inline-block">
                  <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
                  <button type="submit" class="px-2 py-1 bg-teal-600 text-white rounded text-sm mt-1 md:mt-0">
                    PDF
                  </button>
                </form>
              <?php endif; ?>
            </td>  
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile Cards (visible only on mobile) -->
    <div class="md:hidden">
      <?php if (empty($laporan_data)): ?>
        <div class="py-8 text-center text-gray-500">Belum ada data laporan</div>
      <?php else: foreach ($laporan_data as $i => $row): ?>
        <div class="mobile-card data-row">
          <div class="card-row">
            <span class="card-label">No</span>
            <span class="card-value"><?php echo $offset + $i + 1; ?></span>
          </div>
          <div class="card-row">
            <span class="card-label">Nama Siswa</span>
            <span class="card-value nama-siswa"><?php echo htmlspecialchars($row['nama_lengkap']); ?></span>
          </div>
          <div class="card-row">
            <span class="card-label">Tanggal</span>
            <span class="card-value"><?php echo formatTanggal($row['tanggal']); ?></span>
          </div>
          <div class="card-row">
            <span class="card-label">Jumlah Gejala</span>
            <span class="card-value"><?php echo hitungJumlahGejala($row['gejala_terpilih']); ?> Gejala</span>
          </div>
          <div class="card-row">
            <span class="card-label">Hasil Diagnosa</span>
            <span class="card-value diagnosa-text">
              <?php
              if (getHasilDiagnosaUtama($row['hasil_diagnosa']) === 'Pemakaian HP Berlebihan Berat') {
                  echo '<span class="bg-red-100 text-red-800 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">Berat</span>';
              } else if(getHasilDiagnosaUtama($row['hasil_diagnosa']) === 'Pemakaian HP Berlebihan Sedang') {
                  echo '<span class="bg-yellow-100 text-yellow-800 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">Sedang</span>';
              } else if(getHasilDiagnosaUtama($row['hasil_diagnosa']) === 'Pemakaian HP Berlebihan Ringan') {
                  echo '<span class="bg-green-100 text-green-800 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">Ringan</span>';
              } else {
                  echo '<span class="bg-gray-100 text-gray-800 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">' . htmlspecialchars(getHasilDiagnosaUtama($row['hasil_diagnosa'])) . '</span>';
              }
              ?>
            </span>
          </div>
          <div class="action-buttons">
            <button onclick="showDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                    class="px-3 py-2 bg-[#065084] text-white rounded text-sm flex-1">
              <i class="fas fa-info-circle mr-1"></i> Detail
            </button>
            <?php if ($row['pdf_filename']): ?>
              <form action="<?php echo base_url('report.php') ?>" method="POST" target="_blank" class="flex-1">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
                <button type="submit" class="w-full px-3 py-2 bg-teal-600 text-white rounded text-sm">
                  <i class="fas fa-file-pdf mr-1"></i> PDF
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Pagination -->
    <div class="p-4 border-t">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <p class="text-center md:text-left text-sm text-gray-600 mb-2 md:mb-0">
          Menampilkan <?php echo ($offset+1); ?> - <?php echo min($offset+$limit,$totalData); ?> dari <?php echo $totalData; ?> data
        </p>
        
        <div class="flex justify-center space-x-1 flex-wrap">
          <?php if ($hal > 1): ?>
            <a href="<?php echo $baseUrl; ?>&hal=1" class="px-3 py-1 bg-gray-200 rounded text-sm mb-1">&laquo;</a>
            <a href="<?php echo $baseUrl; ?>&hal=<?php echo $hal-1; ?>" class="px-3 py-1 bg-gray-200 rounded text-sm mb-1">Prev</a>
          <?php endif; ?>
          
          <?php 
          // Tampilkan maksimal 5 halaman di mobile
          $start_page = max(1, $hal - 2);
          $end_page = min($totalPages, $start_page + 4);
          if ($end_page - $start_page < 4) {
              $start_page = max(1, $end_page - 4);
          }
          
          for ($i = $start_page; $i <= $end_page; $i++): 
          ?>
            <?php if ($i == $hal): ?>
              <span class="px-3 py-1 bg-teal-600 text-white rounded text-sm mb-1"><?php echo $i; ?></span>
            <?php else: ?>
              <a href="<?php echo $baseUrl; ?>&hal=<?php echo $i; ?>" class="px-3 py-1 bg-gray-200 rounded text-sm mb-1 hover:bg-teal-600 hover:text-white"><?php echo $i; ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          
          <?php if ($hal < $totalPages): ?>
            <a href="<?php echo $baseUrl; ?>&hal=<?php echo $hal+1; ?>" class="px-3 py-1 bg-gray-200 rounded text-sm mb-1">Next</a>
            <a href="<?php echo $baseUrl; ?>&hal=<?php echo $totalPages; ?>" class="px-3 py-1 bg-gray-200 rounded text-sm mb-1">&raquo;</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal untuk detail history (sama seperti di dashboard.php) -->
  <div id="detailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4 py-4">
          <div class="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto">
              <div class="px-6 py-4 border-b border-gray-200 sticky top-0 bg-white">
                  <div class="flex items-center justify-between">
                      <h3 class="text-lg font-medium text-gray-900">Detail Hasil Diagnosa</h3>
                      <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                          </svg>
                      </button>
                  </div>
              </div>
              <div id="modalContent" class="px-6 py-4">
                  
              </div>
          </div>
      </div>
  </div>

  <script>
  // Searching - works for both desktop and mobile
  document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('.data-row');
    let visibleCount = 0;

    rows.forEach(function(row) {
      const nama = row.querySelector('.nama-siswa').textContent.toLowerCase();
      const diagnosa = row.querySelector('.diagnosa-text').textContent.toLowerCase();
      if (nama.includes(searchTerm) || diagnosa.includes(searchTerm)) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });
    
    // Show message if no results
    const noResults = document.getElementById('noResults');
    if (visibleCount === 0 && searchTerm !== '') {
      if (!noResults) {
        const noResultsMsg = document.createElement('div');
        noResultsMsg.id = 'noResults';
        noResultsMsg.className = 'py-8 text-center text-gray-500';
        noResultsMsg.textContent = 'Tidak ada data yang sesuai dengan pencarian';
        document.querySelector('.overflow-x-auto').appendChild(noResultsMsg);
      }
    } else if (noResults) {
      noResults.remove();
    }
  });

  // Function to show detail modal (sama seperti di dashboard.php)
  function showDetails(data) {
      const modal = document.getElementById('detailModal');
      const modalContent = document.getElementById('modalContent');
      
      const gejalaData = JSON.parse(data.gejala_terpilih);
      const hasilData = JSON.parse(data.hasil_diagnosa);
      
      let content = `
          <div class="space-y-6">
              <div>
                  <h4 class="font-medium text-gray-900 mb-3">Informasi Diagnosa</h4>
                  <div class="bg-gray-50 p-4 rounded-lg">
                      <p><strong>Tanggal:</strong> ${new Date(data.tanggal).toLocaleDateString('id-ID', {
                          weekday: 'long',
                          year: 'numeric',
                          month: 'long',
                          day: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit'
                      })}</p>
                      <p><strong>Nama Siswa:</strong> ${data.nama_lengkap}</p>
                      <p><strong>Jumlah Gejala:</strong> ${gejalaData.length} gejala</p>
                  </div>
              </div>
              
              <div>
                  <h4 class="font-medium text-gray-900 mb-3">Gejala yang Dipilih</h4>
                  <div class="bg-blue-50 p-4 rounded-lg">
                      <ol class="list-decimal list-inside space-y-1">`;
      
      gejalaData.forEach(function(gejala, index) {
          content += `<li class="text-sm">${gejala}</li>`;
      });
      
      content += `
                      </ol>
                  </div>
              </div>
              
              <div>
                  <h4 class="font-medium text-gray-900 mb-3">Hasil Diagnosa</h4>
                  <div class="space-y-4">`;
      
      hasilData.forEach(function(hasil, index) {
          const cfColor = hasil.cf >= 70 ? 'text-red-600' : (hasil.cf >= 40 ? 'text-yellow-600' : 'text-green-600');
          const borderColor = index === 0 ? 'border-red-500' : 'border-gray-300';
          
          content += `
              <div class="bg-gray-50 p-4 rounded-lg border-l-4 ${borderColor}">
                  <div class="flex justify-between items-start">
                      <div class="flex-1">
                          <h5 class="font-semibold text-gray-800">${hasil.penyakit}</h5>
                          ${index === 0 ? '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-[#FF4F0F] text-white mt-1">Diagnosa Utama</span>' : ''}
                          <p class="text-sm text-gray-600 mt-2">Gejala cocok: ${hasil.jumlah_gejala_cocok} dari ${gejalaData.length}</p>
                          ${hasil.solusi ? `<div class="mt-3 p-3 bg-white rounded border"><h6 class="font-medium text-sm">Rekomendasi:</h6><p class="text-sm text-gray-600 mt-1">${hasil.solusi}</p></div>` : ''}
                      </div>
                      <div class="text-right ml-4">
                          <div class="text-2xl font-bold ${cfColor}">${hasil.cf}%</div>
                          <div class="text-xs text-gray-500">Kepercayaan</div>
                      </div>
                  </div>
              </div>`;
      });
      
      content += `
                  </div>
              </div>
          </div>`;
      
      modalContent.innerHTML = content;
      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden'; // Prevent background scrolling
  }

  function closeModal() {
      const modal = document.getElementById('detailModal');
      modal.classList.add('hidden');
      document.body.style.overflow = 'auto'; // Re-enable scrolling
  }

  // Close modal when clicking outside
  document.getElementById('detailModal').addEventListener('click', function(e) {
      if (e.target === this) {
          closeModal();
      }
  });

  // Handle escape key to close modal
  document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
          closeModal();
      }
  });
  </script>

  </body>
  </html>