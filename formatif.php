<?php

class User 
{
    public $nama, $noHp;

    public function __construct($nama, $noHp) 
    {
        $this->nama = $nama;
        $this->noHp = $noHp;
    }

    public function getNama() 
    { 
        return $this->nama; 
    }

    public function getStatus() 
    { 
        return "User Biasa"; 
    }
}

class Pelanggan extends User 
{
    public $poin = 0;

    public function getStatus($subtotal = 0) 
    { 
        return ($subtotal > 50000) ? "Member" : "Non-Member"; 
    }

    public function tambahPoin($total) 
    { 
        $this->poin += floor($total / 10000); 
    }
}

class Layanan 
{
    public $jenis, $tarif;

    public function __construct($jenis) 
    {
        $this->jenis = $jenis;
        $this->tarif = match($jenis) { 
            "GoRide Reguler"   => 2500, 
            "GoRide Prioritas" => 3000, 
            "GoCar"            => 4500, 
            "GoCar XL"         => 6000, 
            "GoFood"           => 2000, 
            default            => 0 
        };
    }
}

class Voucher 
{
    public $kodeVoucher, $diskonPersen;

    public function __construct($kodeVoucher) 
    {
        $this->kodeVoucher = $kodeVoucher;
        $this->diskonPersen = match(strtoupper($kodeVoucher)) { 
            "HEMAT10" => 0.1, 
            "HEMAT20" => 0.2, 
            "HEMAT30" => 0.3, 
            default   => 0 
        };
    }

    public function hitungDiskon($subtotal) 
    { 
        return $subtotal * $this->diskonPersen; 
    }
}

abstract class Pembayaran 
{
    abstract public function getMetode();
    abstract public function getBiayaAdmin();
}

class Cash extends Pembayaran 
{
    public function getMetode() 
    { 
        return "Cash"; 
    }

    public function getBiayaAdmin() 
    { 
        return 0; 
    }
}

class EWallet extends Pembayaran 
{
    public function getMetode() 
    { 
        return "E-Wallet"; 
    }

    public function getBiayaAdmin() 
    { 
        return 1000; 
    }
}

class TransferBank extends Pembayaran 
{
    public function getMetode() 
    { 
        return "Transfer Bank"; 
    }

    public function getBiayaAdmin() 
    { 
        return 2500; 
    }
}

class Transaksi 
{
    private static $totalTransaksi = 0;
    public $pelanggan, $layanan, $pembayaran, $voucher, $jarakTempuh, $subtotal, $diskonMember, $diskonVoucher, $biayaAdmin, $total, $statusMember;

    public function __construct(Pelanggan $pelanggan, Layanan $layanan, Pembayaran $pembayaran, Voucher $voucher, $jarakTempuh) 
    {
        $this->pelanggan = $pelanggan;
        $this->layanan = $layanan;
        $this->pembayaran = $pembayaran;
        $this->voucher = $voucher;
        $this->jarakTempuh = $jarakTempuh;
        self::$totalTransaksi++;
    }

    public static function getTotalTransaksi() 
    { 
        return self::$totalTransaksi; 
    }

    public function hitungTotal() 
    {
        $this->subtotal = $this->jarakTempuh * $this->layanan->tarif;
        $this->statusMember = $this->pelanggan->getStatus($this->subtotal);
        $this->diskonMember = ($this->statusMember == "Member") ? $this->subtotal * 0.05 : 0;
        $this->diskonVoucher = $this->voucher->hitungDiskon($this->subtotal);
        $this->biayaAdmin = $this->pembayaran->getBiayaAdmin();
        $this->total = $this->subtotal - $this->diskonMember - $this->diskonVoucher + $this->biayaAdmin;
        $this->pelanggan->tambahPoin($this->total);
    }
}

$tampilan = $error = "";
$nama = $noHp = $jarak = $voucher = "";

$pilihanLayanan = $_POST['layanan'] ?? '';
$pilihanBayar = $_POST['metodeBayar'] ?? '';

