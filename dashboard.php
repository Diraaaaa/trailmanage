
<?php
include("connect.php");

$db = $conn ?? $koneksi ?? $connect ?? null;

if (!$db instanceof mysqli) {
    die("Koneksi database tidak ditemukan. Periksa connect.php.");
}

function totalData($db, $table) {
    $result = $db->query("SELECT COUNT(*) AS total FROM `$table`");
    return $result ? (int)$result->fetch_assoc()['total'] : 0;
}

$totalKereta = totalData($db, 'kereta');
$totalPenumpang = totalData($db, 'penumpang');
$totalReservasi = totalData($db, 'reservasi');
$totalTransaksi = totalData($db, 'transaksi');

$resultPendapatan = $db->query(
    "SELECT COALESCE(SUM(total_bayar), 0) AS total FROM transaksi"
);
$totalPendapatan = (float)$resultPendapatan->fetch_assoc()['total'];

$resultMetode = $db->query("
    SELECT metode_bayar, COUNT(*) AS total
    FROM transaksi
    GROUP BY metode_bayar
");

$cash = 0;
$cashless = 0;

while ($row = $resultMetode->fetch_assoc()) {
    if ($row['metode_bayar'] == 'Cash') {
        $cash = (int)$row['total'];
    } else {
        $cashless = (int)$row['total'];
    }
}

$resultTransaksi = $db->query("
    SELECT
        t.id_transaksi,
        t.metode_bayar,
        t.tgl_bayar,
        t.jml_tiket,
        t.total_bayar,
        r.id_reservasi,
        p.nama_penumpang,
        k.nm_kereta
    FROM transaksi t
    LEFT JOIN reservasi r
        ON t.id_reservasi = r.id_reservasi
    LEFT JOIN penumpang p
        ON r.id_penumpang = p.id_penumpang
    LEFT JOIN kereta k
        ON r.id_kereta = k.id_kereta
    ORDER BY t.tgl_bayar DESC
    LIMIT 8
");

function rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function e($value) {
    return htmlspecialchars((string)($value ?? '-'), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="topbar.css" />
    <style>
        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        .main {
            flex: 1;
            min-width: 0;
            padding: 30px 35px;
        }

        .section-title {
            font-size: 17px;
            margin: 0 0 18px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: white;
            padding: 22px;
            border-radius: 13px;
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .stat-icon {
            width: 43px;
            height: 43px;
            border-radius: 10px;
            background: #EAF1F1;
            color: var(--primary);
            display: grid;
            place-items: center;
            font-size: 21px;
        }

        .stat-card small {
            color: var(--muted);
            font-size: 12px;
        }

        .stat-card h3 {
            margin: 8px 0 0;
            font-size: 25px;
        }

        /* CONTENT PANELS */
        .content-grid {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .panel {
            background: white;
            padding: 23px;
            border-radius: 13px;
        }

        .panel h3 {
            margin: 0 0 22px;
            font-size: 16px;
        }

        .payment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .payment-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .payment-icon {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            background: #EAF1F1;
            color: var(--primary);
            border-radius: 9px;
        }

        .payment-info strong {
            display: block;
            font-size: 13px;
        }

        .payment-info small {
            color: var(--muted);
            font-size: 11px;
        }

        .payment-count {
            font-weight: bold;
            font-size: 15px;
        }

        .payment-note {
            font-size: 11px;
            color: var(--muted);
            margin-top: 15px;
        }

        .table-panel {
            background: white;
            border-radius: 13px;
            padding: 23px;
            overflow: hidden;
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .table-header h3 {
            margin: 0;
            font-size: 16px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        th {
            background: #F6F8FA;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            text-align: left;
            padding: 14px;
        }

        td {
            padding: 15px 14px;
            border-bottom: 1px solid var(--border);
            font-size: 12px;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #EAF1F1;
            color: var(--primary);
            font-size: 11px;
            font-weight: bold;
        }

        .empty {
            text-align: center;
            color: var(--muted);
            padding: 30px;
        }

        @media(max-width: 1100px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width: 760px) {
            .main {
                padding: 22px 15px;
            }

            .stats {
                gap: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card h3 {
                font-size: 19px;
            }

            .panel,
            .table-panel {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<div class="dashboard">

    <aside class="sidebar">

        <div class="brand">
            <img src="image/logo ka.png" alt="Logo">
            <div>
                <h2>RailManage</h2>
                <small>Railway Management</small>
            </div>
        </div>

        <div class="menu-title">MAIN MENU</div>

        <nav class="menu">
            <a href="dashboard.php" class="active">
                <span class="menu-icon">📌</span>
                <span>Dashboard</span>
            </a>

            <a href="kereta.php">
                <span class="menu-icon">🚉</span>
                <span>Data Kereta</span>
            </a>

            <a href="penumpang.php">
                <span class="menu-icon">👤</span>
                <span>Data Penumpang</span>
            </a>

            <a href="reservasi.php">
                <span class="menu-icon">📒</span>
                <span>Reservasi</span>
            </a>

            <a href="transaksi.php">
                <span class="menu-icon">💵</span>
                <span>Transaksi</span>
            </a>
        </nav>

    </aside>

    <!-- MAIN -->
    <main class="main">

        <header class="topbar">
            <div>
                <h2>Dashboard</h2>
                <p>Selamat datang di sistem manajemen RailManage</p>
            </div>

            <div class="admin-info">
                <div>
                    <a href="logout.php" class="logout-btn" onclick="return confirm('Apakah Anda yakin ingin logout?')">Logout</a>
                </div>
            </div>
        </header>

        <h3 class="section-title">Ringkasan Data</h3>

        <section class="stats">

            <div class="stat-card">
                <div class="stat-top">
                    <small>Total Kereta</small>
                    <div class="stat-icon">🚉</div>
                </div>
                <h3><?= $totalKereta ?></h3>
                <small>Kereta terdaftar</small>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <small>Total Penumpang</small>
                    <div class="stat-icon">👤</div>
                </div>
                <h3><?= $totalPenumpang ?></h3>
                <small>Penumpang terdaftar</small>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <small>Total Reservasi</small>
                    <div class="stat-icon">📒</div>
                </div>
                <h3><?= $totalReservasi ?></h3>
                <small>Reservasi tercatat</small>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <small>Total Pendapatan</small>
                    <div class="stat-icon">💵</div>
                </div>
                <h3 style="font-size:21px">
                    <?= rupiah($totalPendapatan) ?>
                </h3>
                <small>transaksi tercatat</small>
            </div>

        </section>

        <section class="content-grid">

            <div class="panel">
                <h3>Informasi Pembayaran</h3>

                <div class="payment-item">
                    <div class="payment-info">
                        <div class="payment-icon">💳</div>
                        <div>
                            <strong>Cashless</strong>
                            <small>Pembayaran non-tunai</small>
                        </div>
                    </div>
                    <div class="payment-count"><?= $cashless ?></div>
                </div>

                <div class="payment-item">
                    <div class="payment-info">
                        <div class="payment-icon">💵</div>
                        <div>
                            <strong>Cash</strong>
                            <small>Pembayaran tunai</small>
                        </div>
                    </div>
                    <div class="payment-count"><?= $cash ?></div>
                </div>

                <p class="payment-note">
                    Jumlah transaksi berdasarkan metode pembayaran.
                </p>
            </div>

            <div class="panel">
                <h3>Ringkasan Transaksi</h3>

                <div class="payment-item">
                    <div class="payment-info">
                        <div class="payment-icon">🤝</div>
                        <div>
                            <strong>Total Transaksi</strong>
                            <small>Seluruh transaksi tercatat</small>
                        </div>
                    </div>
                    <div class="payment-count"><?= $totalTransaksi ?></div>
                </div>

                <div class="payment-item">
                    <div class="payment-info">
                        <div class="payment-icon">🎫</div>
                        <div>
                            <strong>Total Reservasi</strong>
                            <small>Seluruh pemesanan tiket</small>
                        </div>
                    </div>
                    <div class="payment-count"><?= $totalReservasi ?></div>
                </div>
            </div>

        </section>

        <section class="table-panel">

            <div class="table-header">
                <h3>Transaksi Terbaru</h3>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID Transaksi</th>
                            <th>Nama Penumpang</th>
                            <th>Kereta</th>
                            <th>Jumlah Tiket</th>
                            <th>Metode Bayar</th>
                            <th>Tanggal Bayar</th>
                            <th>Total Bayar</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($resultTransaksi && $resultTransaksi->num_rows > 0): ?>

                        <?php while ($row = $resultTransaksi->fetch_assoc()): ?>

                            <tr>
                                <td><strong><?= e($row['id_transaksi']) ?></strong></td>
                                <td><?= e($row['nama_penumpang']) ?></td>
                                <td><?= e($row['nm_kereta']) ?></td>
                                <td><?= e($row['jml_tiket']) ?></td>

                                <td>
                                    <span class="badge">
                                        <?= e($row['metode_bayar']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= $row['tgl_bayar']
                                        ? date('d/m/Y H:i', strtotime($row['tgl_bayar']))
                                        : '-' ?>
                                </td>

                                <td>
                                    <strong><?= rupiah($row['total_bayar'] ?? 0) ?></strong>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="empty">
                                Belum ada data transaksi.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </section>

    </main>

</div>

</body>
</html>
