
<?php
session_start();
include("connect.php");

$db = $conn ?? $koneksi ?? $connect ?? null;

if (!$db instanceof mysqli) {
    die("Koneksi database tidak ditemukan. Periksa connect.php.");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// CSRF protection
if (empty($_SESSION['csrf_transaksi'])) {
    $_SESSION['csrf_transaksi'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

$message = '';
$error = '';


// PROSES CRUD SQL

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        if (!hash_equals(
            $_SESSION['csrf_transaksi'],
            $_POST['csrf_token'] ?? ''
        )) {
            throw new Exception("Permintaan tidak valid. Silakan muat ulang halaman.");
        }

        $action = $_POST['action'] ?? '';

        // TAMBAH TRANSAKSI

        if ($action === 'create' || $action === 'update') {

            $id = trim($_POST['id_transaksi'] ?? '');
            $id_reservasi = trim($_POST['id_reservasi'] ?? '');
            $metode = $_POST['metode_bayar'] ?? '';
            $tgl = $_POST['tgl_bayar'] ?? '';
            $jumlah = filter_var($_POST['jml_tiket'] ?? null, FILTER_VALIDATE_INT);
            $harga = filter_var($_POST['harga_tiket'] ?? null, FILTER_VALIDATE_INT);

            if (
                $id === '' ||
                strlen($id) > 6 ||
                $id_reservasi === '' ||
                !in_array($metode, ['Cash', 'Cashless'], true) ||
                !$jumlah || $jumlah < 1 ||
                $harga === false || $harga < 0 ||
                $tgl === ''
            ) {
                throw new Exception("Mohon lengkapi seluruh data dengan benar.");
            }

            $date = DateTime::createFromFormat('Y-m-d\TH:i', $tgl);

            if (!$date || $date->format('Y-m-d\TH:i') !== $tgl) {
                throw new Exception("Format tanggal pembayaran tidak valid.");
            }

            $tgl = $date->format('Y-m-d H:i:s');
            $total = $jumlah * $harga;

            $db->begin_transaction();

            // Pastikan reservasi tersedia
            $stmt = $db->prepare("
                SELECT id_reservasi, id_transaksi
                FROM reservasi
                WHERE id_reservasi = ?
                FOR UPDATE
            ");

            $stmt->bind_param("s", $id_reservasi);
            $stmt->execute();

            $reservasi = $stmt->get_result()->fetch_assoc();

            if (!$reservasi) {
                throw new Exception("Data reservasi tidak ditemukan.");
            }

            if (
                $reservasi['id_transaksi'] !== null &&
                $reservasi['id_transaksi'] !== '' &&
                $reservasi['id_transaksi'] !== $id
            ) {
                throw new Exception("Reservasi tersebut sudah memiliki transaksi.");
            }

            if ($action === 'create') {

                // Periksa ID transaksi
                $check = $db->prepare("
                    SELECT id_transaksi
                    FROM transaksi
                    WHERE id_transaksi = ?
                ");

                $check->bind_param("s", $id);
                $check->execute();

                if ($check->get_result()->num_rows > 0) {
                    throw new Exception("ID transaksi sudah digunakan.");
                }

                $stmt = $db->prepare("
                    INSERT INTO transaksi
                    (id_transaksi, metode_bayar, tgl_bayar,
                    jml_tiket, harga_tiket, total_bayar, id_reservasi)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "sssiiis",
                    $id,
                    $metode,
                    $tgl,
                    $jumlah,
                    $harga,
                    $total,
                    $id_reservasi
                );

                $stmt->execute();

            } else {

                // Ambil reservasi lama
                $stmt = $db->prepare("
                    SELECT id_reservasi
                    FROM transaksi
                    WHERE id_transaksi = ?
                    FOR UPDATE
                ");

                $stmt->bind_param("s", $id);
                $stmt->execute();

                $lama = $stmt->get_result()->fetch_assoc();

                if (!$lama) {
                    throw new Exception("Transaksi tidak ditemukan.");
                }

                $reservasi_lama = $lama['id_reservasi'];

                // UPDATE
                $stmt = $db->prepare("
                    UPDATE transaksi
                    SET metode_bayar = ?,
                        tgl_bayar = ?,
                        jml_tiket = ?,
                        harga_tiket = ?,
                        total_bayar = ?,
                        id_reservasi = ?
                    WHERE id_transaksi = ?
                ");

                $stmt->bind_param(
                    "ssiiiss",
                    $metode,
                    $tgl,
                    $jumlah,
                    $harga,
                    $total,
                    $id_reservasi,
                    $id
                );

                $stmt->execute();

                if ($reservasi_lama !== $id_reservasi) {

                    $stmt = $db->prepare("
                        UPDATE reservasi
                        SET id_transaksi = NULL
                        WHERE id_reservasi = ?
                        AND id_transaksi = ?
                    ");

                    $stmt->bind_param("ss", $reservasi_lama, $id);
                    $stmt->execute();
                }
            }

            $stmt = $db->prepare("
                UPDATE reservasi
                SET id_transaksi = ?
                WHERE id_reservasi = ?
            ");

            $stmt->bind_param("ss", $id, $id_reservasi);
            $stmt->execute();

            $db->commit();

            header("Location: transaksi.php?status=" .
                ($action === 'create' ? 'added' : 'updated'));
            exit;
        }

        // HAPUS

        elseif ($action === 'delete') {

            $id = trim($_POST['id_transaksi'] ?? '');

            if ($id === '') {
                throw new Exception("ID transaksi tidak valid.");
            }

            $db->begin_transaction();

            $stmt = $db->prepare("
                SELECT id_reservasi
                FROM transaksi
                WHERE id_transaksi = ?
                FOR UPDATE
            ");

            $stmt->bind_param("s", $id);
            $stmt->execute();

            $data = $stmt->get_result()->fetch_assoc();

            if (!$data) {
                throw new Exception("Transaksi tidak ditemukan.");
            }

            $stmt = $db->prepare("
                UPDATE reservasi
                SET id_transaksi = NULL
                WHERE id_transaksi = ?
            ");

            $stmt->bind_param("s", $id);
            $stmt->execute();

            $stmt = $db->prepare("
                DELETE FROM transaksi
                WHERE id_transaksi = ?
            ");

            $stmt->bind_param("s", $id);
            $stmt->execute();

            $db->commit();

            header("Location: transaksi.php?status=deleted");
            exit;
        }

    } catch (Throwable $ex) {

        if ($db->errno || $db->thread_id) {
            try {
                $db->rollback();
            } catch (Throwable $ignored) {
            }
        }

        $error = $ex->getMessage();
    }
}


// NOTIFIKASI

if (isset($_GET['status'])) {
    $status = $_GET['status'];

    if ($status === 'added') {
        $message = "Transaksi berhasil ditambahkan.";
    } elseif ($status === 'updated') {
        $message = "Transaksi berhasil diperbarui.";
    } elseif ($status === 'deleted') {
        $message = "Transaksi berhasil dihapus.";
    }
}

// EDIT

$editData = null;

if (isset($_GET['edit'])) {

    $id_edit = trim($_GET['edit']);

    $stmt = $db->prepare("
        SELECT *
        FROM transaksi
        WHERE id_transaksi = ?
    ");

    $stmt->bind_param("s", $id_edit);
    $stmt->execute();

    $editData = $stmt->get_result()->fetch_assoc();
}

// DATA RESERVASI DROPDOWN

$reservasiList = $db->query("
    SELECT
        r.id_reservasi,
        r.id_transaksi,
        p.nama_penumpang,
        k.nm_kereta
    FROM reservasi r
    LEFT JOIN penumpang p
        ON r.id_penumpang = p.id_penumpang
    LEFT JOIN kereta k
        ON r.id_kereta = k.id_kereta
    ORDER BY r.id_reservasi ASC
");

// PENCARIAN 

$keyword = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        t.*,
        r.id_reservasi AS reservasi,
        p.nama_penumpang,
        k.nm_kereta
    FROM transaksi t
    LEFT JOIN reservasi r
        ON t.id_reservasi = r.id_reservasi
    LEFT JOIN penumpang p
        ON r.id_penumpang = p.id_penumpang
    LEFT JOIN kereta k
        ON r.id_kereta = k.id_kereta
";

if ($keyword !== '') {

    $sql .= "
        WHERE t.id_transaksi LIKE ?
        OR t.id_reservasi LIKE ?
        OR p.nama_penumpang LIKE ?
        OR k.nm_kereta LIKE ?
    ";

    $stmt = $db->prepare($sql . " ORDER BY CAST(SUBSTRING(r.id_transaksi, 3) AS UNSIGNED) ASC");

    $like = "%$keyword%";

    $stmt->bind_param(
        "ssss",
        $like,
        $like,
        $like,
        $like
    );

    $stmt->execute();
    $transaksiList = $stmt->get_result();

} else {

    $transaksiList = $db->query(
        $sql . " ORDER BY CAST(SUBSTRING(r.id_transaksi, 3) AS UNSIGNED) ASC"
    );
}

$summary = $db->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(total_bayar), 0) AS pendapatan,
        SUM(metode_bayar = 'Cash') AS cash,
        SUM(metode_bayar = 'Cashless') AS cashless
    FROM transaksi
")->fetch_assoc();

// GENERATE ID RESERVASI OTOMATIS

$resultID = $db->query("
    SELECT MAX(CAST(SUBSTRING(id_transaksi, 3) AS UNSIGNED)) AS nomor
    FROM transaksi
    WHERE id_transaksi REGEXP '^TS[0-9]+$'
");

$dataID = $resultID->fetch_assoc();

$nomorBaru = (int)($dataID['nomor'] ?? 0) + 1;

$idOtomatis = 'TS' . str_pad($nomorBaru, 2, '0', STR_PAD_LEFT);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="topbar.css" />
    <title>Transaksi</title>
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

            .admin-profile {
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
    <!--SIDEBAR-->
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

            <a href="reservasi.php">
                <span class="menu-icon">📒</span>
                <span>Reservasi</span>
            </a>

            <a href="transaksi.php" class="active">
                <span class="menu-icon">💵</span>
                <span>Transaksi</span>
            </a>
        </nav>

    </aside>

    <main class="main">
        <!--TOPBAR-->
        <header class="topbar">
            <div>
                <h2>Manajemen Transaksi</h2>
                <p>Kelola pembayaran dan transaksi tiket kereta api</p>
            </div>
            <div class="admin-info">
                <div>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

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
                <small>Total Transaksi</small>
                <h3><?= (int)$summary['total'] ?></h3>
            </div>

            <div class="summary-card">
                <small>Total Pendapatan</small>
                <h3 style="font-size:19px">
                    <?= rupiah($summary['pendapatan']) ?>
                </h3>
            </div>

            <div class="summary-card">
                <small>Pembayaran Cash</small>
                <h3><?= (int)$summary['cash'] ?></h3>
            </div>

            <div class="summary-card">
                <small>Pembayaran Cashless</small>
                <h3><?= (int)$summary['cashless'] ?></h3>
            </div>

        </section>

        <!-- FORM CREATE-->

        <section class="panel">

            <h3>
                <?= $editData ? 'Edit Transaksi' : 'Tambah Transaksi' ?>
            </h3>

            <form method="POST" action="transaksi.php">

                <input type="hidden" name="csrf_token"
                    value="<?= e($_SESSION['csrf_transaksi']) ?>">

                <input type="hidden" name="action"
                    value="<?= $editData ? 'update' : 'create' ?>">

                <div class="form-grid">

                    <div class="field">
                        <label>ID Transaksi</label>

                        <input
                            type="text"
                            name="id_transaksi"
                            value="<?= e($editData['id_transaksi'] ?? $idOtomatis) ?>"
                            readonly
                            required
                        >
                    </div>

                    <div class="field">
                        <label>ID Reservasi</label>

                        <select name="id_reservasi" required>
                            <option value="">Pilih Reservasi</option>

                            <?php while ($r = $reservasiList->fetch_assoc()): ?>

                                <?php
                                $selected = $editData &&
                                    $editData['id_reservasi'] === $r['id_reservasi'];

                                $occupied = !empty($r['id_transaksi']) &&
                                    (!$editData ||
                                    $r['id_transaksi'] !== $editData['id_transaksi']);
                                ?>

                                <?php if (!$occupied): ?>

                                    <option
                                        value="<?= e($r['id_reservasi']) ?>"
                                        <?= $selected ? 'selected' : '' ?>
                                    >
                                        <?= e($r['id_reservasi']) ?>
                                        - <?= e($r['nama_penumpang']) ?>
                                        (<?= e($r['nm_kereta']) ?>)
                                    </option>

                                <?php endif; ?>

                            <?php endwhile; ?>

                        </select>
                    </div>

                    <div class="field">
                        <label>Metode Pembayaran</label>

                        <select name="metode_bayar" required>
                            <option value="">Pilih Metode</option>

                            <option value="Cash"
                                <?= ($editData['metode_bayar'] ?? '') === 'Cash'
                                    ? 'selected' : '' ?>>
                                Cash
                            </option>

                            <option value="Cashless"
                                <?= ($editData['metode_bayar'] ?? '') === 'Cashless'
                                    ? 'selected' : '' ?>>
                                Cashless
                            </option>
                        </select>
                    </div>

                    <div class="field">
                        <label>Tanggal Pembayaran</label>

                        <input
                            type="datetime-local"
                            name="tgl_bayar"
                            value="<?= !empty($editData['tgl_bayar'])
                                ? date('Y-m-d\TH:i', strtotime($editData['tgl_bayar']))
                                : date('Y-m-d\TH:i') ?>"
                            required
                        >
                    </div>

                    <div class="field">
                        <label>Jumlah Tiket</label>

                        <input
                            type="number"
                            name="jml_tiket"
                            min="1"
                            placeholder="Masukkan jumlah tiket"
                            value="<?= e($editData['jml_tiket'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="field">
                        <label>Harga Tiket (Rp)</label>

                        <input
                            type="number"
                            name="harga_tiket"
                            min="0"
                            placeholder="Masukkan harga tiket"
                            value="<?= e($editData['harga_tiket'] ?? '') ?>"
                            required
                        >
                    </div>

                </div>

                <div class="form-actions">

                    <button type="submit" class="btn-primary">
                        <?= $editData ? 'Simpan Perubahan' : '+ Tambah Transaksi' ?>
                    </button>

                    <?php if ($editData): ?>
                        <a href="transaksi.php" class="btn btn-secondary">
                            Batal
                        </a>
                    <?php endif; ?>

                </div>
            </form>

        </section>

        <!-- TABEL -->

        <section class="panel">

            <div class="table-header">

                <h3>Daftar Transaksi</h3>

                <form method="GET" class="search-form">

                    <input
                        type="text"
                        name="search"
                        placeholder="Cari ID atau nama..."
                        value="<?= e($keyword) ?>"
                    >

                    <button type="submit" class="btn-primary">
                        Cari
                    </button>

                    <?php if ($keyword !== ''): ?>
                        <a href="transaksi.php" class="btn btn-secondary">
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
                            <th>ID Transaksi</th>
                            <th>ID Reservasi</th>
                            <th>Nama Penumpang</th>
                            <th>Nama Kereta</th>
                            <th>Metode Bayar</th>
                            <th>Jumlah Tiket</th>
                            <th>Tanggal Bayar</th>
                            <th>Total Bayar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($transaksiList->num_rows > 0): ?>

                        <?php $no = 1; ?>

                        <?php while ($row = $transaksiList->fetch_assoc()): ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td>
                                    <strong><?= e($row['id_transaksi']) ?></strong>
                                </td>

                                <td><?= e($row['reservasi']) ?></td>

                                <td><?= e($row['nama_penumpang'] ?: '-') ?></td>

                                <td><?= e($row['nm_kereta'] ?: '-') ?></td>

                                <td>
                                    <span class="badge">
                                        <?= e($row['metode_bayar']) ?>
                                    </span>
                                </td>

                                <td><?= e($row['jml_tiket']) ?></td>

                                <td>
                                    <?= $row['tgl_bayar']
                                        ? date('d/m/Y H:i', strtotime($row['tgl_bayar']))
                                        : '-' ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= rupiah($row['total_bayar']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <div class="actions">

                                        <a
                                            href="transaksi.php?edit=<?= urlencode($row['id_transaksi']) ?>"
                                            class="btn btn-edit"
                                        >
                                            Edit
                                        </a>

                                        <form method="POST"
                                            action="transaksi.php"
                                            onsubmit="return confirm('Yakin ingin menghapus transaksi ini?')">

                                            <input type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_transaksi']) ?>">

                                            <input type="hidden"
                                                name="action"
                                                value="delete">

                                            <input type="hidden"
                                                name="id_transaksi"
                                                value="<?= e($row['id_transaksi']) ?>">

                                            <button type="submit" class="btn-delete">
                                                Hapus
                                            </button>

                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="10" class="empty">
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
