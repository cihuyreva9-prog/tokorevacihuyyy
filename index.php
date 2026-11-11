
<?php
require __DIR__.'/lib.php';
$u=user();
$p=$_GET['p']??'';

$courier=[
    'Ambil di toko'=>0,
    'JNE Reguler'=>15000,
    'J&T Express'=>18000,
    'SiCepat'=>14000
];

if($p==='login'||$p==='daftar'){
    go(BASE.'/akun/login.php'.($p==='daftar'?'?daftar=1':''));
}

if($p==='wishlist'&&$_SERVER['REQUEST_METHOD']==='POST'){
    chk();
    need('pembeli');
    $id=(int)$_POST['id'];

    if(row('SELECT 1 x FROM wishlists WHERE user_id=? AND product_id=?',[$u['id'],$id])){
        q('DELETE FROM wishlists WHERE user_id=? AND product_id=?',[$u['id'],$id]);
    }else{
        q('INSERT INTO wishlists VALUES(?,?)',[$u['id'],$id]);
    }

    go($_SERVER['HTTP_REFERER']??BASE.'/index.php');
}

// ---- API untuk halaman belanja satu halaman ----
if(isset($_GET['api'])){
    header('Content-Type: application/json');
    $d=json_decode(file_get_contents('php://input'),true)?:[];

    if(!hash_equals(csrf(),(string)($d['csrf']??''))){
        http_response_code(419);
        echo json_encode(['ok'=>false,'msg'=>'Sesi habis, muat ulang halaman.']);
        exit;
    }

    try{
        if($_GET['api']==='voucher'){
            $vv=row(
                'SELECT code,type,value,min_total FROM vouchers WHERE code=? AND active=1',
                [strtoupper(trim($d['code']??''))]
            );

            if(!$vv)throw new Exception('Kode voucher tidak valid');

            echo json_encode(['ok'=>true,'v'=>$vv]);
            exit;
        }

        if($_GET['api']==='order'){
            $cr=$d['courier']??'';

            if(!isset($courier[$cr]))$cr='Ambil di toko';

            $pm=in_array($d['pm']??'',['transfer','qris','cod'],true)
                ?$d['pm']
                :'transfer';

            $name=trim($d['name']??'');
            $ph=trim($d['phone']??'');
            $ad=trim($d['address']??'');
            $cash=(int)($d['cash']??0);

            if($name===''||strlen(preg_replace('/\D/','',$ph))<8){
                throw new Exception('Isi nama dan nomor HP yang valid.');
            }

            if($cr!=='Ambil di toko'&&$ad===''){
                throw new Exception('Isi alamat pengiriman, atau pilih "Ambil di toko".');
            }

            $code=make_order([
                'source'=>'online',
                'user_id'=>($u&&$u['role']==='pembeli')?$u['id']:null,
                'status'=>$pm==='cod'?'diproses':'belum_bayar',
                'payment_method'=>$pm,
                'name'=>$name,
                'phone'=>$ph,
                'address'=>$ad,
                'courier'=>$cr,
                'shipping'=>$courier[$cr],
                'voucher'=>$d['voucher']??'',
                'amount_paid'=>($pm==='cod'&&$cash>0)?$cash:null
            ],$d['items']??[]);

            if($u&&$u['role']==='pembeli'&&!empty($d['save'])){
                q(
                    'UPDATE users SET name=?,phone=?,address=? WHERE id=?',
                    [
                        $name,
                        $ph,
                        $ad!==''?$ad:(row('SELECT address FROM users WHERE id=?',[$u['id']])['address']??''),
                        $u['id']
                    ]
                );
            }

            $o=row('SELECT total,change_amount FROM orders WHERE code=?',[$code]);

            echo json_encode([
                'ok'=>true,
                'code'=>$code,
                'total'=>$o['total'],
                'change'=>$o['change_amount'],
                'key'=>rkey($code),
                'pm'=>$pm,
                'bank'=>setting('bank_info')
            ]);
            exit;
        }

    }catch(Exception $x){
        echo json_encode(['ok'=>false,'msg'=>$x->getMessage()]);
        exit;
    }

    exit;
}

