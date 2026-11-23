
<?php
session_start();
include("connect.php");

$db = $conn ?? $koneksi ?? $connect ?? null;

if (!$db instanceof mysqli) {
    die("Koneksi database tidak ditemukan.");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (empty($_SESSION['csrf_kereta'])) {
    $_SESSION['csrf_kereta'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

$message = '';
$error = '';
$editData = null;

// PROSES CRUD

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(
            $_SESSION['csrf_kereta'],
            $_POST['csrf_token'] ?? ''
        )) {
            throw new Exception("Permintaan tidak valid.");
        }

        $action = $_POST['action'] ?? '';

        // TAMBAH DAN EDIT
        if ($action === 'create' || $action === 'update') {

            $id = trim($_POST['id_kereta'] ?? '');
            $nama = trim($_POST['nm_kereta'] ?? '');
            $kelas = trim($_POST['kelas_kereta'] ?? '');
            $gerbong = filter_var($_POST['gerbong'] ?? null, FILTER_VALIDATE_INT);
            $kursi = filter_var($_POST['no_kursi'] ?? null, FILTER_VALIDATE_INT);
            $jam = $_POST['jam_berangkat'] ?? '';
            $harga = filter_var($_POST['harga_tiket'] ?? null, FILTER_VALIDATE_INT);
            $asal = trim($_POST['stasiun_asal'] ?? '');
            $tujuan = trim($_POST['stasiun_tujuan'] ?? '');

            if (
                $id === '' || strlen($id) > 10 ||
                $nama === '' || strlen($nama) > 30 ||
                $kelas === '' || strlen($kelas) > 25 ||
                $gerbong === false || $gerbong < 1 ||
                $kursi === false || $kursi < 1 ||
                $harga === false || $harga < 0 ||
                $jam === '' ||
                $asal === '' || strlen($asal) > 30 ||
                $tujuan === '' || strlen($tujuan) > 30
            ) {
                throw new Exception("Mohon lengkapi seluruh data dengan benar.");
            }

            $date = DateTime::createFromFormat('H:i', $jam);

            if (!$date || $date->format('H:i') !== $jam) {
                throw new Exception("Format jam keberangkatan tidak valid.");
            }

            if ($asal === $tujuan) {
                throw new Exception("Stasiun asal dan tujuan tidak boleh sama.");
            }

            if ($action === 'create') {

                $stmt = $db->prepare("
                    INSERT INTO kereta
                    (id_kereta, nm_kereta, kelas_kereta,
                    gerbong, no_kursi, jam_berangkat,
                    harga_tiket, stasiun_asal, stasiun_tujuan)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "sssiisiss",
                    $id, $nama, $kelas,
                    $gerbong, $kursi, $jam,
                    $harga, $asal, $tujuan
                );

                $stmt->execute();

                header("Location: kereta.php?status=added");
                exit;

            } else {

                $stmt = $db->prepare("
                    UPDATE kereta SET
                        nm_kereta = ?,
                        kelas_kereta = ?,
                        gerbong = ?,
                        no_kursi = ?,
                        jam_berangkat = ?,
                        harga_tiket = ?,
                        stasiun_asal = ?,
                        stasiun_tujuan = ?
                    WHERE id_kereta = ?
                ");

                $stmt->bind_param(
                    "ssiisisss",
                    $nama, $kelas, $gerbong,
                    $kursi, $jam, $harga,
                    $asal, $tujuan, $id
                );

                $stmt->execute();

                header("Location: kereta.php?status=updated");
                exit;
            }
        }

        // HAPUS DATA
        elseif ($action === 'delete') {

            $id = trim($_POST['id_kereta'] ?? '');

            if ($id === '') {
                throw new Exception("ID kereta tidak valid.");
            }

            $stmt = $db->prepare("
                DELETE FROM kereta WHERE id_kereta = ?
            ");

            $stmt->bind_param("s", $id);
            $stmt->execute();

            header("Location: kereta.php?status=deleted");
            exit;
        }

    } catch (Throwable $ex) {
        $error = $ex->getMessage();

        if ($ex instanceof mysqli_sql_exception && $ex->getCode() == 1451) {
            $error = "Data kereta tidak dapat dihapus karena sudah digunakan pada data reservasi.";
        }
    }
}

// NOTIFIKASI

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'added') {
        $message = "Data kereta berhasil ditambahkan.";
    } elseif ($_GET['status'] === 'updated') {
        $message = "Data kereta berhasil diperbarui.";
    } elseif ($_GET['status'] === 'deleted') {
        $message = "Data kereta berhasil dihapus.";
    }
}

