
<?php
require __DIR__.'/lib.php';

$u = user();

$o = row(
    'SELECT o.*, k.name kasir
     FROM orders o
     LEFT JOIN users k ON k.id=o.cashier_id
     WHERE o.code=?',
    [$_GET['code'] ?? '']
);

$okk = $o && (
    ($u && in_array($u['role'], ['admin','kasir'])) ||
    ($u && $o['user_id'] == $u['id']) ||
    hash_equals(rkey($o['code']), (string)($_GET['k'] ?? ''))
);

if (!$okk) {
    http_response_code(404);
    exit('Struk tidak ditemukan');
}

$staff = $u && $u['role'] !== 'pembeli';
$copy = $o['print_count'] > 0;

if ($staff && isset($_GET['auto'])) {
    q('UPDATE orders SET print_count=print_count+1 WHERE id=?', [$o['id']]);
}

$items = rows(
    'SELECT * FROM order_items WHERE order_id=?',
    [$o['id']]
);

$pos = $o['source'] === 'pos';
$w = $pos ? (int)setting('paper_width') : 80;

function ln($a, $b, $c = '') {
    return '<div class="line '.$c.'"><span>'.$a.'</span><span>'.$b.'</span></div>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Struk <?=e($o['code'])?></title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px 12px;
    font-family: 'Trebuchet MS', Arial, sans-serif;
    background: linear-gradient(135deg,#fff0f6,#eef7ff,#f0fff4);
    color: #475569;
}

.no {
    text-align: center;
    margin-bottom: 18px;
}

.no a,
.no button {
    display: inline-block;
    border: 0;
    border-radius: 30px;
    padding: 11px 20px;
    margin: 4px;
    background: #ff8eb5;
    color: white;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 4px 12px #ff8eb544;
}

.no button {
    background: #8bbcf7;
}

.receipt {
    width: <?=$w?>mm;
    max-width: 100%;
    margin: auto;
    padding: 20px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 35px #64748b20;
    position: relative;
    overflow: hidden;
}

.decoration {
    position: absolute;
    top: 5px;
    right: 10px;
    font-size: 22px;
    opacity: .5;
}

.header {
    text-align: center;
    padding: 12px 0 16px;
    border-bottom: 2px dashed #f6b8d0;
}

.mascot {
    width: 78px;
    height: 78px;
    margin: 0 auto 8px;
    border-radius: 50%;
    background: #fff0f6;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 43px;
    border: 3px solid #ffd3e3;
}

.store {
    font-size: 21px;
    font-weight: 900;
    color: #ec6d9d;
    margin: 5px 0;
    word-break: break-word;
}

.address {
    font-size: 11px;
    line-height: 1.6;
    color: #8492a6;
}

.tagline {
    display: inline-block;
    margin-top: 8px;
    padding: 5px 13px;
    background: #fff0f6;
    color: #e66d9b;
    border-radius: 20px;
    font-size: 10px;
    font-weight: bold;
}

.copy {
    text-align: center;
    background: #fff1c9;
    color: #b7791f;
    padding: 7px;
    border-radius: 8px;
    font-weight: bold;
    margin: 12px 0;
}

.section {
    margin: 13px 0;
}

.line {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin: 6px 0;
    font-size: 12px;
}

.line span:last-child {
    text-align: right;
    overflow-wrap: anywhere;
}

.muted {
    color: #94a3b8;
    font-size: 10px;
}

.divider {
    border: 0;
    border-top: 2px dashed #f6b8d0;
    margin: 12px 0;
}

.product {
    margin: 9px 0;
    font-size: 12px;
}

.product-name {
    font-weight: bold;
    color: #475569;
    margin-bottom: 4px;
    overflow-wrap: anywhere;
}

.product-detail {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    color: #8492a6;
    font-size: 11px;
}

.total-box {
    background: #fff2f7;
    border: 1px solid #ffd6e5;
    border-radius: 12px;
    padding: 12px;
    margin: 12px 0;
}

.total {
    font-size: 16px;
    font-weight: 900;
    color: #e45d91;
}

.footer {
    text-align: center;
    padding-top: 12px;
    border-top: 2px dashed #f6b8d0;
    font-size: 11px;
    line-height: 1.7;
    color: #8492a6;
}

.thanks {
    color: #ec6d9d;
    font-size: 14px;
    font-weight: bold;
    margin: 10px 0 5px;
}

.stars {
    color: #f5b942;
    letter-spacing: 4px;
    font-size: 17px;
}

@media print {
    body {
        background: white;
        padding: 0;
        margin: 0;
    }

    .no {
        display: none;
    }

    .receipt {
        width: <?=$w?>mm;
        max-width: none;
        margin: 0;
        padding: 5px;
        border-radius: 0;
        box-shadow: none;
    }

    .mascot {
        border: none;
    }

    @page {
        margin: 2mm;
        size: <?=$w?>mm auto;
    }
}

@media screen and (max-width: 450px) {
    .receipt {
        padding: 14px;
    }
}
</style>
</head>

<body>

<div class="no">
    <a href="?code=<?=e($o['code'])?>&auto=1<?=isset($_GET['k'])?'&k='.e($_GET['k']):''?>">
        🖨️ Cetak Struk
    </a>

    <button onclick="history.length>1?history.back():window.close()">
        ✨ Tutup
    </button>
</div>

<div class="receipt">

    <div class="decoration">🌸 ⭐ 🌈</div>

    <div class="header">
        <div class="mascot">🛍️</div>

        <div class="store">
            <?=e(setting('store_name'))?>
        </div>

        <div class="address">
            <?=e(setting('store_address'))?><br>
            ☎ <?=e(setting('store_phone'))?>
        </div>

        <div class="tagline">
            💗 Terima kasih sudah berbelanja 💗
        </div>
    </div>

    <?php if ($copy): ?>
        <div class="copy">⭐ SALINAN STRUK ⭐</div>
    <?php endif; ?>

    <div class="section">
        <?=ln('🧾 Kode', e($o['code']))?>

        <?=ln('📅 Tanggal', date('d/m/Y H:i', strtotime($o['created_at'])))?>

        <?php if ($pos): ?>
            <?=ln('👩‍💼 Kasir', e($o['kasir'] ?? '-'))?>
        <?php else: ?>
            <?=ln('👤 Pelanggan', e($o['name']))?>
            <?=ln('💳 Pembayaran', e(strtoupper($o['payment_method'])))?>
            <?=ln('📱 Telepon', e($o['phone']))?>
        <?php endif; ?>
    </div>

    <hr class="divider">

    <div style="font-weight:bold;color:#ec6d9d;margin-bottom:10px">
        🧺 Daftar Belanja
    </div>

    <?php foreach ($items as $i): ?>
        <div class="product">
            <div class="product-name">
                🌷 <?=e($i['name'])?>
            </div>

            <div class="product-detail">
                <span>
                    <?=number_format($i['qty'],0,',','.')?>
                    × Rp <?=number_format($i['price'],0,',','.')?>
                </span>

                <b>
                    Rp <?=number_format($i['price']*$i['qty'],0,',','.')?>
                </b>
            </div>
        </div>
    <?php endforeach; ?>

    <hr class="divider">

    <?=ln('Subtotal', 'Rp '.number_format($o['subtotal'],0,',','.'))?>

    <?php if ($o['discount']): ?>
        <?=ln('🎁 Diskon', '- Rp '.number_format($o['discount'],0,',','.'))?>
    <?php endif; ?>

    <?php if ($o['tax']): ?>
        <?=ln('PPN '.e(setting('tax_percent')).'%', 'Rp '.number_format($o['tax'],0,',','.'))?>
    <?php endif; ?>

    <?php if ($o['shipping']): ?>
        <?=ln('🚚 Ongkir', 'Rp '.number_format($o['shipping'],0,',','.'))?>
        <?=ln('Kurir', e($o['courier']))?>
    <?php endif; ?>

    <?php if ($o['rounding']): ?>
        <?=ln('Pembulatan', 'Rp '.number_format($o['rounding'],0,',','.'))?>
    <?php endif; ?>

    <div class="total-box">
        <?=ln('TOTAL BELANJA', 'Rp '.number_format($o['total'],0,',','.'), 'total')?>
    </div>

    <?php if ($o['amount_paid'] !== null): ?>
        <?=ln('💵 Uang diterima', 'Rp '.number_format($o['amount_paid'],0,',','.'))?>

        <?=ln('Kembalian', 'Rp '.number_format($o['change_amount'],0,',','.'))?>
    <?php endif; ?>

    <hr class="divider">

    <?php if ($o['status'] === 'dibatalkan'): ?>
        <div class="copy">❌ TRANSAKSI DIBATALKAN</div>
    <?php endif; ?>

    <div class="footer">
        <div class="thanks">💖 Terima Kasih! 💖</div>

        <div class="stars">★ ★ ★ ★ ★</div>

        <?=nl2br(e(setting('receipt_footer')))?>

        <p>🌈 Senang melayani Anda 🌈</p>
        <div>✨ Sampai jumpa kembali! ✨</div>
    </div>

</div>

<?php if (isset($_GET['auto'])): ?>
<script>
window.onload = () => window.print();
</script>
<?php endif; ?>

</body>
</html>