// ---- DATA ----
$cats=rows('SELECT * FROM categories');

$sold=array_column(
    rows('SELECT product_id,SUM(qty) q FROM order_items GROUP BY product_id'),
    'q',
    'product_id'
);

$rv=[];

foreach(
    rows('SELECT r.product_id,r.rating,r.comment,u.name FROM reviews r JOIN users u ON u.id=r.user_id ORDER BY r.id DESC')
    as $r
){
    $rv[$r['product_id']][]=$r;
}

$me=($u&&$u['role']==='pembeli')
    ?row('SELECT * FROM users WHERE id=?',[$u['id']])
    :null;

$wl=$me
    ?array_map('intval',array_column(rows('SELECT product_id FROM wishlists WHERE user_id=?',[$u['id']]),'product_id'))
    :[];

$P=[];

foreach(rows('SELECT * FROM products WHERE active=1 ORDER BY id DESC') as $x){
    $l=$rv[$x['id']]??[];

    $P[$x['id']]=[
        'id'=>(int)$x['id'],
        'name'=>$x['name'],
        'price'=>(int)$x['price'],
        'stock'=>(int)$x['stock'],
        'icon'=>$x['icon'],
        'image_path'=>$x['image_path']??'',
        'desc'=>$x['description'],
        'cat'=>(int)$x['category_id'],
        'sold'=>(int)($sold[$x['id']]??0),
        'n'=>count($l),
        'avg'=>$l?round(array_sum(array_column($l,'rating'))/count($l),1):0,
        'rv'=>array_slice($l,0,3)
    ];
}

// ---- PELACAKAN PESANAN ----
$lc=null;
$lcode=strtoupper(trim($_GET['lacak']??''));
$lhp=substr(preg_replace('/\D/','',$_GET['hp']??''),-4);

if($lcode!==''){
    $lc=row('SELECT * FROM orders WHERE code=?',[$lcode]);

    if(
        $lc&&
        !($me&&$lc['user_id']==$me['id'])&&
        ($lhp===''||substr(preg_replace('/\D/','',(string)$lc['phone']),-4)!==$lhp)
    ){
        $lc=false;
    }
}

head('Belanja');
?>

<div class="steps">
    <b class="on">1. Pilih produk</b>
    <b class="on">2. Isi data dan pilih pembayaran</b>
    <b class="on">3. Pesan, selesai</b>
</div>

