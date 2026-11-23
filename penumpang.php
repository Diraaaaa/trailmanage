
<?php
session_start();
include("connect.php");

$db = $conn ?? $koneksi ?? $connect ?? null;

if (!$db instanceof mysqli) {
    die("Koneksi database tidak ditemukan.");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (empty($_SESSION['csrf_penumpang'])) {
    $_SESSION['csrf_penumpang'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';
$editData = null;

// PROSES CRUD

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (!hash_equals(
            $_SESSION['csrf_penumpang'],
            $_POST['csrf_token'] ?? ''
        )) {
            throw new Exception("Permintaan tidak valid.");
        }

        $action = $_POST['action'] ?? '';

        // TAMBAH DAN EDIT DATA

        if ($action === 'create' || $action === 'update') {

            $id = trim($_POST['id_penumpang'] ?? '');
            $nama = trim($_POST['nama_penumpang'] ?? '');
            $jk = trim($_POST['jenis_kelamin'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $telepon = trim($_POST['no_telp'] ?? '');

            // VALIDASI DATA

            if (
                $id === '' || strlen($id) > 10 ||
                $nama === '' || strlen($nama) > 50 ||
                !in_array($jk, ['Laki-laki', 'Perempuan'], true) ||
                $alamat === '' ||
                strlen($telepon) < 10 ||
                strlen($telepon) > 15 ||
                !preg_match('/^[0-9+\- ]+$/', $telepon)
            ) {
                throw new Exception(
                    "Mohon lengkapi seluruh data dengan benar. Periksa kembali nomor telepon."
                );
            }

            // TAMBAH DATA

            if ($action === 'create') {

                $stmt = $db->prepare("
                    INSERT INTO penumpang
                    (id_penumpang, nama_penumpang, jenis_kelamin, alamat, no_telp)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "sssss",
                    $id,
                    $nama,
                    $jk,
                    $alamat,
                    $telepon
                );

                $stmt->execute();

                header("Location: penumpang.php?status=added");
                exit;

            }

            // EDIT DATA

            else {

                $stmt = $db->prepare("
                    UPDATE penumpang SET
                        nama_penumpang = ?,
                        jenis_kelamin = ?,
                        alamat = ?,
                        no_telp = ?
                    WHERE id_penumpang = ?
                ");

                $stmt->bind_param(
                    "sssss",
                    $nama,
                    $jk,
                    $alamat,
                    $telepon,
                    $id
                );

                $stmt->execute();

                header("Location: penumpang.php?status=updated");
                exit;
            }
        }

        // HAPUS DATA

        elseif ($action === 'delete') {

            $id = trim($_POST['id_penumpang'] ?? '');

            if ($id === '') {
                throw new Exception("ID penumpang tidak valid.");
            }

            $stmt = $db->prepare("
                DELETE FROM penumpang
                WHERE id_penumpang = ?
            ");

            $stmt->bind_param("s", $id);
            $stmt->execute();

            header("Location: penumpang.php?status=deleted");
            exit;
        }

        else {
            throw new Exception("Aksi tidak dikenal.");
        }

    } catch (Throwable $ex) {

        $error = $ex->getMessage();

        if ($ex instanceof mysqli_sql_exception) {

            if ($ex->getCode() == 1062) {
                $error = "ID penumpang sudah terdaftar.";
            }

            elseif ($ex->getCode() == 1451) {
                $error = "Data penumpang tidak dapat dihapus karena sudah digunakan pada data reservasi.";
            }

            else {
                $error = "Terjadi kesalahan pada database: " . $ex->getMessage();
            }
        }
    }
}

// NOTIFIKASI

if (isset($_GET['status'])) {

    if ($_GET['status'] === 'added') {
        $message = "Data penumpang berhasil ditambahkan.";
    }

    elseif ($_GET['status'] === 'updated') {
        $message = "Data penumpang berhasil diperbarui.";
    }

    elseif ($_GET['status'] === 'deleted') {
        $message = "Data penumpang berhasil dihapus.";
    }
}

// DATA EDIT

if (isset($_GET['edit'])) {

    $idEdit = trim($_GET['edit']);

    $stmt = $db->prepare("
        SELECT * FROM penumpang
        WHERE id_penumpang = ?
    ");

    $stmt->bind_param("s", $idEdit);
    $stmt->execute();

    $editData = $stmt->get_result()->fetch_assoc();

    if (!$editData) {
        $error = "Data penumpang tidak ditemukan.";
    }
}

// PENCARIAN

$keyword = trim($_GET['search'] ?? '');

if ($keyword !== '') {

    $stmt = $db->prepare("
        SELECT * FROM penumpang
        WHERE id_penumpang LIKE ?
        OR nama_penumpang LIKE ?
        OR no_telp LIKE ?
        ORDER BY id_penumpang ASC
    ");

    $like = "%$keyword%";

    $stmt->bind_param(
        "sss",
        $like,
        $like,
        $like
    );

    $stmt->execute();

    $penumpangList = $stmt->get_result();

} else {

    $penumpangList = $db->query("
        SELECT * FROM penumpang
        ORDER BY id_penumpang ASC
    ");
}

// RINGKASAN

$summary = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(jenis_kelamin = 'Laki-laki') AS laki,
        SUM(jenis_kelamin = 'Perempuan') AS perempuan
    FROM penumpang
")->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="asset/css/style.css" />
    <link rel="stylesheet" href="asset/css/sidebar.css" />
    <link rel="stylesheet" href="asset/css/topbar.css" />
    <title>Data Penumpang | RailManage</title>

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
            grid-template-columns: repeat(3, 1fr);
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
        .field select,
        .field textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #D9E1ED;
            border-radius: 8px;
            outline: none;
            font-size: 13px;
            font-family: inherit;
            background: white;
        }

        .field textarea {
            resize: vertical;
            min-height: 90px;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
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
                grid-template-columns: repeat(2, 1fr);
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

            <a href="kereta.php">
                <span class="menu-icon">🚉</span>
                <span>Data Kereta</span>
            </a>

            <a href="penumpang.php" class="active">
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
                <h2>Manajemen Data Penumpang</h2>
                <p>Kelola informasi identitas dan kontak penumpang</p>
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
                <small>Total Penumpang</small>
                <h3><?= (int)$summary['total'] ?></h3>
            </div>

            <div class="summary-card">
                <small>Laki-laki</small>
                <h3><?= (int)$summary['laki'] ?></h3>
            </div>

            <div class="summary-card">
                <small>Perempuan</small>
                <h3><?= (int)$summary['perempuan'] ?></h3>
            </div>

        </section>

        <!-- FORM PENUMPANG -->

        <section class="panel">

            <h3>
                <?= $editData ? 'Edit Data Penumpang' : 'Tambah Data Penumpang' ?>
            </h3>

            <form method="POST" action="penumpang.php">

                <input type="hidden" name="csrf_token"
                    value="<?= e($_SESSION['csrf_penumpang']) ?>">

                <input type="hidden" name="action"
                    value="<?= $editData ? 'update' : 'create' ?>">

                <div class="form-grid">

                    <div class="field">

                        <label>ID Penumpang</label>

                        <input
                            type="text"
                            name="id_penumpang"
                            maxlength="10"
                            placeholder="Contoh: PM01"
                            value="<?= e($editData['id_penumpang'] ?? '') ?>"
                            <?= $editData ? 'readonly' : '' ?>
                            required>

                    </div>

                    <div class="field">

                        <label>Nama Penumpang</label>

                        <input
                            type="text"
                            name="nama_penumpang"
                            maxlength="50"
                            placeholder="Masukkan nama lengkap"
                            value="<?= e($editData['nama_penumpang'] ?? '') ?>"
                            required>

                    </div>

                    <div class="field">

                        <label>Jenis Kelamin</label>

                        <select name="jenis_kelamin" required>

                            <option value="">-- Pilih Jenis Kelamin --</option>

                            <option value="Laki-laki"
                                <?= ($editData['jenis_kelamin'] ?? '') === 'Laki-laki' ? 'selected' : '' ?>>
                                Laki-laki
                            </option>

                            <option value="Perempuan"
                                <?= ($editData['jenis_kelamin'] ?? '') === 'Perempuan' ? 'selected' : '' ?>>
                                Perempuan
                            </option>

                        </select>

                    </div>

                    <div class="field">

                        <label>Nomor Telepon</label>

                        <input
                            type="tel"
                            name="no_telp"
                            maxlength="15"
                            placeholder="Contoh: 081234567890"
                            value="<?= e($editData['no_telp'] ?? '') ?>"
                            required>

                    </div>

                    <div class="field">

                        <label>Alamat</label>

                        <textarea
                            name="alamat"
                            placeholder="Masukkan alamat lengkap"
                            required><?= e($editData['alamat'] ?? '') ?></textarea>

                    </div>

                </div>

                <div class="form-actions">

                    <button type="submit" class="btn btn-primary">
                        <?= $editData ? 'Simpan Perubahan' : '+ Tambah Penumpang' ?>
                    </button>

                    <?php if ($editData): ?>

                        <a href="penumpang.php" class="btn btn-secondary">
                            Batal
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>

        <!-- TABEL PENUMPANG -->

        <section class="panel">

            <div class="table-header">

                <h3>Daftar Penumpang</h3>

                <form method="GET" class="search-form">

                    <input
                        type="text"
                        name="search"
                        placeholder="Cari ID, atau nama..."
                        value="<?= e($keyword) ?>">

                    <button type="submit" class="btn btn-primary">
                        Cari
                    </button>

                    <?php if ($keyword !== ''): ?>

                        <a href="penumpang.php" class="btn btn-secondary">
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
                            <th>ID Penumpang</th>
                            <th>Nama Penumpang</th>
                            <th>Jenis Kelamin</th>
                            <th>Alamat</th>
                            <th>Nomor Telepon</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($penumpangList->num_rows > 0): ?>

                        <?php $no = 1; ?>

                        <?php while ($row = $penumpangList->fetch_assoc()): ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td>
                                    <strong><?= e($row['id_penumpang']) ?></strong>
                                </td>

                                <td><?= e($row['nama_penumpang']) ?></td>

                                <td><?= e($row['jenis_kelamin']) ?></td>

                                <td><?= e($row['alamat']) ?></td>

                                <td><?= e($row['no_telp']) ?></td>

                                <td>

                                    <div class="actions">

                                        <a
                                            href="penumpang.php?edit=<?= urlencode($row['id_penumpang']) ?>"
                                            class="btn btn-edit">
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="penumpang.php"
                                            onsubmit="return confirm('Yakin ingin menghapus data penumpang ini?')">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_penumpang']) ?>">

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete">

                                            <input
                                                type="hidden"
                                                name="id_penumpang"
                                                value="<?= e($row['id_penumpang']) ?>">

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
                            <td colspan="7" class="empty">
                                Data penumpang belum tersedia.
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