// DATA EDIT

if (isset($_GET['edit'])) {
    $idEdit = trim($_GET['edit']);

    $stmt = $db->prepare("
        SELECT * FROM kereta WHERE id_kereta = ?
    ");

    $stmt->bind_param("s", $idEdit);
    $stmt->execute();

    $editData = $stmt->get_result()->fetch_assoc();
}

// PENCARIAN

$keyword = trim($_GET['search'] ?? '');

if ($keyword !== '') {
    $stmt = $db->prepare("
        SELECT * FROM kereta
        WHERE id_kereta LIKE ?
        OR nm_kereta LIKE ?
        OR kelas_kereta LIKE ?
        OR stasiun_asal LIKE ?
        OR stasiun_tujuan LIKE ?
        ORDER BY id_kereta ASC
    ");

    $like = "%$keyword%";

    $stmt->bind_param(
        "sssss",
        $like, $like, $like, $like, $like
    );

    $stmt->execute();
    $keretaList = $stmt->get_result();

} else {
    $keretaList = $db->query("
        SELECT * FROM kereta
        ORDER BY id_kereta ASC
    ");
}

// RINGKASAN

$summary = $db->query("
    SELECT
        COUNT(*) AS total,
        COUNT(DISTINCT kelas_kereta) AS kelas,
        COALESCE(SUM(gerbong), 0) AS gerbong,
        COALESCE(SUM(no_kursi), 0) AS kursi
    FROM kereta
")->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kereta | RailManage</title>
    <link rel="stylesheet" href="asset/css/style.css" />
    <link rel="stylesheet" href="asset/css/sidebar.css" />
    <link rel="stylesheet" href="asset/css/topbar.css" />

    <style>
        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* MAIN */

        .main {
            flex: 1;
            min-width: 0;
            padding: 30px 35px;
        }

        /* SUMMARY */

        .summary {
            display: grid;
            gap: 17px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
        }

        .summary-card small {
            color: var(--muted);
            font-size: 12px;
        }

        .summary-card h3 {
            font-size: 22px;
            margin: 12px 0 0;
        }

        /* PANEL */

        .panel {
            background: white;
            border-radius: 13px;
            padding: 24px;
            margin-bottom: 23px;
        }

        .panel h3 {
            margin: 0 0 22px;
            font-size: 17px;
        }

        /* FORM */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 17px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field label {
            font-size: 13px;
            font-weight: bold;
            color: #34445F;
        }

        .field input,
        .field select {
            width: 100%;
            padding: 12px;
            border: 1px solid #D9E1ED;
            border-radius: 8px;
            outline: none;
            font-size: 13px;
            font-family: inherit;
            background: white;
        }

        .field input:focus,
        .field select:focus {
            border-color: var(--primary);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        button,
        .btn {
            border: none;
            padding: 11px 17px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: #E9EEF2;
            color: #526174;
        }

        .btn-edit {
            background: #EAF1F1;
            color: var(--primary);
        }

        .btn-delete {
            background: #FCECEC;
            color: #C04A4A;
        }

        /* TABLE */

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .table-header h3 {
            margin: 0;
        }

        .search-form {
            display: flex;
            gap: 8px;
        }

        .search-form input {
            padding: 10px 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            width: 230px;
            outline: none;
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
            text-align: left;
            padding: 14px;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid var(--border);
            font-size: 12px;
        }

        .actions {
            display: flex;
            gap: 7px;
        }

        .actions button,
        .actions a {
            padding: 8px 11px;
            font-size: 11px;
        }

        .alert {
            padding: 13px 16px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .success {
            background: #E8F5ED;
            color: #28734A;
        }

        .danger {
            background: #FCECEC;
            color: #B83D3D;
        }

        .empty {
            text-align: center;
            color: var(--muted);
            padding: 30px;
        }

        @media(max-width: 1100px) {
            .summary {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width: 760px) {

            .main {
                padding: 20px 14px;
            }

            .summary {
                gap: 10px;
            }

            .summary-card {
                padding: 14px;
            }

            .summary-card h3 {
                font-size: 17px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-form,
            .search-form input {
                width: 100%;
            }

            .panel {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<div class="dashboard">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="brand">
            <img src="asset/image/logo ka.png" alt="Logo">
            <div>
                <h2>RailManage</h2>
                <small>Railway Management</small>
            </div>
        </div>

        <div class="menu-title">MAIN MENU</div>

        <nav class="menu">
            <a href="dashboard.php">
                <span class="menu-icon">📌</span>
                <span>Dashboard</span>
            </a>

            <a href="kereta.php" class="active">
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
                <h2>Manajemen Data Kereta</h2>
                <p>Kelola informasi dan jadwal keberangkatan kereta</p>
            </div>

            <div class="admin-info">
                <a href="logout.php" class="logout-btn" onclick="return confirm('Apakah Anda yakin ingin logout?')">Logout</a>
            </div>
        </header>

        <!-- NOTIFIKASI -->

        <?php if ($message): ?>
            <div class="alert success">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert danger">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- SUMMARY -->

        <section class="summary">

            <div class="summary-card">
                <small>Total Kereta</small>
                <h3><?= (int)$summary['total'] ?></h3>
            </div>

        </section>

        <!-- FORM -->

        <section class="panel">

            <h3>
                <?= $editData ? 'Edit Data Kereta' : 'Tambah Data Kereta' ?>
            </h3>

            <form method="POST" action="kereta.php">

                <input type="hidden" name="csrf_token"
                    value="<?= e($_SESSION['csrf_kereta']) ?>">

                <input type="hidden" name="action"
                    value="<?= $editData ? 'update' : 'create' ?>">

                <div class="form-grid">

                    <div class="field">
                        <label>ID Kereta</label>
                        <input
                            type="text"
                            name="id_kereta"
                            maxlength="10"
                            placeholder="Contoh: KA01"
                            value="<?= e($editData['id_kereta'] ?? '') ?>"
                            <?= $editData ? 'readonly' : '' ?>
                            required>
                    </div>

                    <div class="field">
                        <label>Nama Kereta</label>
                        <input
                            type="text"
                            name="nm_kereta"
                            maxlength="30"
                            placeholder="Masukkan nama kereta"
                            value="<?= e($editData['nm_kereta'] ?? '') ?>"
                            required>
                    </div>

                    <div class="field">
                        <label>Kelas Kereta</label>
                        <input
                            type="text"
                            name="kelas_kereta"
                            maxlength="25"
                            placeholder="Contoh: Ekonomi, Bisnis, Eksekutif"
                            value="<?= e($editData['kelas_kereta'] ?? '') ?>"
                            required>
                    </div>

                    <div class="field">
                        <label>Jumlah Gerbong</label>
                        <input
                            type="number"
                            name="gerbong"
                            min="1"
                            placeholder="Masukkan jumlah gerbong"
                            value="<?= e($editData['gerbong'] ?? '') ?>"
                            required>
                    </div>

                    <div class="field">
                        <label>Jumlah Kursi</label>
                        <input
                            type="number"
                            name="no_kursi"
                            min="1"
                            placeholder="Masukkan jumlah kursi"
                            value="<?= e($editData['no_kursi'] ?? '') ?>"
                            required>
                    </div>

                    <div class="field">
                        <label>Jam Keberangkatan</label>
                        <input
                            type="time"
                            name="jam_berangkat"
                            value="<?= e(isset($editData['jam_berangkat'])
                                ? substr($editData['jam_berangkat'], 0, 5)
                                : '') ?>"
                            required>
                    </div>

                    <div class="field">
                        <label>Harga Tiket (Rp)</label>
                        <input
                            type="number"
                            name="harga_tiket"
                            min="0"
                            placeholder="Masukkan harga tiket"
                            value="<?= e($editData['harga_tiket'] ?? '') ?>"
                            required>
                    </div>

                    <div class="field">
                        <label>Stasiun Asal</label>
                        <input
                            type="text"
                            name="stasiun_asal"
                            maxlength="30"
                            placeholder="Masukkan stasiun asal"
                            value="<?= e($editData['stasiun_asal'] ?? '') ?>"
                            required>
                    </div>

                    <div class="field">
                        <label>Stasiun Tujuan</label>
                        <input
                            type="text"
                            name="stasiun_tujuan"
                            maxlength="30"
                            placeholder="Masukkan stasiun tujuan"
                            value="<?= e($editData['stasiun_tujuan'] ?? '') ?>"
                            required>
                    </div>

                </div>

                <div class="form-actions">

                    <button type="submit" class="btn btn-primary">
                        <?= $editData ? 'Simpan Perubahan' : '+ Tambah Kereta' ?>
                    </button>

                    <?php if ($editData): ?>
                        <a href="kereta.php" class="btn btn-secondary">
                            Batal
                        </a>
                    <?php endif; ?>

                </div>

            </form>

        </section>

        <!-- TABEL DATA -->

        <section class="panel">

            <div class="table-header">

                <h3>Daftar Kereta</h3>

                <form method="GET" class="search-form">

                    <input
                        type="text"
                        name="search"
                        placeholder="Cari nama atau ID kereta..."
                        value="<?= e($keyword) ?>">

                    <button type="submit" class="btn btn-primary">
                        Cari
                    </button>

                    <?php if ($keyword !== ''): ?>
                        <a href="kereta.php" class="btn btn-secondary">
                            Reset
                        </a>
                    <?php endif; ?>

                </form>

            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>ID Kereta</th>
                            <th>Nama Kereta</th>
                            <th>Kelas</th>
                            <th>Gerbong</th>
                            <th>Kursi</th>
                            <th>Jam Berangkat</th>
                            <th>Harga Tiket</th>
                            <th>Stasiun Asal</th>
                            <th>Stasiun Tujuan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($keretaList->num_rows > 0): ?>

                        <?php $no = 1; ?>

                        <?php while ($row = $keretaList->fetch_assoc()): ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td>
                                    <strong><?= e($row['id_kereta']) ?></strong>
                                </td>

                                <td><?= e($row['nm_kereta']) ?></td>

                                <td><?= e($row['kelas_kereta']) ?></td>

                                <td><?= e($row['gerbong']) ?></td>

                                <td><?= e($row['no_kursi']) ?></td>

                                <td>
                                    <?= e(substr($row['jam_berangkat'] ?? '', 0, 5)) ?>
                                </td>

                                <td>
                                    <strong><?= rupiah($row['harga_tiket']) ?></strong>
                                </td>

                                <td><?= e($row['stasiun_asal']) ?></td>

                                <td><?= e($row['stasiun_tujuan']) ?></td>

                                <td>
                                    <div class="actions">

                                        <a
                                            href="kereta.php?edit=<?= urlencode($row['id_kereta']) ?>"
                                            class="btn btn-edit">
                                            Edit
                                        </a>

                                        <form method="POST"
                                            action="kereta.php"
                                            onsubmit="return confirm('Yakin ingin menghapus data kereta ini?')">

                                            <input type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_kereta']) ?>">

                                            <input type="hidden"
                                                name="action"
                                                value="delete">

                                            <input type="hidden"
                                                name="id_kereta"
                                                value="<?= e($row['id_kereta']) ?>">

                                            <button type="submit" class="btn btn-delete">
                                                Hapus
                                            </button>

                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="11" class="empty">
                                Data kereta belum tersedia.
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