<div class="shop">

    <section id="produk">
        <div class="tb">
            <input id="q" placeholder="Cari produk..." aria-label="Cari produk">

            <select id="ct" aria-label="Kategori">
                <option value="0">Semua kategori</option>
                <?php foreach($cats as $c): ?>
                    <option value="<?=e($c['id'])?>"><?=e($c['name'])?></option>
                <?php endforeach; ?>
            </select>

            <select id="so" aria-label="Urutkan">
                <option value="new">Terbaru</option>
                <option value="best">Terlaris</option>
                <option value="low">Termurah</option>
                <option value="high">Termahal</option>
            </select>
        </div>

        <div class="grid" id="grid"></div>
    </section>

    <aside class="card cartp" id="keranjang">

        <div id="co">
            <h3>Keranjang saya</h3>

            <div id="ln"></div>

            <div class="sum" id="sm"></div>

            <h3 style="margin-top:14px">Data penerima</h3>

            <label for="nm">Nama</label>
            <input id="nm" value="<?=e($me['name']??'')?>">

            <label for="hp">No. HP</label>
            <input id="hp" value="<?=e($me['phone']??'')?>" inputmode="tel">

            <label for="ad">Alamat (kosongkan jika ambil di toko)</label>
            <textarea id="ad" rows="2"><?=e($me['address']??'')?></textarea>

            <label for="cr">Pengiriman</label>
            <select id="cr">
                <?php foreach($courier as $k=>$v): ?>
                    <option value="<?=e($k)?>">
                        <?=e($k)?> (<?=$v?rp($v):'gratis'?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="pm">Pembayaran</label>
            <select id="pm">
                <option value="transfer">Transfer bank</option>
                <option value="qris">QRIS</option>
                <option value="cod">Bayar di tempat (COD)</option>
            </select>

            <div id="cb" hidden>
                <label for="cash">Uang yang disiapkan (opsional)</label>
                <input id="cash" type="number" min="0">
                <div id="ck" class="mut"></div>
            </div>

            <label for="vc">Kode voucher</label>

            <div class="row">
                <input id="vc" placeholder="HEMAT10" style="width:140px">
                <button class="sm btn ghost" id="vb" type="button">Pakai</button>
            </div>

            <div class="mut" id="vm"></div>

            <?php if($me): ?>
                <label style="font-weight:400">
                    <input type="checkbox" id="sv" checked style="width:auto">
                    Simpan data ini ke profil untuk pesanan berikutnya
                </label>
            <?php endif; ?>

            <br>

            <button id="order" style="width:100%" disabled>
                Pesan sekarang
            </button>

            <p class="mut">
                <?=$me
                    ?'Masuk sebagai '.e($me['name']).' · poin '.(int)$me['points']
                    :'Tanpa perlu daftar. <a href="'.BASE.'/akun/login.php?daftar=1">Daftar sekali</a> agar data terisi otomatis di pesanan berikutnya.'
                ?>
            </p>
        </div>

        <div id="done" hidden></div>

    </aside>

</div>

<div class="bar">
    <span>Total <b id="bt">Rp 0</b></span>
    <a href="#keranjang">Lihat keranjang (<span id="cc">0</span>)</a>
</div>

<div class="modal" id="md">
    <div class="card" id="mb"></div>
</div>

<section class="card" id="lacak" style="margin-top:28px">
    <h2>Lacak pesanan</h2>

    <form class="row" method="get" action="#lacak">
        <input name="lacak" value="<?=e($lcode)?>" placeholder="No. pesanan" required>
        <input name="hp" value="<?=e($lhp)?>" placeholder="4 digit akhir HP" style="max-width:170px">
        <button>Cek</button>
    </form>

    <?php
    if($lc){
        echo '<p><b>'.e($lc['code']).'</b> '.badge($lc['status']).'</p>'.
            timeline($lc['status'],$lc['source']).
            '<p class="mut">Kurir: '.e($lc['courier']?:'-').
            ' · Resi: '.e($lc['tracking_no']?:'belum ada').
            ' · Total '.rp($lc['total']).'</p>'.
            '<a class="btn ghost sm" target="_blank" href="'.BASE.'/struk.php?code='.e($lc['code']).'&k='.rkey($lc['code']).'">Lihat invoice</a>';
    }elseif($lc===false){
        echo '<p class="kurang">Nomor pesanan atau 4 digit HP tidak cocok.</p>';
    }elseif($lcode!==''){
        echo '<p class="kurang">Pesanan tidak ditemukan.</p>';
    }
    ?>
</section>

<section class="card" id="bantuan">
    <h2>Bantuan</h2>

    <details>
        <summary>Cara membayar</summary>
        <p>Transfer bank, QRIS, atau COD. <?=e(setting('bank_info'))?></p>
    </details>

    <details>
        <summary>Berapa lama pengiriman?</summary>
        <p>Pesanan diproses di hari yang sama setelah pembayaran terverifikasi.</p>
    </details>

    <details>
        <summary>Barang bermasalah?</summary>
        <p>Hubungi <?=e(setting('store_phone'))?> dengan nomor pesanan.</p>
    </details>
</section>

<script>
const P=<?=json_encode($P)?>,
C=<?=json_encode($courier)?>,
TAX=<?=(float)setting('tax_percent')?>,
RND=<?=(int)setting('rounding')?>,
CS="<?=csrf()?>",
BASE="<?=BASE?>",
WL=new Set(<?=json_encode($wl)?>),
LOGIN=<?=$me?'true':'false'?>;

const $=i=>document.getElementById(i);

const rp=n=>'Rp '+Math.round(n).toLocaleString('id-ID');

const esc=s=>String(s??'').replace(/[&<>"]/g,c=>({
    '&':'&amp;',
    '<':'&lt;',
    '>':'&gt;',
    '"':'&quot;'
}[c]));

