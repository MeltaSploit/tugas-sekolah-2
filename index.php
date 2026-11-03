
<?php
session_start();

require_once 'config/database.php';

// ========================================
// KONFIGURASI
// ========================================

$tanggal_nikah = '2026-11-20T08:00:00';
$error = '';
$success = '';

// ========================================
// AMBIL DATA TAMU DARI URL
// Format: index.php?to=PANJI001
// ========================================

$kode = trim($_GET['to'] ?? $_POST['kode_unik'] ?? '');

$tamu = null;

if ($kode !== '') {
    $stmt = $pdo->prepare(
        "SELECT * FROM tamu WHERE kode_unik = ?"
    );

    $stmt->execute([$kode]);
    $tamu = $stmt->fetch();
}

$nama_tamu = $tamu
    ? $tamu['nama_tamu']
    : 'Tamu Undangan';

// ========================================
// PROSES FORM UCAPAN
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama = trim($_POST['nama'] ?? '');
    $pesan = trim($_POST['pesan'] ?? '');
    $kehadiran = $_POST['kehadiran'] ?? '';

    $kode_post = trim($_POST['kode_unik'] ?? '');

    if (
        $nama === '' ||
        $pesan === '' ||
        !in_array($kehadiran, ['Hadir', 'Tidak Hadir'], true)
    ) {

        $error = 'Semua kolom wajib diisi!';

    } elseif (strlen($nama) > 100) {

        $error = 'Nama maksimal 100 karakter.';

    } else {

        try {

            $pdo->beginTransaction();

            // Pastikan kode tamu valid jika diberikan
            if ($kode_post !== '') {

                $check = $pdo->prepare(
                    "SELECT id FROM tamu WHERE kode_unik = ?"
                );

                $check->execute([$kode_post]);

                if (!$check->fetch()) {
                    throw new Exception('Kode undangan tidak valid.');
                }

            }

            // Simpan ucapan
            $stmt = $pdo->prepare("
                INSERT INTO ucapan
                (nama, pesan, kehadiran, kode_unik)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $nama,
                $pesan,
                $kehadiran,
                $kode_post !== '' ? $kode_post : null
            ]);

            // Update status kehadiran tamu
            if ($kode_post !== '') {

                $update = $pdo->prepare("
                    UPDATE tamu
                    SET status_hadir = ?
                    WHERE kode_unik = ?
                ");

                $update->execute([
                    $kehadiran,
                    $kode_post
                ]);

            }

            $pdo->commit();

            header(
                "Location: index.php?to=" .
                urlencode($kode_post) .
                "#ucapan"
            );

            exit;

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Gagal menyimpan ucapan. Silakan coba lagi.';

        }

    }

}

// ========================================
// AMBIL DATA UCAPAN
// ========================================