if (isset($_POST['hitung'])) {
    $nama = trim($_POST['nama']); 
    $noHp = trim($_POST['noHp']); 
    $jarak = floatval($_POST['jarak']); 
    $voucher = trim($_POST['voucher']);
    
    $voucherValid = empty($voucher) ? true : match(strtoupper($voucher)) { 
        "HEMAT10", "HEMAT20", "HEMAT30" => true, 
        default                         => false 
    };

    if (empty($nama)) {
        $error = "Nama tidak boleh kosong!";
    } elseif (strlen($noHp) < 10) {
        $error = "Nomor HP minimal 10 digit!";
    } elseif ($jarak <= 0) {
        $error = "Jarak harus lebih dari 0 km!";
    } elseif (!$voucherValid) {
        $error = "Voucher tidak valid!";
    }

    if (empty($error)) {
        $pembayaran = match($pilihanBayar) { 
            "EWallet"      => new EWallet(), 
            "TransferBank" => new TransferBank(), 
            default        => new Cash() 
        };

        $trx = new Transaksi(new Pelanggan($nama, $noHp), new Layanan($pilihanLayanan), $pembayaran, new Voucher($voucher), $jarak);
        $trx->hitungTotal();

        $tampilan = "Nama Pelanggan: {$trx->pelanggan->nama} <br>
            Status: {$trx->statusMember} <br>
            Poin: {$trx->pelanggan->poin} Poin <br><hr>
            Layanan: {$trx->layanan->jenis}<br>
            Jarak: {$trx->jarakTempuh} km <br>
            Tarif/km: Rp " . number_format($trx->layanan->tarif, 0, ',', '.') . "<br>
            Metode: " . $trx->pembayaran->getMetode() . "<hr>
            Subtotal: Rp " . number_format($trx->subtotal, 0, ',', '.') . "<br>
            Diskon Member: Rp " . number_format($trx->diskonMember, 0, ',', '.') . "<br>
            Diskon Voucher: Rp " . number_format($trx->diskonVoucher, 0, ',', '.') . "<br>
            Admin: Rp " . number_format($trx->biayaAdmin, 0, ',', '.') . "<br>
            <strong>Total Bayar: Rp " . number_format($trx->total, 0, ',', '.') . "</strong><br>
            <small style='color: gray;'>Total Transaksi: " . Transaksi::getTotalTransaksi() . "</small>";
    }
}
?>  

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sistem Ojek Online</title>
    <style>
        body { 
            background: #ebebeb; 
            font-family: sans-serif; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            margin: 0; 
        }
        .box { 
            background: #fff; 
            padding: 20px; 
            width: 350px; 
            margin-bottom: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
        }
        .error-box { 
            background: #ffebee; 
            color: #c62828; 
            border: 1px solid #ef9a9a; 
        }
        input, select, button { 
            width: 100%; 
            padding: 8px; 
            margin: 6px 0; 
            box-sizing: border-box; 
            border-radius: 4px; 
            border: 1px solid #ccc; 
        }
        #btn-hitung, #nav-button { 
            background: #6ecb5f; 
            color: #fff; 
            border: none; 
            cursor: pointer; 
            transition: 0.3s; 
        }
        #btn-hitung { 
            font-weight: bold; 
        }
        #nav-button { 
            padding: 10px; 
            width: 150px; 
            border-radius: 4px; 
        }
        #nav-header { 
            display: flex; 
            justify-content: center; 
            background-color: #5caf50; 
            width: 100%; 
            padding: 15px; 
            transition: 0.3s;

        }
    </style>
</head>
<body>

    <nav id="nav-header">
        <button id="nav-button" onclick="ubahwarna()">Ubah Warna</button>
    </nav>

    <h2>Sistem Ojek Online Premium</h2>
    
    <?php if ($error): ?>
        <div class="box error-box">
            <strong>Kesalahan:</strong> <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="box">
        Nama: 
        <input type="text" name="nama" value="<?= $nama ?>" required>
        
        No HP: 
        <input type="number" name="noHp" value="<?= $noHp ?>" required>
        
        Jarak (km): 
        <input type="number" step="any" name="jarak" value="<?= $jarak ?>" required>
        
        Layanan: 
        <select name="layanan">
            <option value="GoRide Reguler" <?= $pilihanLayanan == 'GoRide Reguler' ? 'selected' : '' ?>>GoRide Reguler</option>
            <option value="GoRide Prioritas" <?= $pilihanLayanan == 'GoRide Prioritas' ? 'selected' : '' ?>>GoRide Prioritas</option>
            <option value="GoCar" <?= $pilihanLayanan == 'GoCar' ? 'selected' : '' ?>>GoCar</option>
            <option value="GoCar XL" <?= $pilihanLayanan == 'GoCar XL' ? 'selected' : '' ?>>GoCar XL</option>
            <option value="GoFood" <?= $pilihanLayanan == 'GoFood' ? 'selected' : '' ?>>GoFood</option>
        </select>
        
        Pembayaran: 
        <select name="metodeBayar">
            <option value="cash" <?= $pilihanBayar == 'cash' ? 'selected' : '' ?>>Cash</option>
            <option value="EWallet" <?= $pilihanBayar == 'EWallet' ? 'selected' : '' ?>>E-Wallet</option>
            <option value="TransferBank" <?= $pilihanBayar == 'TransferBank' ? 'selected' : '' ?>>Transfer Bank</option>
        </select>
        
        Voucher: 
        <input type="text" name="voucher" value="<?= $voucher ?>">
        
        <button type="submit" id="btn-hitung" name="hitung">Hitung Biaya</button>
    </form>

    <?php if ($tampilan): ?>
        <div class="box">
            <h3>Struktur Pembayaran</h3>
            <p><?= $tampilan ?></p>
        </div>
    <?php endif; ?>

    <script>
        function ubahwarna() {
            var nbt = document.getElementById("nav-button");
            var btn = document.getElementById("btn-hitung");
            var nav = document.getElementById("nav-header");

            if (btn.style.backgroundColor === "rgb(255, 235, 59)") {
                btn.style.backgroundColor = "#6ecb5f"; 
                nbt.style.backgroundColor = "#6ecb5f"; 
                nav.style.backgroundColor = "#5caf50"; 
                btn.style.color = "#fff";
                nbt.style.color = "#fff";
            } else {
                btn.style.backgroundColor = "#ffeb3b"; 
                nbt.style.backgroundColor = "#ffeb3b"; 
                nav.style.backgroundColor = "#fbe200"; 
                btn.style.color = "#000";
                nbt.style.color = "#000";
            }
        }
    </script>
</body>
</html>