let cart={},V=null;

try{
    cart=JSON.parse(localStorage.getItem('tk_cart')||'{}');
}catch(e){}

for(const i in cart){
    if(!P[i]||P[i].stock<1){
        delete cart[i];
    }else{
        cart[i]=Math.min(+cart[i],P[i].stock);
    }
}

function save(){
    try{
        localStorage.setItem('tk_cart',JSON.stringify(cart));
    }catch(e){}
}

// ---- FOTO PRODUK ----
function productPhoto(p,modal=false){

    const width=modal?'100%':'100%';
    const height=modal?'230px':'180px';

    const fallback=`
        <span style="
            display:grid;
            place-items:center;
            width:100%;
            height:${height};
            font-size:${modal?'85px':'65px'};
            background:#f5f5f5;
            border-radius:12px;
        ">${esc(p.icon||'📦')}</span>
    `;

    if(!p.image_path){
        return fallback;
    }

    const src=BASE+'/'+esc(p.image_path);

    return `
        <img
            src="${src}"
            alt="${esc(p.name)}"
            loading="lazy"
            style="
                display:block;
                width:${width};
                height:${height};
                object-fit:cover;
                border-radius:12px;
            "
            onerror="
                this.style.display='none';
                this.nextElementSibling.style.display='grid';
            "
        >
        <span style="
            display:none;
            place-items:center;
            width:100%;
            height:${height};
            font-size:${modal?'85px':'65px'};
            background:#f5f5f5;
            border-radius:12px;
        ">${esc(p.icon||'📦')}</span>
    `;
}

function add(i,d=1){
    const p=P[i];

    if(!p)return;

    const q=(cart[i]||0)+d;

    if(q<=0){
        delete cart[i];
    }else if(q>p.stock){
        alert('Stok '+p.name+' hanya '+p.stock);
    }else{
        cart[i]=q;
    }

    save();
    draw();
}

// ---- KATALOG PRODUK ----
function grid(){

    const s=$('q').value.toLowerCase();
    const c=+$('ct').value;
    const o=$('so').value;

    let l=Object.values(P).filter(p=>
        (!c||p.cat===c)&&
        (!s||(p.name+' '+p.desc).toLowerCase().includes(s))
    );

    l.sort(
        o==='best'?(a,b)=>b.sold-a.sold:
        o==='low'?(a,b)=>a.price-b.price:
        o==='high'?(a,b)=>b.price-a.price:
        (a,b)=>b.id-a.id
    );

    $('grid').innerHTML=l.map(p=>`
        <article class="pc">

            <a
                class="pic"
                href="#"
                onclick="show(${p.id});return false"
                aria-label="Detail ${esc(p.name)}"
                style="
                    display:block;
                    overflow:hidden;
                    text-decoration:none;
                "
            >
                ${productPhoto(p)}
            </a>

            <div class="pb">

                <a
                    class="pn"
                    href="#"
                    onclick="show(${p.id});return false"
                >
                    ${esc(p.name)}
                </a>

                <div class="pr">${rp(p.price)}</div>

                <div class="mut">
                    ${p.stock>0?'Stok '+p.stock:'Stok habis'}
                    ${p.n?' · ★ '+p.avg:''}
                </div>

                <div class="row" style="margin-top:auto">

                    <button
                        class="sm"
                        ${p.stock<1?'disabled':''}
                        onclick="add(${p.id})"
                    >
                        + Keranjang
                    </button>

                    ${LOGIN?`
                        <button
                            class="heart"
                            title="Wishlist"
                            onclick="wish(${p.id},this)"
                        >
                            ${WL.has(p.id)?'♥':'♡'}
                        </button>
                    `:''}

                </div>

            </div>
        </article>
    `).join('')||'<div class="card">Tidak ada produk yang cocok.</div>';
}