$stmt = $pdo->query("
    SELECT nama, pesan, kehadiran, tanggal
    FROM ucapan
    ORDER BY tanggal DESC
");

$list_ucapan = $stmt->fetchAll();

$total_ucapan = count($list_ucapan);

// Statistik kehadiran
$statistik = $pdo->query("
    SELECT
        SUM(kehadiran = 'Hadir') AS hadir,
        SUM(kehadiran = 'Tidak Hadir') AS tidak_hadir
    FROM ucapan
")->fetch();


// Helper untuk keamanan output HTML
function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>The Wedding of Romeo & Juliet</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: Georgia, serif;
        }

        .hero {
            background:
                linear-gradient(
                    rgba(0,0,0,0.55),
                    rgba(0,0,0,0.65)
                ),
                url('asset/asseet.jpg');

            background-size: cover;
            background-position: center;
        }

        .gold {
            color: #d6b36a;
        }

        .gold-bg {
            background-color: #d6b36a;
        }

    </style>

</head>

<body class="bg-[#faf8f3] text-gray-800">


<!-- ================================== -->
<!-- HERO SECTION -->
<!-- ================================== -->

<section class="hero min-h-screen flex items-center justify-center text-center text-white px-5">

    <div class="max-w-2xl">

        <p class="uppercase tracking-[5px] text-sm mb-8 gold">
            The Wedding Of
        </p>

        <h1 class="text-5xl md:text-7xl italic mb-5">
            Romeo & Juliet
        </h1>

        <div class="w-24 h-[1px] gold-bg mx-auto my-8"></div>

        <p class="text-sm tracking-widest mb-3">
            Kepada Yth.
        </p>

        <h2 class="text-2xl md:text-3xl gold mb-8">
            <?= e($nama_tamu); ?>
        </h2>

        <p class="text-sm text-gray-200 mb-10 leading-7">
            Tanpa mengurangi rasa hormat,
            kami mengundang Anda untuk menghadiri
            acara pernikahan kami.
        </p>

        <a href="#undangan"
           class="inline-block gold-bg text-gray-900 px-8 py-3 rounded-full hover:bg-white transition">

            Buka Undangan

        </a>

    </div>

</section>


<!-- ================================== -->
<!-- PEMBUKAAN -->
<!-- ================================== -->

<section id="undangan" class="py-20 px-5 text-center">

    <p class="gold uppercase tracking-[4px] text-sm mb-5">
        Assalamu'alaikum Wr. Wb.
    </p>

    <h2 class="text-3xl md:text-4xl italic mb-6">
        Dengan Memohon Rahmat Allah SWT
    </h2>

    <p class="max-w-xl mx-auto text-gray-600 leading-8">

        Dengan penuh kebahagiaan,
        kami bermaksud menyelenggarakan
        pernikahan putra-putri kami.

        Merupakan suatu kehormatan bagi kami
        apabila Bapak/Ibu/Saudara/i berkenan hadir.

    </p>

</section>


<!-- ================================== -->
<!-- MEMPELAI -->
<!-- ================================== -->

<section class="py-16 bg-white px-5">

    <div class="max-w-4xl mx-auto text-center">

        <h2 class="text-4xl italic mb-12">
            Mempelai
        </h2>

        <div class="grid md:grid-cols-2 gap-12">

            <div>

                <div class="w-36 h-36 rounded-full gold-bg mx-auto mb-5 flex items-center justify-center text-5xl text-white">
                    R
                </div>

                <h3 class="text-3xl italic mb-3">
                    Romeo
                </h3>

                <p class="text-gray-500">
                    Putra dari Bapak & Ibu
                </p>

            </div>

            <div>

                <div class="w-36 h-36 rounded-full gold-bg mx-auto mb-5 flex items-center justify-center text-5xl text-white">
                    J
                </div>

                <h3 class="text-3xl italic mb-3">
                    Juliet
                </h3>

                <p class="text-gray-500">
                    Putri dari Bapak & Ibu
                </p>

            </div>

        </div>

    </div>

</section>


<!-- ================================== -->
<!-- COUNTDOWN -->
<!-- ================================== -->

<section class="py-20 px-5 text-center">

    <p class="gold uppercase tracking-[4px] text-sm mb-4">
        Save The Date
    </p>

    <h2 class="text-4xl italic mb-10">
        Menuju Hari Bahagia
    </h2>

    <div class="max-w-xl mx-auto grid grid-cols-4 gap-3">

        <div class="bg-white shadow-sm rounded-xl p-4">
            <h3 id="days" class="text-3xl gold">00</h3>
            <p class="text-xs mt-2">Hari</p>
        </div>

        <div class="bg-white shadow-sm rounded-xl p-4">
            <h3 id="hours" class="text-3xl gold">00</h3>
            <p class="text-xs mt-2">Jam</p>
        </div>

        <div class="bg-white shadow-sm rounded-xl p-4">
            <h3 id="minutes" class="text-3xl gold">00</h3>
            <p class="text-xs mt-2">Menit</p>
        </div>

        <div class="bg-white shadow-sm rounded-xl p-4">
            <h3 id="seconds" class="text-3xl gold">00</h3>
            <p class="text-xs mt-2">Detik</p>
        </div>

    </div>

    <p id="countdown-message"
       class="mt-6 text-gray-500">
    </p>

</section>


<!-- ================================== -->
<!-- FORM UCAPAN -->
<!-- ================================== -->

<section id="ucapan" class="py-20 px-5 bg-[#faf8f3]">

    <div class="max-w-xl mx-auto">

        <div class="text-center mb-10">

            <p class="gold uppercase tracking-[4px] text-sm mb-3">
                Guest Book
            </p>

            <h2 class="text-4xl italic mb-4">
                Ucapan & Kehadiran
            </h2>

            <p class="text-gray-500">
                Berikan doa terbaik dan konfirmasi kehadiran Anda.
            </p>

        </div>


        <form method="POST"
              action="index.php?to=<?= urlencode($kode); ?>#ucapan"
              class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-gray-100">

            <input type="hidden"
                   name="kode_unik"
                   value="<?= e($kode); ?>">

            <!-- Nama -->

            <div class="mb-5">

                <label class="block text-sm font-semibold mb-2">
                    Nama Lengkap
                </label>

                <input
                    type="text"
                    name="nama"
                    maxlength="100"
                    value="<?= e($tamu['nama_tamu'] ?? ''); ?>"
                    placeholder="Masukkan nama kamu"
                    required
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#d6b36a]"
                >

            </div>


            <!-- Kehadiran -->

            <div class="mb-5">

                <label class="block text-sm font-semibold mb-3">
                    Konfirmasi Kehadiran
                </label>

                <div class="grid grid-cols-2 gap-3">

                    <label class="cursor-pointer flex items-center gap-2">

                        <input
                            type="radio"
                            name="kehadiran"
                            value="Hadir"
                            required
                            class="accent-[#d6b36a]"
                        >

                        <span class="text-sm">
                            Hadir
                        </span>

                    </label>


                    <label class="cursor-pointer flex items-center gap-2">

                        <input
                            type="radio"
                            name="kehadiran"
                            value="Tidak Hadir"
                            class="accent-[#d6b36a]"
                        >

                        <span class="text-sm">
                            Tidak Hadir
                        </span>

                    </label>

                </div>

            </div>


            <!-- Pesan -->

            <div class="mb-6">

                <label class="block text-sm font-semibold mb-2">
                    Ucapan & Doa
                </label>

                <textarea
                    name="pesan"
                    rows="5"
                    placeholder="Tuliskan ucapan dan doa terbaik..."
                    required
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#d6b36a] resize-none"
                ></textarea>

            </div>


            <!-- Error -->

            <?php if ($error !== ''): ?>

                <p class="text-red-500 text-sm mb-4">
                    <?= e($error); ?>
                </p>

            <?php endif; ?>


            <!-- Submit -->

            <button
                type="submit"
                class="w-full gold-bg text-white py-3 rounded-xl font-semibold hover:bg-[#bd9b54] transition">

                Kirim Ucapan

            </button>

        </form>

    </div>

</section>


<!-- ================================== -->
<!-- STATISTIK KEHADIRAN -->
<!-- ================================== -->

<section class="py-16 px-5 text-center">

    <h2 class="text-3xl italic mb-10">
        Konfirmasi Kehadiran
    </h2>

    <div class="max-w-lg mx-auto grid grid-cols-2 gap-5">

        <div class="bg-white p-6 rounded-2xl shadow-sm">

            <h3 class="text-4xl text-green-600 font-bold">
                <?= (int) ($statistik['hadir'] ?? 0); ?>
            </h3>

            <p class="text-gray-500 mt-2">
                Hadir
            </p>

        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm">

            <h3 class="text-4xl text-red-500 font-bold">
                <?= (int) ($statistik['tidak_hadir'] ?? 0); ?>
            </h3>

            <p class="text-gray-500 mt-2">
                Tidak Hadir
            </p>

        </div>

    </div>

</section>


<!-- ================================== -->
<!-- DAFTAR UCAPAN -->
<!-- ================================== -->

<section class="py-20 px-5 bg-white">

    <div class="max-w-2xl mx-auto">

        <div class="text-center mb-10">

            <p class="gold uppercase tracking-[4px] text-sm mb-3">
                Wishes
            </p>

            <h2 class="text-4xl italic">
                Ucapan & Doa
            </h2>

            <p class="text-gray-500 mt-3">
                <?= $total_ucapan; ?> ucapan telah dikirim.
            </p>

        </div>


        <div class="space-y-4">

            <?php if (!empty($list_ucapan)): ?>

                <?php foreach ($list_ucapan as $ucapan): ?>

                    <div class="bg-[#faf8f3] p-5 rounded-xl border border-gray-100">

                        <div class="flex justify-between items-center gap-3 mb-3">

                            <h3 class="font-bold">
                                <?= e($ucapan['nama']); ?>
                            </h3>

                            <span class="text-xs px-3 py-1 rounded-full whitespace-nowrap
                                <?= $ucapan['kehadiran'] === 'Hadir'
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-red-100 text-red-700'; ?>">

                                <?= e($ucapan['kehadiran']); ?>

                            </span>

                        </div>

                        <p class="text-gray-600 leading-7 break-words">
                            <?= nl2br(e($ucapan['pesan'])); ?>
                        </p>

                        <p class="text-xs text-gray-400 mt-3">
                            <?= e($ucapan['tanggal']); ?>
                        </p>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="text-center py-10 text-gray-400">

                    Belum ada ucapan.
                    Jadilah yang pertama memberikan doa!

                </div>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- ================================== -->
<!-- FOOTER -->
<!-- ================================== -->

<footer class="py-12 text-center bg-[#26231e] text-white">

    <h2 class="text-3xl italic gold mb-4">
        Romeo & Juliet
    </h2>

    <p class="text-sm text-gray-400">
        Terima kasih atas doa dan restunya.
    </p>

    <div class="w-16 h-[1px] gold-bg mx-auto my-6"></div>

    <p class="text-xs text-gray-500">
        © 2027 Wedding Invitation
    </p>

</footer>


<!-- ================================== -->
<!-- JAVASCRIPT COUNTDOWN -->
<!-- ================================== -->

<script>

    const weddingDate = new Date(
        "<?= $tanggal_nikah; ?>"
    ).getTime();

    const countdown = setInterval(function () {

        const now = new Date().getTime();

        const distance = weddingDate - now;

        if (distance <= 0) {

            clearInterval(countdown);

            document.getElementById("days").innerText = "00";
            document.getElementById("hours").innerText = "00";
            document.getElementById("minutes").innerText = "00";
            document.getElementById("seconds").innerText = "00";

            document.getElementById("countdown-message").innerText =
                "Hari bahagia telah tiba!";

            return;
        }

        const days = Math.floor(
            distance / (1000 * 60 * 60 * 24)
        );

        const hours = Math.floor(
            (distance % (1000 * 60 * 60 * 24)) /
            (1000 * 60 * 60)
        );

        const minutes = Math.floor(
            (distance % (1000 * 60 * 60)) /
            (1000 * 60)
        );

        const seconds = Math.floor(
            (distance % (1000 * 60)) / 1000
        );

        document.getElementById("days").innerText = days;
        document.getElementById("hours").innerText = hours;
        document.getElementById("minutes").innerText = minutes;
        document.getElementById("seconds").innerText = seconds;

    }, 1000);

</script>

</body>
</html>
