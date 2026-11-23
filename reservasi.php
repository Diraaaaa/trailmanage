
<?php
session_start();
include("connect.php");

$db = $conn ?? $koneksi ?? $connect ?? null;

if (!$db instanceof mysqli) {
    die("Koneksi database tidak ditemukan. Periksa connect.php.");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db->set_charset("utf8mb4");

// CSRF protection
if (empty($_SESSION['csrf_reservasi'])) {
    $_SESSION['csrf_reservasi'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';
$editData = null;


// PROSES CRUD

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionStarted = false;

    try {
        if (!hash_equals(
            $_SESSION['csrf_reservasi'],
            $_POST['csrf_token'] ?? ''
        )) {
            throw new Exception("Permintaan tidak valid. Silakan muat ulang halaman.");
        }

        $action = $_POST['action'] ?? '';

        // TAMBAH DAN UPDATE RESERVASI
        if ($action === 'create' || $action === 'update') {

            $id = trim($_POST['id_reservasi'] ?? '');
            $id_penumpang = trim($_POST['id_penumpang'] ?? '');
            $id_kereta = trim($_POST['id_kereta'] ?? '');
            $tgl_berangkat = trim($_POST['tgl_berangkat'] ?? '');
            $tgl_pesan = trim($_POST['tgl_pesan'] ?? '');

            if (
                $id === '' ||
                strlen($id) > 10 ||
                $id_penumpang === '' ||
                $id_kereta === '' ||
                $tgl_berangkat === '' ||
                $tgl_pesan === ''
            ) {
                throw new Exception("Mohon lengkapi seluruh data dengan benar.");
            }

            // Validasi tanggal keberangkatan
            $dateBerangkat = DateTime::createFromFormat(
                'Y-m-d\TH:i',
                $tgl_berangkat
            );

            if (
                !$dateBerangkat ||
                $dateBerangkat->format('Y-m-d\TH:i') !== $tgl_berangkat
            ) {
                throw new Exception("Format tanggal keberangkatan tidak valid.");
            }

            // Validasi tanggal pemesanan
            $datePesan = DateTime::createFromFormat('Y-m-d', $tgl_pesan);

            if (
                !$datePesan ||
                $datePesan->format('Y-m-d') !== $tgl_pesan
            ) {
                throw new Exception("Format tanggal pemesanan tidak valid.");
            }

            $tgl_berangkat_db = $dateBerangkat->format('Y-m-d H:i:s');

            $db->begin_transaction();
            $transactionStarted = true;

            if ($action === 'create') {

                // INSERT RESERVASI
                // id_transaksi otomatis NULL terlebih dahulu

                $stmt = $db->prepare("
                    INSERT INTO reservasi
                    (
                        id_reservasi,
                        id_penumpang,
                        id_kereta,
                        tgl_berangkat,
                        tgl_pesan
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "sssss",
                    $id,
                    $id_penumpang,
                    $id_kereta,
                    $tgl_berangkat_db,
                    $tgl_pesan
                );

                $stmt->execute();
                $stmt->close();

            } else {

                // PERIKSA RESERVASI
                $stmt = $db->prepare("
                    SELECT id_transaksi
                    FROM reservasi
                    WHERE id_reservasi = ?
                    FOR UPDATE
                ");

                $stmt->bind_param("s", $id);
                $stmt->execute();

                $dataLama = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$dataLama) {
                    throw new Exception("Data reservasi tidak ditemukan.");
                }

                // Jika sudah memiliki transaksi, jangan ubah penumpang
                // atau kereta agar data transaksi tetap konsisten.
                if (!empty($dataLama['id_transaksi'])) {

                    $stmt = $db->prepare("
                        UPDATE reservasi
                        SET tgl_berangkat = ?,
                            tgl_pesan = ?
                        WHERE id_reservasi = ?
                    ");

                    $stmt->bind_param(
                        "sss",
                        $tgl_berangkat_db,
                        $tgl_pesan,
                        $id
                    );

                } else {

                    $stmt = $db->prepare("
                        UPDATE reservasi
                        SET id_penumpang = ?,
                            id_kereta = ?,
                            tgl_berangkat = ?,
                            tgl_pesan = ?
                        WHERE id_reservasi = ?
                    ");

                    $stmt->bind_param(
                        "sssss",
                        $id_penumpang,
                        $id_kereta,
                        $tgl_berangkat_db,
                        $tgl_pesan,
                        $id
                    );
                }

                $stmt->execute();
                $stmt->close();
            }

            $db->commit();
            $transactionStarted = false;

            header("Location: reservasi.php?status=" .
                ($action === 'create' ? 'added' : 'updated'));
            exit;
        }

        // HAPUS RESERVASI
        elseif ($action === 'delete') {

            $id = trim($_POST['id_reservasi'] ?? '');

            if ($id === '') {
                throw new Exception("ID reservasi tidak valid.");
            }

            $db->begin_transaction();
            $transactionStarted = true;

            // Periksa apakah reservasi memiliki transaksi
            $stmt = $db->prepare("
                SELECT id_transaksi
                FROM reservasi
                WHERE id_reservasi = ?
                FOR UPDATE
            ");

            $stmt->bind_param("s", $id);
            $stmt->execute();

            $data = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$data) {
                throw new Exception("Data reservasi tidak ditemukan.");
            }

            if (!empty($data['id_transaksi'])) {
                throw new Exception(
                    "Reservasi tidak dapat dihapus karena sudah memiliki transaksi. Hapus transaksi terlebih dahulu."
                );
            }

            $stmt = $db->prepare("
                DELETE FROM reservasi
                WHERE id_reservasi = ?
            ");

            $stmt->bind_param("s", $id);
            $stmt->execute();
            $stmt->close();

            $db->commit();
            $transactionStarted = false;

            header("Location: reservasi.php?status=deleted");
            exit;
        }

    } catch (Throwable $ex) {

        if ($transactionStarted) {
            try {
                $db->rollback();
            } catch (Throwable $ignored) {
            }
        }

        if ($ex instanceof mysqli_sql_exception) {
            if ($ex->getCode() == 1062) {
                $error = "ID reservasi sudah digunakan.";
            } elseif ($ex->getCode() == 1452) {
                $error = "Data penumpang atau kereta tidak ditemukan.";
            } elseif ($ex->getCode() == 1451) {
                $error = "Reservasi tidak dapat dihapus karena masih terhubung dengan transaksi.";
            } else {
                $error = "Terjadi kesalahan database. Periksa kembali data.";
            }
        } else {
            $error = $ex->getMessage();
        }
    }
}


// NOTIFIKASI

if (isset($_GET['status'])) {
    $status = $_GET['status'];

    if ($status === 'added') {
        $message = "Reservasi berhasil ditambahkan.";
    } elseif ($status === 'updated') {
        $message = "Reservasi berhasil diperbarui.";
    } elseif ($status === 'deleted') {
        $message = "Reservasi berhasil dihapus.";
    }
}


// EDIT RESERVASI

if (isset($_GET['edit'])) {

    $id_edit = trim($_GET['edit']);

    $stmt = $db->prepare("
        SELECT *
        FROM reservasi
        WHERE id_reservasi = ?
    ");

    $stmt->bind_param("s", $id_edit);
    $stmt->execute();

    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}


// DROPDOWN PENUMPANG

$penumpangList = $db->query("
    SELECT id_penumpang, nama_penumpang
    FROM penumpang
    ORDER BY nama_penumpang ASC
");


// DROPDOWN KERETA

$keretaList = $db->query("
    SELECT
        id_kereta,
        nm_kereta,
        kelas_kereta,
        jam_berangkat,
        harga_tiket,
        stasiun_asal,
        stasiun_tujuan
    FROM kereta
    ORDER BY nm_kereta ASC
");


// PENCARIAN RESERVASI

$keyword = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        r.*,
        p.nama_penumpang,
        k.nm_kereta,
        k.kelas_kereta,
        k.stasiun_asal,
        k.stasiun_tujuan
    FROM reservasi r
    LEFT JOIN penumpang p
        ON r.id_penumpang = p.id_penumpang
    LEFT JOIN kereta k
        ON r.id_kereta = k.id_kereta
";

if ($keyword !== '') {

    $sql .= "
        WHERE r.id_reservasi LIKE ?
        OR p.nama_penumpang LIKE ?
        OR k.nm_kereta LIKE ?
    ";

    $stmt = $db->prepare($sql . " ORDER BY CAST(SUBSTRING(r.id_reservasi, 3) AS UNSIGNED) ASC");

    $like = "%$keyword%";

    $stmt->bind_param(
        "sss",
        $like,
        $like,
        $like
    );

    $stmt->execute();
    $reservasiList = $stmt->get_result();

} else {

    $reservasiList = $db->query(
        $sql . " ORDER BY CAST(SUBSTRING(r.id_reservasi, 3) AS UNSIGNED) ASC"
    );
}


// RINGKASAN

$summary = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(tgl_pesan = CURDATE()) AS hari_ini,
        SUM(id_transaksi IS NULL OR id_transaksi = '') AS belum_bayar,
        SUM(id_transaksi IS NOT NULL AND id_transaksi <> '') AS sudah_bayar
    FROM reservasi
")->fetch_assoc();

// GENERATE ID RESERVASI OTOMATIS

$resultID = $db->query("
    SELECT MAX(CAST(SUBSTRING(id_reservasi, 3) AS UNSIGNED)) AS nomor
    FROM reservasi
    WHERE id_reservasi REGEXP '^RV[0-9]+$'
");

$dataID = $resultID->fetch_assoc();

$nomorBaru = (int)($dataID['nomor'] ?? 0) + 1;

$idOtomatis = 'RV' . str_pad($nomorBaru, 2, '0', STR_PAD_LEFT);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="topbar.css">

    <title>Reservasi</title>

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

        /* SUMMARY */

        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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

        .field input:disabled,
        .field select:disabled {
            background: #F2F4F6;
            color: #777;
            cursor: not-allowed;
        }

        .full {
            grid-column: 1 / -1;
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

        /* SEARCH */

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

        /* TABEL */

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

        .badge {
            padding: 6px 10px;
            border-radius: 20px;
            background: #EAF1F1;
            color: var(--primary);
            font-size: 11px;
            font-weight: bold;
        }

        .badge-paid {
            background: #E8F5ED;
            color: #28734A;
        }

        .badge-unpaid {
            background: #FFF4DF;
            color: #A66B12;
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

        .form-note {
            color: var(--muted);
            font-size: 11px;
            margin-top: 5px;
            line-height: 1.5;
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

            .admin-profile,
            .admin-info {
                display: none;
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

            .full {
                grid-column: auto;
            }

            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-form,
            .search-form input {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="dashboard">

    <!-- SIDEBAR -->

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

            <a href="dashboard.php">
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

            <a href="reservasi.php" class="active">
                <span class="menu-icon">📒</span>
                <span>Reservasi</span>
            </a>

            <a href="transaksi.php">
                <span class="menu-icon">💵</span>
                <span>Transaksi</span>
            </a>

        </nav>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main">

        <!-- TOPBAR -->

        <header class="topbar">

            <div>
                <h2>Manajemen Reservasi</h2>
                <p>Kelola pemesanan tiket kereta api</p>
            </div>

            <div class="admin-info">
                <div>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
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
                <small>Total Reservasi</small>
                <h3><?= (int)$summary['total'] ?></h3>
            </div>

            <div class="summary-card">
                <small>Reservasi Hari Ini</small>
                <h3><?= (int)$summary['hari_ini'] ?></h3>
            </div>

            <div class="summary-card">
                <small>Belum Memiliki Transaksi</small>
                <h3><?= (int)$summary['belum_bayar'] ?></h3>
            </div>

            <div class="summary-card">
                <small>Sudah Memiliki Transaksi</small>
                <h3><?= (int)$summary['sudah_bayar'] ?></h3>
            </div>

        </section>


        <!-- FORM RESERVASI -->

        <section class="panel">

            <h3>
                <?= $editData ? 'Edit Reservasi' : 'Tambah Reservasi' ?>
            </h3>

            <form method="POST" action="reservasi.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_reservasi']) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="<?= $editData ? 'update' : 'create' ?>"
                >

                <div class="form-grid">

                    <!-- ID RESERVASI -->

                    <div class="field">
                        <label>ID Reservasi</label>

                        <input
        type="text"
        name="id_reservasi"
        value="<?= e($editData['id_reservasi'] ?? $idOtomatis) ?>"
        readonly
        required
    >
                    </div>


                    <!-- PENUMPANG -->

                    <div class="field">
                        <label>Nama Penumpang</label>

                        <select
                            name="id_penumpang"
                            <?= (
                                $editData &&
                                !empty($editData['id_transaksi'])
                            ) ? 'disabled' : '' ?>
                            required
                        >

                            <option value="">Pilih Penumpang</option>

                            <?php while ($p = $penumpangList->fetch_assoc()): ?>

                                <option
                                    value="<?= e($p['id_penumpang']) ?>"
                                    <?= (
                                        ($editData['id_penumpang'] ?? '') ===
                                        $p['id_penumpang']
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= e($p['nama_penumpang']) ?>
                                    (<?= e($p['id_penumpang']) ?>)
                                </option>

                            <?php endwhile; ?>

                        </select>

                        <?php if ($editData && !empty($editData['id_transaksi'])): ?>
                            <input
                                type="hidden"
                                name="id_penumpang"
                                value="<?= e($editData['id_penumpang']) ?>"
                            >
                        <?php endif; ?>
                    </div>


                    <!-- KERETA -->

                    <div class="field">
                        <label>Nama Kereta</label>

                        <select
                            name="id_kereta"
                            <?= (
                                $editData &&
                                !empty($editData['id_transaksi'])
                            ) ? 'disabled' : '' ?>
                            required
                        >

                            <option value="">Pilih Kereta</option>

                            <?php while ($k = $keretaList->fetch_assoc()): ?>

                                <option
                                    value="<?= e($k['id_kereta']) ?>"
                                    <?= (
                                        ($editData['id_kereta'] ?? '') ===
                                        $k['id_kereta']
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= e($k['nm_kereta']) ?>
                                    - <?= e($k['kelas_kereta']) ?>
                                    (<?= e($k['id_kereta']) ?>)
                                </option>

                            <?php endwhile; ?>

                        </select>

                        <?php if ($editData && !empty($editData['id_transaksi'])): ?>
                            <input
                                type="hidden"
                                name="id_kereta"
                                value="<?= e($editData['id_kereta']) ?>"
                            >
                        <?php endif; ?>
                    </div>


                    <!-- TANGGAL KEBERANGKATAN -->

                    <div class="field">
                        <label>Tanggal dan Jam Keberangkatan</label>

                        <input
                            type="datetime-local"
                            name="tgl_berangkat"
                            value="<?= !empty($editData['tgl_berangkat'])
                                ? date(
                                    'Y-m-d\TH:i',
                                    strtotime($editData['tgl_berangkat'])
                                )
                                : '' ?>"
                            required
                        >
                    </div>


                    <!-- TANGGAL PEMESANAN -->

                    <div class="field">
                        <label>Tanggal Pemesanan</label>

                        <input
                            type="date"
                            name="tgl_pesan"
                            value="<?= e(
                                $editData['tgl_pesan'] ?? date('Y-m-d')
                            ) ?>"
                            required
                        >
                    </div>

                </div>

                <?php if ($editData && !empty($editData['id_transaksi'])): ?>
                    <p class="form-note">
                        Reservasi ini sudah memiliki transaksi
                        Data penumpang dan kereta tidak dapat diubah
                        untuk menjaga kesesuaian informasi transaksi
                    </p>
                <?php endif; ?>


                <!-- BUTTON -->

                <div class="form-actions">

                    <button type="submit" class="btn-primary">
                        <?= $editData
                            ? 'Simpan Perubahan'
                            : '+ Tambah Reservasi' ?>
                    </button>

                    <?php if ($editData): ?>

                        <a href="reservasi.php" class="btn btn-secondary">
                            Batal
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>


        <!-- TABEL RESERVASI -->

        <section class="panel">

            <div class="table-header">

                <h3>Daftar Reservasi</h3>

                <form method="GET" class="search-form">

                    <input
                        type="text"
                        name="search"
                        placeholder="Cari ID, penumpang, atau kereta..."
                        value="<?= e($keyword) ?>"
                    >

                    <button type="submit" class="btn-primary">
                        Cari
                    </button>

                    <?php if ($keyword !== ''): ?>

                        <a href="reservasi.php" class="btn btn-secondary">
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
                            <th>ID Reservasi</th>
                            <th>Nama Penumpang</th>
                            <th>Nama Kereta</th>
                            <th>Rute</th>
                            <th>Tanggal Berangkat</th>
                            <th>Tanggal Pesan</th>
                            <th>Status Transaksi</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($reservasiList->num_rows > 0): ?>

                        <?php $no = 1; ?>

                        <?php while ($row = $reservasiList->fetch_assoc()): ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td>
                                    <strong>
                                        <?= e($row['id_reservasi']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e($row['nama_penumpang'] ?: '-') ?>
                                </td>

                                <td>
                                    <?= e($row['nm_kereta'] ?: '-') ?>
                                    <br>
                                    <small>
                                        <?= e($row['kelas_kereta'] ?: '-') ?>
                                    </small>
                                </td>

                                <td>
                                    <?= e($row['stasiun_asal'] ?: '-') ?>
                                    <br>
                                    ↓
                                    <br>
                                    <?= e($row['stasiun_tujuan'] ?: '-') ?>
                                </td>

                                <td>
                                    <?= !empty($row['tgl_berangkat'])
                                        ? date(
                                            'd/m/Y H:i',
                                            strtotime($row['tgl_berangkat'])
                                        )
                                        : '-' ?>
                                </td>

                                <td>
                                    <?= !empty($row['tgl_pesan'])
                                        ? date(
                                            'd/m/Y',
                                            strtotime($row['tgl_pesan'])
                                        )
                                        : '-' ?>
                                </td>

                                <td>

                                    <?php if (!empty($row['id_transaksi'])): ?>

                                        <span class="badge badge-paid">
                                            Sudah Transaksi
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-unpaid">
                                            Belum Transaksi
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <div class="actions">

                                        <a
                                            href="reservasi.php?edit=<?= urlencode($row['id_reservasi']) ?>"
                                            class="btn btn-edit"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="reservasi.php"
                                            onsubmit="return confirm('Yakin ingin menghapus reservasi ini?')"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_reservasi']) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_reservasi"
                                                value="<?= e($row['id_reservasi']) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-delete"
                                            >
                                                Hapus
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="9" class="empty">
                                Belum ada data reservasi.
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