// ---- DETAIL PRODUK ----
function show(i){

    const p=P[i];

    $('mb').innerHTML=`

        <div style="
            width:100%;
            max-width:420px;
            margin:0 auto 15px;
            overflow:hidden;
        ">
            ${productPhoto(p,true)}
        </div>

        <h2>${esc(p.name)}</h2>

        <div class="pr">${rp(p.price)}</div>

        <p>${esc(p.desc)}</p>

        <p class="mut">
            ${p.stock>0?'Stok '+p.stock:'Stok habis'}
            ${p.n?' · ★ '+p.avg+' ('+p.n+' ulasan)':''}
        </p>

    `+

    p.rv.map(r=>`
        <div class="mut">
            <b>${esc(r.name)}</b>
            ${'★'.repeat(r.rating)}
            ${esc(r.comment)}
        </div>
    `).join('')+

    '<br>'+

    (p.stock>0?`
        <button onclick="add(${i});$('md').classList.remove('on')">
            Tambah ke keranjang
        </button>
    `:'')+

    `
        <button
            class="btn ghost"
            onclick="$('md').classList.remove('on')"
        >
            Tutup
        </button>
    `;

    $('md').classList.add('on');
}

$('md').onclick=e=>{
    if(e.target===$('md')){
        $('md').classList.remove('on');
    }
};

// ---- WISHLIST ----
async function wish(i,b){

    const f=new FormData();

    f.append('csrf',CS);
    f.append('id',i);

    await fetch(BASE+'/index.php?p=wishlist',{
        method:'POST',
        body:f
    });

    if(WL.has(i)){
        WL.delete(i);
        b.textContent='♡';
    }else{
        WL.add(i);
        b.textContent='♥';
    }
}

// ---- PERHITUNGAN TOTAL ----
function calc(){

    let sub=0;

    for(const i in cart){
        sub+=P[i].price*cart[i];
    }

    let vd=0;

    if(V&&sub>=V.min_total){
        vd=Math.min(
            sub,
            V.type==='percent'
                ?Math.round(sub*V.value/100)
                :+V.value
        );
    }

    const sh=C[$('cr').value]||0;
    const b=sub-vd;
    const tax=Math.round(b*TAX/100);
    const raw=b+tax+sh;
    const tot=RND?Math.round(raw/RND)*RND:raw;

    return{
        sub,
        vd,
        sh,
        tax,
        rd:tot-raw,
        tot
    };
}

// ---- TAMPILAN KERANJANG ----
function draw(){

    const c=calc();
    const ids=Object.keys(cart);
    const n=ids.reduce((s,i)=>s+cart[i],0);

    if($('cn'))$('cn').textContent=n;

    $('cc').textContent=n;
    $('bt').textContent=rp(c.tot);

    $('ln').innerHTML=ids.map(i=>`

        <div class="cl">

            <div>
                ${esc(P[i].icon||'📦')}
                ${esc(P[i].name)}

                <div class="mut">${rp(P[i].price)}</div>
            </div>

            <div class="q">

                <button
                    class="sm"
                    onclick="add(${i},-1)"
                    aria-label="Kurangi"
                >−</button>

                <b>${cart[i]}</b>

                <button
                    class="sm"
                    onclick="add(${i},1)"
                    aria-label="Tambah"
                >+</button>

            </div>

            <b>${rp(P[i].price*cart[i])}</b>

        </div>

    `).join('')||

    '<p class="mut">Keranjang masih kosong. Pilih produk di daftar, lalu tekan + Keranjang.</p>';

    $('sm').innerHTML=n?`

        <div>
            <span>Subtotal</span>
            <span>${rp(c.sub)}</span>
        </div>

        ${c.vd?`
            <div>
                <span>Voucher</span>
                <span>-${rp(c.vd)}</span>
            </div>
        `:''}

        <div>
            <span>PPN ${TAX}%</span>
            <span>${rp(c.tax)}</span>
        </div>

        <div>
            <span>Ongkir</span>
            <span>${rp(c.sh)}</span>
        </div>

        ${c.rd?`
            <div>
                <span>Pembulatan</span>
                <span>${rp(c.rd)}</span>
            </div>
        `:''}

        <div class="t">
            <span>Total bayar</span>
            <span>${rp(c.tot)}</span>
        </div>

    `:'';

    const cod=$('pm').value==='cod';

    $('cb').hidden=!cod;

    if(cod){
        const k=+$('cash').value||0;

        $('ck').innerHTML=k
            ?(
                k>=c.tot
                    ?`Kembalian dari kurir: <b>${rp(k-c.tot)}</b>`
                    :`<span class="kurang">Uang kurang ${rp(c.tot-k)}</span>`
            )
            :'';
    }

    $('order').disabled=!n;
}

