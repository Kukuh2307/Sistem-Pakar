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
  
  // Query untuk mengambil data laporan dengan JOIN
  $query = "
    SELECT 
      hd.id,
      u.nama_lengkap,
      hd.tanggal,
      hd.gejala_terpilih,
      hd.hasil_diagnosa,
      hd.pdf_filename,
      hd.user_id
    FROM history_diagnosa hd
    JOIN users u ON hd.user_id = u.id
    ORDER BY hd.tanggal DESC
  ";
  
  $stmt = $pdo->prepare($query);
  $stmt->execute();
  $laporan_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
} catch (PDOException $e) {
  error_log("Database error: " . $e->getMessage());
  $laporan_data = [];
}

function hitungJumlahGejala($gejala_json) {
  $gejala_array = json_decode($gejala_json, true);
  return is_array($gejala_array) ? count($gejala_array) : 0;
}

function getHasilDiagnosaUtama($hasil_json) {
  $hasil_array = json_decode($hasil_json, true);
  if (is_array($hasil_array) && !empty($hasil_array)) {
    return $hasil_array[0]['penyakit'] ?? 'Tidak diketahui';
  }
  return 'Tidak diketahui';
}

function formatTanggal($tanggal) {
  return date('d/m/Y H:i', strtotime($tanggal));
}
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
        .table-container {
            max-height: 600px;
            overflow-y: auto;
        }
        .search-highlight {
            background-color: #fef08a;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto">
        <!-- Header Cards -->
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

        <!-- Tabel Data Laporan -->
        <div class="bg-white rounded-xl shadow overflow-hidden m-6 h-[500px]">
            <div class="p-4 border-b bg-gradient-to-r from-teal-50 to-teal-100">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <h2 class="text-lg font-semibold text-teal-700 mb-4 md:mb-0">
                        <i class="fas fa-clipboard-list mr-2"></i>
                        Daftar Laporan Diagnosa Siswa
                    </h2>
                    <div class="flex items-center space-x-4">
                        <!-- Search Box -->
                        <div class="relative">
                            <input 
                                type="text" 
                                id="searchInput" 
                                placeholder="Cari nama siswa atau diagnosa..."
                                class="px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent w-64"
                            >
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                        <!-- Refresh Button -->
                        <button 
                            id="refreshBtn"
                            class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition flex items-center"
                            onclick="refreshData()"
                        >
                            <i class="fas fa-sync-alt mr-2"></i>
                            Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading Indicator -->
            <div id="loadingIndicator" class="hidden p-4 text-center">
                <i class="fas fa-spinner fa-spin text-teal-600 text-2xl"></i>
                <p class="text-gray-600 mt-2">Memuat data...</p>
            </div>

            <div class="table-container">
                <table class="min-w-full text-left border-collapse" id="dataTable">
                    <thead class="bg-teal-600 text-white sticky top-0 z-10">
                        <tr>
                            <th class="py-3 px-4 font-semibold">No</th>
                            <th class="py-3 px-4 font-semibold">
                                <i class="fas fa-user mr-1"></i>Nama Siswa
                            </th>
                            <th class="py-3 px-4 font-semibold">
                                <i class="fas fa-calendar mr-1"></i>Tanggal
                            </th>
                            <th class="py-3 px-4 font-semibold">
                                <i class="fas fa-list mr-1"></i>Jumlah Gejala
                            </th>
                            <th class="py-3 px-4 font-semibold">
                                <i class="fas fa-stethoscope mr-1"></i>Hasil Diagnosa
                            </th>
                            <th class="py-3 px-4 font-semibold">
                                <i class="fas fa-cogs mr-1"></i>Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="divide-y divide-gray-200">
                        <?php if (empty($laporan_data)): ?>
                            <tr>
                                <td colspan="6" class="py-8 px-4 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-2"></i>
                                    <p>Belum ada data laporan diagnosa</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($laporan_data as $index => $row): ?>
                                <tr class="hover:bg-gray-50 transition data-row">
                                    <td class="py-3 px-4 text-sm"><?php echo $index + 1; ?></td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-teal-100 rounded-full flex items-center justify-center mr-3">
                                                <i class="fas fa-user text-teal-600 text-xs"></i>
                                            </div>
                                            <span class="font-medium nama-siswa"><?php echo htmlspecialchars($row['nama_lengkap']); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-gray-600">
                                        <?php echo formatTanggal($row['tanggal']); ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <i class="fas fa-list-ol mr-1"></i>
                                            <?php echo hitungJumlahGejala($row['gejala_terpilih']); ?> Gejala
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php 
                                        $diagnosa = getHasilDiagnosaUtama($row['hasil_diagnosa']);
                                        $badgeClass = 'bg-gray-100 text-gray-800';
                                        if (strpos($diagnosa, 'Ringan') !== false) {
                                            $badgeClass = 'bg-green-100 text-green-800';
                                        } elseif (strpos($diagnosa, 'Sedang') !== false) {
                                            $badgeClass = 'bg-yellow-100 text-yellow-800';
                                        } elseif (strpos($diagnosa, 'Berat') !== false) {
                                            $badgeClass = 'bg-red-100 text-red-800';
                                        }
                                        ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $badgeClass; ?> diagnosa-text">
                                            <?php echo htmlspecialchars($diagnosa); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex space-x-2">
                                            <button 
                                                onclick="lihatDetail(<?php echo $row['id']; ?>)"
                                                class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 transition flex items-center"
                                                title="Lihat Detail"
                                            >
                                                <i class="fas fa-eye mr-1"></i>
                                                Detail
                                            </button>
                                            <?php if (!empty($row['pdf_filename'])): ?>
                                                <button 
                                                    onclick="downloadPDF('<?php echo htmlspecialchars($row['pdf_filename']); ?>')"
                                                    class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 transition flex items-center"
                                                    title="Download PDF"
                                                >
                                                    <i class="fas fa-download mr-1"></i>
                                                    PDF
                                                </button>
                                            <?php else: ?>
                                                <button 
                                                    class="px-3 py-1 text-xs bg-gray-400 text-white rounded cursor-not-allowed flex items-center"
                                                    disabled
                                                    title="PDF tidak tersedia"
                                                >
                                                    <i class="fas fa-ban mr-1"></i>
                                                    PDF
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- No Results Message -->
            <div id="noResults" class="hidden p-8 text-center text-gray-500">
                <i class="fas fa-search text-4xl mb-2"></i>
                <p>Tidak ada data yang sesuai dengan pencarian</p>
            </div>
        </div>
    </div>

    <!-- Modal Detail -->
    <div id="detailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        <i class="fas fa-file-medical-alt mr-2 text-teal-600"></i>
                        Detail Diagnosa
                    </h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="modalContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.data-row');
            const noResults = document.getElementById('noResults');
            let visibleCount = 0;

            rows.forEach(function(row) {
                const nama = row.querySelector('.nama-siswa').textContent.toLowerCase();
                const diagnosa = row.querySelector('.diagnosa-text').textContent.toLowerCase();
                
                // Remove previous highlights
                row.querySelectorAll('.search-highlight').forEach(function(el) {
                    el.outerHTML = el.innerHTML;
                });

                if (nama.includes(searchTerm) || diagnosa.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                    
                    // Highlight search terms
                    if (searchTerm) {
                        highlightText(row.querySelector('.nama-siswa'), searchTerm);
                        highlightText(row.querySelector('.diagnosa-text'), searchTerm);
                    }
                } else {
                    row.style.display = 'none';
                }
            });

            // Show/hide no results message
            if (visibleCount === 0 && searchTerm) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }

            // Update row numbers
            updateRowNumbers();
        });

        function highlightText(element, searchTerm) {
            const text = element.textContent;
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            element.innerHTML = text.replace(regex, '<span class="search-highlight">$1</span>');
        }

        function updateRowNumbers() {
            const visibleRows = document.querySelectorAll('.data-row:not([style*="display: none"])');
            visibleRows.forEach(function(row, index) {
                row.querySelector('td:first-child').textContent = index + 1;
            });
        }

        // Refresh data functionality
        function refreshData() {
            const refreshBtn = document.getElementById('refreshBtn');
            const loadingIndicator = document.getElementById('loadingIndicator');
            
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Memuat...';
            loadingIndicator.classList.remove('hidden');
            
            // Simulate data refresh (replace with actual AJAX call)
            setTimeout(function() {
                location.reload(); // Simple reload for now
            }, 1000);
        }

        // Detail modal functions
        function lihatDetail(id) {
            // Show modal
            document.getElementById('detailModal').classList.remove('hidden');
            
            // Load detail data via AJAX (simplified version)
            fetch(`get_detail.php?id=${id}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('modalContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('modalContent').innerHTML = 
                        '<div class="text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i>Error loading data</div>';
                });
        }

        function closeModal() {
            document.getElementById('detailModal').classList.add('hidden');
        }

        function downloadPDF(filename) {
            // Redirect to PDF download
            window.open(`downloads/${filename}`, '_blank');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('detailModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Auto refresh every 30 seconds
        setInterval(function() {
            // Update counters via AJAX without full page reload
            fetch('get_counters.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('total-siswa').textContent = data.total_siswa;
                    document.getElementById('total-laporan').textContent = data.total_laporan;
                });
        }, 30000);
    </script>
</body>
</html>