// ---- API ----
async function api(a,b){

    const r=await fetch('?api='+a,{
        method:'POST',
        headers:{
            'Content-Type':'application/json'
        },
        body:JSON.stringify({
            ...b,
            csrf:CS
        })
    });

    return r.json();
}

// ---- VOUCHER ----
$('vb').onclick=async()=>{

    const r=await api('voucher',{
        code:$('vc').value
    });

    V=r.ok?r.v:null;

    $('vm').textContent=r.ok
        ?'Voucher '+r.v.code+' dipakai (min. belanja '+rp(r.v.min_total)+')'
        :r.msg;

    draw();
};

// ---- PEMESANAN ----
$('order').onclick=async()=>{

    const b=$('order');

    b.disabled=true;

    const r=await api('order',{

        items:cart,
        name:$('nm').value,
        phone:$('hp').value,
        address:$('ad').value,
        courier:$('cr').value,
        pm:$('pm').value,
        voucher:V?V.code:'',
        cash:+$('cash').value||0,
        save:$('sv')?$('sv').checked:false

    });

    if(!r.ok){
        alert(r.msg);
        b.disabled=false;
        return;
    }

    cart={};
    save();
    draw();

    $('co').hidden=true;

    const d=$('done');

    d.hidden=false;

    d.innerHTML=`

        <h3>Pesanan berhasil dibuat</h3>

        <div class="steps">
            <b class="on">1. Pesanan dibuat</b>
            <b>2. ${r.pm==='cod'?'Dikemas':'Bayar'}</b>
            <b>3. Dikirim</b>
        </div>

        <p>
            No. pesanan <b>${esc(r.code)}</b><br>
            Total <b>${rp(r.total)}</b>
        </p>

    `+

    (r.pm==='cod'
        ?`
            <p>
                Siapkan uang tunai saat kurir tiba.
                ${r.change?' Kembalian dari kurir '+rp(r.change)+'.':''}
            </p>
        `
        :`
            <p>
                Bayar via ${r.pm.toUpperCase()}:
                <b>${esc(r.bank)}</b>.
                Pesanan diproses setelah admin memverifikasi pembayaran.
            </p>
        `
    )+

    `

        <p class="mut">
            Catat nomor pesanan untuk melacak.
        </p>

        <a
            class="btn ghost sm"
            target="_blank"
            href="${BASE}/struk.php?code=${encodeURIComponent(r.code)}&k=${encodeURIComponent(r.key)}"
        >
            Lihat invoice
        </a>

        <button
            class="sm"
            onclick="location.href='${BASE}/index.php'"
        >
            Belanja lagi
        </button>

    `;
};

// ---- EVENT ----
['q','ct','so'].forEach(i=>{
    $(i).oninput=grid;
});

['cr','pm','cash'].forEach(i=>{
    $(i).oninput=draw;
});

// ---- TAMBAH PRODUK DARI URL ----
const ad=new URLSearchParams(location.search).get('add');

if(ad&&P[ad]&&P[ad].stock>0){
    cart[ad]=Math.min(
        P[ad].stock,
        (cart[ad]||0)+1
    );

    save();

    history.replaceState(null,'',BASE+'/index.php');
}

// ---- JALANKAN HALAMAN ----
grid();
draw();

</script>

<?php foot(); ?>
